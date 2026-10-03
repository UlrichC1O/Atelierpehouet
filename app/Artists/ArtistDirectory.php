<?php

namespace App\Artists;

use App\Cms\DatabaseHealth;
use App\Cms\Markdown;
use App\Cms\MediaItem;
use App\Cms\SafeUrl;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Exhibition;
use Carbon\CarbonInterface;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\LostConnectionDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use PDOException;
use Throwable;
use WeakReference;

/**
 * The public read model of the artist pages (docs/ARTISTS.md §4.1), a container singleton.
 *
 * One snapshot — every artist with its published artworks and exhibitions, as plain JSON-safe rows
 * (3 queries; no models, MediaItem or Carbon: the cache refuses to unserialize objects) — is cached in
 * Cache::store(config('cms.cache.store')) under self::CACHE_KEY for cms.cache.ttl seconds. Unpublished
 * artists are kept in it (flagged) for the admin preview; every public list leaves them out.
 *
 * Snapshots carry a `version` key (self::VERSION): anything else read back from the cache — an old
 * shape, an object the cache refused to unserialize — is a miss (docs/ARTISTS.md §12.1).
 *
 * The public site never breaks because of it (§12.2–3): every successful load also keeps a copy under
 * self::LASTGOOD_KEY (no expiry). When loading fails (database down or paused, tables missing, or the
 * CMS circuit breaker App\Cms\DatabaseHealth open) a warning is logged once per retry window, a failed
 * marker is cached for cms.cache.retry seconds and the last good copy is served (stale() = true); only
 * without any copy does everything answer as if there were no artist (available() = false). Only real
 * connection failures are reported to the circuit breaker — a missing table is not an outage.
 *
 * Visitors whose session says they are logged in (the admins) read fresh data (no cache read or write);
 * that check never touches the database (§12.4). Texts are localised (English falls back to French) and
 * photos resolved (cms()->media()) when read, never cached here. The snapshot is memoized per request.
 *
 * Summary array (all(), neighbors(), start of find()):
 *   id · slug · url · name · initials · discipline ?string · location ?string · statement ?string · accent ·
 *   portrait ?MediaItem · cover ?MediaItem · counts ['artworks' => int, 'exhibitions' => int] · published ·
 *   example · updated_at ?int (unix, the artist or one of its items)
 * Full artist (find()) = summary + bio_html ?HtmlString · meta_description · website ?string · instagram ?string ·
 *   links list{label, url, icon} · artworks list · availability_filters list · exhibitions [current, upcoming, past] ·
 *   cv [year ⇒ list]
 */
#[Singleton]
final class ArtistDirectory
{
    /** Cache key of the snapshot. */
    public const CACHE_KEY = 'artists.snapshot.v1';

    /** Copy of the last snapshot read from the database (no expiry), served while the database is unreachable. */
    public const LASTGOOD_KEY = self::CACHE_KEY.'.lastgood';

    /** Shape of the cached snapshot: bump it when the rows change, older copies are then misses. */
    public const VERSION = 2;

    private const EMPTY = ['version' => self::VERSION, 'artists' => []];

    private const ARTIST_COLUMNS = ['id', 'slug', 'name', 'discipline_fr', 'discipline_en', 'location', 'statement_fr',
        'statement_en', 'bio_fr', 'bio_en', 'meta_fr', 'meta_en', 'portrait_media_id', 'accent', 'website', 'instagram',
        'is_published', 'is_example', 'position', 'updated_at'];

    private const ARTWORK_COLUMNS = ['id', 'artist_id', 'media_id', 'title_fr', 'title_en', 'year', 'medium_fr',
        'medium_en', 'dimensions', 'description_fr', 'description_en', 'availability', 'is_published', 'updated_at'];

    private const EXHIBITION_COLUMNS = ['id', 'artist_id', 'media_id', 'title_fr', 'title_en', 'kind', 'venue', 'city',
        'year', 'starts_on', 'ends_on', 'description_fr', 'description_en', 'url', 'is_published', 'updated_at'];

    /** Longest meta description (characters, before the ellipsis). */
    private const META_LENGTH = 160;

    /** @var array{artists: list<array<string, mixed>>}|null memoized snapshot of the current request */
    private ?array $snapshot = null;

    private bool $available = false;

    /** The memoized snapshot is the last good copy, served because the database could not be read. */
    private bool $stale = false;

    /** The memoized snapshot was read from the database for a logged-in user. */
    private bool $fresh = false;

    /** @var WeakReference<object>|null the request the memoized snapshot belongs to */
    private ?WeakReference $scope = null;

    /** @var array<string, list<array<string, mixed>>> published summaries per locale (memo) */
    private array $summaries = [];

    /** True when there is artist data to show (from the database, the cache or the last good copy). */
    public function available(): bool
    {
        $this->data();

        return $this->available;
    }

    /** True when the data shown is the last good copy because the database could not be read. */
    public function stale(): bool
    {
        $this->data();

        return $this->stale;
    }

    /**
     * Whether the visitor's session says they are logged in — decided without the database (the users
     * table may be unreachable), so guests never cost a query. Never throws.
     */
    public static function maybeAdmin(?Request $request = null): bool
    {
        try {
            $guard = Auth::guard('web');

            // A user already resolved in this request (no query), e.g. after a login or in tests.
            if ($guard->hasUser()) {
                return true;
            }

            $request ??= app()->bound('request') ? app('request') : null;

            return $request instanceof Request
                && $request->hasSession()
                && $request->session()->has($guard->getName());
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether the visitor may preview unpublished artist pages: a logged-in administrator (the "admin"
     * gate when the CMS defines it). Guests are answered without touching the database. Never throws.
     */
    public static function canPreview(Request $request): bool
    {
        if (! self::maybeAdmin($request)) {
            return false;
        }

        try {
            $user = $request->user();

            return $user !== null && (! Gate::has('admin') || $user->can('admin'));
        } catch (Throwable) {
            return false;
        }
    }

    /** Forgets the cached and the memoized snapshot (the artist models call it on every write). Never throws. */
    public function flush(): void
    {
        $this->forget();

        try {
            $this->store()?->forget(self::CACHE_KEY);
        } catch (Throwable $e) {
            Log::warning('Artist pages cache could not be flushed: '.$e->getMessage());
        }
    }

    /**
     * The published artists, ordered by position then name.
     *
     * @return list<array<string, mixed>> summaries
     */
    public function all(?string $locale = null): array
    {
        $rows = $this->published();
        $locale = self::locale($locale);

        return $this->summaries[$locale] ??= array_map(fn (array $row): array => $this->summary($row, $locale), $rows);
    }

    /**
     * One artist with the whole page content, or null. Unpublished artists only with $withHidden
     * (a logged-in admin previewing the page).
     *
     * @return array<string, mixed>|null
     */
    public function find(string $slug, ?string $locale = null, bool $withHidden = false): ?array
    {
        foreach ($this->data()['artists'] as $row) {
            if ($row['slug'] === $slug) {
                return $row['published'] || $withHidden ? $this->full($row, self::locale($locale)) : null;
            }
        }

        return null;
    }

    /**
     * The published artists before and after $slug in all() order, wrapping around; both null when
     * there are fewer than two published artists or when $slug is not one of them.
     *
     * @return array{prev: array<string, mixed>|null, next: array<string, mixed>|null}
     */
    public function neighbors(string $slug): array
    {
        $artists = $this->all();
        $count = count($artists);
        $index = array_search($slug, array_column($artists, 'slug'), true);

        if ($count < 2 || ! is_int($index)) {
            return ['prev' => null, 'next' => null];
        }

        return [
            'prev' => $artists[($index - 1 + $count) % $count],
            'next' => $artists[($index + 1) % $count],
        ];
    }

    /** Number of published artists. */
    public function count(): int
    {
        return count($this->published());
    }

    /**
     * Sitemap entries: the index (only when there is at least one published artist) and every
     * published artist page.
     *
     * @return array<string, int|null> absolute URL ⇒ last modification (unix timestamp) or null
     */
    public function sitemapEntries(): array
    {
        $rows = $this->published();

        if ($rows === [] || ! Route::has('artists.index')) {
            return [];
        }

        $times = array_values(array_filter(array_column($rows, 'updated_at'), 'is_int'));
        $entries = [route('artists.index') => $times === [] ? null : max($times)];

        foreach ($rows as $row) {
            $entries[self::url($row['slug'])] = $row['updated_at'];
        }

        return $entries;
    }

    /** A photo of the CMS library (cms()->media()), or null: no id, no CMS, unknown photo. Never throws. */
    public static function media(?int $id): ?MediaItem
    {
        if ($id === null || $id < 1 || ! function_exists('cms')) {
            return null;
        }

        try {
            return cms()->media($id);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{artists: list<array<string, mixed>>}
     */
    private function data(): array
    {
        $fresh = $this->wantsFresh();
        $request = $this->request();

        if ($this->snapshot !== null && ($this->fresh || ! $fresh) && $this->scope?->get() === $request) {
            return $this->snapshot;
        }

        $this->forget();
        $this->fresh = $fresh;
        $this->scope = $request === null ? null : WeakReference::create($request);

        return $this->snapshot = $this->resolve($fresh);
    }

    /**
     * @return list<array<string, mixed>> snapshot rows of the published artists
     */
    private function published(): array
    {
        return array_values(array_filter($this->data()['artists'], fn (array $row): bool => $row['published']));
    }

    /**
     * The cached snapshot, else a fresh load (cached when it is not an admin's fresh read).
     *
     * @return array{artists: list<array<string, mixed>>}
     */
    private function resolve(bool $fresh): array
    {
        $this->available = false;
        $this->stale = false;
        $store = $this->store();

        if (! $fresh && $store !== null) {
            $cached = $this->read($store, self::CACHE_KEY);

            if ($cached !== null && ($cached['failed'] ?? false) === true) {
                return $this->fallback($store);
            }

            if ($cached !== null && is_array($cached['artists'] ?? null)) {
                $this->available = true;

                return $cached;
            }
        }

        // The CMS circuit breaker is open: the database was unreachable moments ago, do not wait for it again.
        if (! self::databaseHealthy()) {
            return $this->fallback($store);
        }

        try {
            $snapshot = $this->load();
        } catch (Throwable $e) {
            self::reportOutage($e);
            $this->failed($fresh ? null : $store, $e);

            return $this->fallback($store);
        }

        $this->available = true;

        if (! $fresh) {
            $this->put($store, self::CACHE_KEY, $snapshot, (int) config('cms.cache.ttl', 600));
        }

        $this->put($store, self::LASTGOOD_KEY, $snapshot, null);

        return $snapshot;
    }

    /**
     * The last good copy when there is one (stale), else the empty, unavailable snapshot.
     *
     * @return array{artists: list<array<string, mixed>>}
     */
    private function fallback(?Repository $store): array
    {
        $copy = $store === null ? null : $this->read($store, self::LASTGOOD_KEY);

        if ($copy !== null && is_array($copy['artists'] ?? null)) {
            $this->available = true;
            $this->stale = true;

            return $copy;
        }

        $this->available = false;

        return self::EMPTY;
    }

    /**
     * A cached value of this snapshot version, else null (missing, unreadable, an older shape, or an
     * object the cache could not unserialize).
     *
     * @return array<string, mixed>|null
     */
    private function read(Repository $store, string $key): ?array
    {
        try {
            $value = $store->get($key);
        } catch (Throwable $e) {
            Log::warning('Artist pages cache unreadable, reading the database: '.$e->getMessage());

            return null;
        }

        return is_array($value) && ($value['version'] ?? null) === self::VERSION ? $value : null;
    }

    /** False while the CMS circuit breaker (App\Cms\DatabaseHealth) says the database is unreachable. */
    private static function databaseHealthy(): bool
    {
        if (! class_exists(DatabaseHealth::class) || ! method_exists(DatabaseHealth::class, 'available')) {
            return true;
        }

        try {
            return (bool) app(DatabaseHealth::class)->available();
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Tells the CMS circuit breaker about a database that could not be reached. A missing table or a
     * broken query is not an outage and must not cut the whole site off from the database.
     */
    private static function reportOutage(Throwable $e): void
    {
        if (! self::isConnectionFailure($e) || ! class_exists(DatabaseHealth::class) || ! method_exists(DatabaseHealth::class, 'failed')) {
            return;
        }

        try {
            app(DatabaseHealth::class)->failed($e);
        } catch (Throwable) {
            // The breaker is best-effort.
        }
    }

    private static function isConnectionFailure(Throwable $e): bool
    {
        if (self::isMissingTable($e)) {
            return false;
        }

        // The CMS's own classification when it has one, so the whole site agrees on what an outage is.
        if (method_exists(DatabaseHealth::class, 'isOutage')) {
            try {
                return (bool) DatabaseHealth::isOutage($e);
            } catch (Throwable) {
                // Fall back to the checks below.
            }
        }

        for ($error = $e; $error !== null; $error = $error->getPrevious()) {
            $state = $error instanceof PDOException ? (string) ($error->errorInfo[0] ?? $error->getCode()) : '';

            if (str_starts_with($state, '08') || (new LostConnectionDetector)->causedByLostConnection($error)) {
                return true;
            }

            $message = strtolower($error->getMessage());

            foreach (['could not connect', 'connection refused', 'timed out', 'timeout expired', 'could not translate host', 'network is unreachable'] as $needle) {
                if (str_contains($message, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Reads the snapshot from the database (3 queries). Booleans are filtered here rather than in SQL
     * (their literals differ between drivers).
     *
     * @return array{artists: list<array<string, mixed>>}
     */
    private function load(): array
    {
        $db = DB::connection();

        $artists = $db->table('artists')->orderBy('position')->orderBy('name')->orderBy('id')->get(self::ARTIST_COLUMNS);

        $artworks = [];

        foreach ($db->table('artworks')->orderBy('artist_id')->orderBy('position')->orderBy('id')->get(self::ARTWORK_COLUMNS) as $row) {
            if (! self::bool($row->is_published)) {
                continue;
            }

            $artworks[(int) $row->artist_id][] = [
                'id' => (int) $row->id,
                'media' => self::id($row->media_id),
                'title_fr' => self::text($row->title_fr) ?? '',
                'title_en' => self::text($row->title_en),
                'year' => self::text($row->year),
                'medium_fr' => self::text($row->medium_fr),
                'medium_en' => self::text($row->medium_en),
                'dimensions' => self::text($row->dimensions),
                'description_fr' => self::text($row->description_fr),
                'description_en' => self::text($row->description_en),
                'availability' => in_array($row->availability, Artwork::AVAILABILITIES, true) ? (string) $row->availability : 'none',
                'updated_at' => self::time($row->updated_at),
            ];
        }

        $exhibitions = [];

        foreach ($db->table('exhibitions')->orderBy('artist_id')->orderBy('id')->get(self::EXHIBITION_COLUMNS) as $row) {
            if (! self::bool($row->is_published)) {
                continue;
            }

            $exhibitions[(int) $row->artist_id][] = [
                'id' => (int) $row->id,
                'media' => self::id($row->media_id),
                'title_fr' => self::text($row->title_fr) ?? '',
                'title_en' => self::text($row->title_en),
                'kind' => in_array($row->kind, Exhibition::KINDS, true) ? (string) $row->kind : 'other',
                'venue' => self::text($row->venue),
                'city' => self::text($row->city),
                'year' => (int) $row->year,
                'starts_on' => Dates::parse(self::text($row->starts_on))?->format('Y-m-d'),
                'ends_on' => Dates::parse(self::text($row->ends_on))?->format('Y-m-d'),
                'description_fr' => self::text($row->description_fr),
                'description_en' => self::text($row->description_en),
                'url' => self::https($row->url),
                'updated_at' => self::time($row->updated_at),
            ];
        }

        $accents = array_map('strval', (array) config('atelier.accents', []));
        $list = [];

        foreach ($artists as $row) {
            $id = (int) $row->id;
            $works = $artworks[$id] ?? [];
            $shows = $exhibitions[$id] ?? [];
            $times = array_values(array_filter(
                [self::time($row->updated_at), ...array_column($works, 'updated_at'), ...array_column($shows, 'updated_at')],
                'is_int',
            ));

            $list[] = [
                'id' => $id,
                'slug' => (string) $row->slug,
                'name' => self::text($row->name) ?? '',
                'discipline_fr' => self::text($row->discipline_fr),
                'discipline_en' => self::text($row->discipline_en),
                'location' => self::text($row->location),
                'statement_fr' => self::text($row->statement_fr),
                'statement_en' => self::text($row->statement_en),
                'bio_fr' => self::text($row->bio_fr),
                'bio_en' => self::text($row->bio_en),
                'meta_fr' => self::text($row->meta_fr),
                'meta_en' => self::text($row->meta_en),
                'portrait' => self::id($row->portrait_media_id),
                'accent' => in_array($row->accent, $accents, true) ? (string) $row->accent : Artist::DEFAULT_ACCENT,
                'website' => self::https($row->website),
                'instagram' => self::https($row->instagram),
                'published' => self::bool($row->is_published),
                'example' => self::bool($row->is_example),
                'updated_at' => $times === [] ? null : max($times),
                'artworks' => $works,
                'exhibitions' => $shows,
            ];
        }

        return ['version' => self::VERSION, 'artists' => $list];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function summary(array $row, string $locale): array
    {
        $cover = null;

        foreach ($row['artworks'] as $artwork) {
            if (($cover = self::media($artwork['media'])) !== null) {
                break;
            }
        }

        return [
            'id' => $row['id'],
            'slug' => $row['slug'],
            'url' => self::url($row['slug']),
            'name' => $row['name'],
            'initials' => Artist::initialsOf($row['name']),
            'discipline' => self::localized($row, 'discipline', $locale),
            'location' => $row['location'],
            'statement' => self::localized($row, 'statement', $locale),
            'accent' => $row['accent'],
            'portrait' => self::media($row['portrait']),
            'cover' => $cover,
            'counts' => ['artworks' => count($row['artworks']), 'exhibitions' => count($row['exhibitions'])],
            'published' => $row['published'],
            'example' => $row['example'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function full(array $row, string $locale): array
    {
        $artist = $this->summary($row, $locale);
        $today = Carbon::now();

        $artworks = array_map(fn (array $artwork): array => $this->artwork($artwork, $artist['name'], $locale), $row['artworks']);
        $present = array_column($artworks, 'availability');

        $exhibitions = array_map(fn (array $exhibition): array => $this->exhibition($exhibition, $locale, $today), $row['exhibitions']);
        $groups = [Dates::CURRENT => [], Dates::UPCOMING => [], Dates::PAST => []];

        foreach ($exhibitions as $exhibition) {
            $groups[$exhibition['status']][] = $exhibition;
        }

        return $artist + [
            'bio_html' => self::html(self::localized($row, 'bio', $locale)),
            'meta_description' => $this->metaDescription($row, $artist, $locale),
            'website' => $row['website'],
            'instagram' => $row['instagram'],
            'links' => self::links($row['website'], $row['instagram']),
            'artworks' => $artworks,
            'availability_filters' => array_values(array_filter(
                Artwork::AVAILABILITIES,
                fn (string $key): bool => $key !== 'none' && in_array($key, $present, true),
            )),
            'exhibitions' => [
                'current' => self::sorted($groups[Dates::CURRENT], fn (array $a, array $b): int => self::nullsLast($a['ends_on'], $b['ends_on'])
                    ?: self::nullsLast($a['starts_on'], $b['starts_on']) ?: $a['id'] <=> $b['id']),
                'upcoming' => self::sorted($groups[Dates::UPCOMING], fn (array $a, array $b): int => self::nullsLast($a['starts_on'], $b['starts_on'])
                    ?: $a['year'] <=> $b['year'] ?: $a['id'] <=> $b['id']),
                'past' => self::sorted($groups[Dates::PAST], fn (array $a, array $b): int => $b['year'] <=> $a['year']
                    ?: self::latestFirst($a, $b)),
            ],
            'cv' => self::cv($exhibitions),
        ];
    }

    /**
     * @param  array<string, mixed>  $row  snapshot artwork
     * @return array<string, mixed>
     */
    private function artwork(array $row, string $artistName, string $locale): array
    {
        $title = self::localized($row, 'title', $locale) ?? '';
        $medium = self::localized($row, 'medium', $locale);
        $media = self::media($row['media']);
        $availability = $row['availability'];

        // The photo's own alternative text, unless it only repeats the title (the uploader's default).
        $alt = trim((string) ($media?->alt[$locale] ?? ''));

        if ($alt === '' || $alt === $title || $alt === $row['title_fr']) {
            $alt = $artistName === '' ? $title : $title.' — '.$artistName;
        }

        $caption = implode(' — ', array_filter([
            implode(', ', array_filter([$title, $row['year']], self::filled(...))),
            implode(', ', array_filter([$medium, $row['dimensions']], self::filled(...))),
        ], self::filled(...)));

        return [
            'id' => $row['id'],
            'title' => $title,
            'year' => $row['year'],
            'medium' => $medium,
            'dimensions' => $row['dimensions'],
            'description' => self::localized($row, 'description', $locale),
            'availability' => $availability,
            'availability_label' => $availability === 'none' ? null : (string) __('artists.availability.'.$availability),
            'media' => $media,
            'alt' => $alt,
            'caption' => $caption,
            'landscape' => $media?->isLandscape() ?? false,
        ];
    }

    /**
     * @param  array<string, mixed>  $row  snapshot exhibition
     * @return array<string, mixed>
     */
    private function exhibition(array $row, string $locale, CarbonInterface $today): array
    {
        $status = Dates::status($row['starts_on'], $row['ends_on'], $row['year'], $today);

        return [
            'id' => $row['id'],
            'title' => self::localized($row, 'title', $locale) ?? '',
            'kind' => $row['kind'],
            'kind_label' => (string) __('artists.kinds.'.$row['kind']),
            'venue' => $row['venue'],
            'city' => $row['city'],
            'year' => $row['year'],
            'starts_on' => $row['starts_on'],
            'ends_on' => $row['ends_on'],
            'dates' => Dates::range($row['starts_on'], $row['ends_on'], $row['year'], $locale),
            'status' => $status,
            'status_label' => (string) __('artists.status.'.$status),
            'description' => self::localized($row, 'description', $locale),
            'url' => $row['url'],
            'media' => self::media($row['media']),
        ];
    }

    /**
     * meta → statement → "{name} — {discipline}", on one line, at most 160 characters.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $artist  summary
     */
    private function metaDescription(array $row, array $artist, string $locale): string
    {
        $text = self::localized($row, 'meta', $locale)
            ?? $artist['statement']
            ?? implode(' — ', array_filter([$artist['name'], $artist['discipline']], self::filled(...)));

        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', $text)), self::META_LENGTH, '…');
    }

    /**
     * Every published exhibition by year, most recent year first; within a year by start date
     * (latest first, year-only entries last), then newest entry first.
     *
     * @param  list<array<string, mixed>>  $exhibitions
     * @return array<int, list<array<string, mixed>>>
     */
    private static function cv(array $exhibitions): array
    {
        $years = [];

        foreach ($exhibitions as $exhibition) {
            $years[$exhibition['year']][] = $exhibition;
        }

        krsort($years);

        return array_map(fn (array $items): array => self::sorted($items, self::latestFirst(...)), $years);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function latestFirst(array $a, array $b): int
    {
        if ($a['starts_on'] !== $b['starts_on']) {
            if ($a['starts_on'] === null || $b['starts_on'] === null) {
                return $a['starts_on'] === null ? 1 : -1;
            }

            return strcmp($b['starts_on'], $a['starts_on']);
        }

        return $b['id'] <=> $a['id'];
    }

    /** Ascending order of two Y-m-d dates, a missing date after any date. */
    private static function nullsLast(?string $a, ?string $b): int
    {
        if ($a === $b) {
            return 0;
        }

        if ($a === null || $b === null) {
            return $a === null ? 1 : -1;
        }

        return strcmp($a, $b);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function sorted(array $items, callable $compare): array
    {
        usort($items, $compare);

        return $items;
    }

    /**
     * External links of the page: the website (labelled with its host) and Instagram (labelled with the handle).
     *
     * @return list<array{label: string, url: string, icon: string}>
     */
    private static function links(?string $website, ?string $instagram): array
    {
        $links = [];

        if ($website !== null) {
            $links[] = ['label' => self::host($website), 'url' => $website, 'icon' => 'globe'];
        }

        if ($instagram !== null) {
            $host = self::host($instagram);
            $handle = trim((string) strtok((string) parse_url($instagram, PHP_URL_PATH), '/'));
            $label = str_ends_with($host, 'instagram.com') ? ($handle !== '' ? '@'.ltrim($handle, '@') : 'Instagram') : $host;
            $links[] = ['label' => $label, 'url' => $instagram, 'icon' => 'instagram'];
        }

        return $links;
    }

    private static function host(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /** The biography as HTML: sanitized Markdown (App\Cms\Markdown), else escaped text with line breaks. */
    private static function html(?string $markdown): ?HtmlString
    {
        if ($markdown === null) {
            return null;
        }

        if (class_exists(Markdown::class)) {
            try {
                $html = Markdown::render($markdown);

                return trim($html->toHtml()) === '' ? null : $html;
            } catch (Throwable $e) {
                Log::warning('Artist biography could not be rendered as Markdown: '.$e->getMessage());
            }
        }

        return new HtmlString(nl2br(e($markdown), false));
    }

    /**
     * `{field}_{locale}` when filled, else `{field}_fr` (snapshot values are trimmed, blank ⇒ null).
     *
     * @param  array<string, mixed>  $row
     */
    private static function localized(array $row, string $field, string $locale): ?string
    {
        return $row[$field.'_'.$locale] ?? $row[$field.'_fr'] ?? null;
    }

    private static function url(string $slug): string
    {
        return Route::has('artists.show') ? route('artists.show', $slug) : url('artistes/'.$slug);
    }

    private static function locale(?string $locale): string
    {
        $locale = trim((string) ($locale ?? app()->getLocale()));

        return $locale === '' ? 'fr' : $locale;
    }

    /** Logged-in visitors (per their session — no database query) read fresh data. */
    private function wantsFresh(): bool
    {
        return self::maybeAdmin();
    }

    private function request(): ?object
    {
        try {
            return app()->bound('request') ? app('request') : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function forget(): void
    {
        $this->snapshot = null;
        $this->summaries = [];
        $this->scope = null;
    }

    /** A table of this module does not exist (migrations pending): the server answered, it is no outage. */
    private static function isMissingTable(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'no such table')
            || str_contains($message, 'undefined table')
            || (str_contains($message, 'relation') && str_contains($message, 'does not exist'))
            || str_contains($message, "doesn't exist");
    }

    /** Logs the failure (once per retry window for cached reads) and remembers it for cms.cache.retry seconds. */
    private function failed(?Repository $store, Throwable $e): void
    {
        $retry = (int) config('cms.cache.retry', 30);
        $warn = true;

        try {
            $warn = $store === null || $retry <= 0 || $store->add(self::CACHE_KEY.'.warned', true, $retry);
        } catch (Throwable) {
            // The cache is down as well: log every time rather than never.
        }

        if ($warn) {
            Log::warning('Artist pages unavailable, showing the last good copy if any: '.$e->getMessage());
        }

        $this->put($store, self::CACHE_KEY, ['version' => self::VERSION, 'failed' => true], $retry);
    }

    /**
     * Writes a cache entry; $seconds null = no expiry. Never throws.
     *
     * @param  array<string, mixed>  $value
     */
    private function put(?Repository $store, string $key, array $value, ?int $seconds): void
    {
        if ($store === null || ($seconds !== null && $seconds <= 0)) {
            return;
        }

        try {
            $seconds === null ? $store->forever($key, $value) : $store->put($key, $value, $seconds);
        } catch (Throwable $e) {
            Log::warning('Artist pages cache unwritable: '.$e->getMessage());
        }
    }

    private function store(): ?Repository
    {
        try {
            return Cache::store(config('cms.cache.store') ?: null);
        } catch (Throwable $e) {
            Log::warning('Artist pages cache store unusable: '.$e->getMessage());

            return null;
        }
    }

    /** An https URL (the only external links the pages print), else null. */
    private static function https(mixed $value): ?string
    {
        $url = self::text($value);

        // The CMS's checker (https, a host, no credentials, backslash or whitespace) when it exists.
        if ($url !== null && method_exists(SafeUrl::class, 'external')) {
            try {
                return SafeUrl::external($url);
            } catch (Throwable) {
                return null;
            }
        }

        return $url !== null && preg_match('#^https://[^\s/?\#]+#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false
            ? $url
            : null;
    }

    /** Trimmed text, or null when empty. */
    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function filled(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }

    private static function id(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** Unix time of a database timestamp, or null. */
    private static function time(mixed $value): ?int
    {
        $text = self::text($value);
        $time = $text === null ? false : strtotime($text);

        return $time === false ? null : $time;
    }

    /** Booleans as returned by SQLite (0/1), Postgres (true/false) or a stringifying driver ('t'/'f'). */
    private static function bool(mixed $value): bool
    {
        return is_string($value)
            ? in_array(strtolower($value), ['1', 't', 'true', 'y', 'yes', 'on'], true)
            : (bool) $value;
    }
}
