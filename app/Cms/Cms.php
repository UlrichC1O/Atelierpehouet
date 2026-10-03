<?php

namespace App\Cms;

use App\Support\ServiceCatalog;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The public read model of the CMS (docs/CMS.md §4.1, §13 A), a container singleton (helper cms()).
 *
 * Everything the public pages need from the database — text overrides, services, settings, the
 * metadata of every photo, photo spots and the list of published free pages — is read as one
 * snapshot (6 queries) and cached in config('cms.cache.store') for cms.cache.ttl seconds. Free-page
 * bodies are not in it: page() loads one body on demand (cached under its own key).
 *
 * Cached values hold plain arrays, strings, ints, bools and nulls only, with a "version" key: the
 * cache refuses to unserialize objects (cache.serializable_classes = false), and anything else read
 * back is a miss.
 *
 * The site must never break because of the CMS:
 *  - the database circuit breaker (DatabaseHealth) is checked before every read: while it is open,
 *    the database is skipped;
 *  - a failed load is remembered cms.cache.retry seconds (logged once per window);
 *  - stale-if-error: every successful load also stores a last-good copy (no expiry) that a failed
 *    refresh serves; only with no copy at all does the CMS answer as if it were empty, and the pages
 *    render with the defaults of the lang/ and content files. When that comes from an outage,
 *    blind() is true: URLs only the CMS knows answer 503 instead of 404 (EnsureCmsData).
 */
final class Cms
{
    /** Tables created by the CMS migrations (database/migrations/2026_10_03_0000NN_*). */
    public const TABLES = [
        'translation_overrides', 'media', 'media_files', 'media_slots', 'cms_services', 'settings', 'custom_pages', 'cms_activity',
    ];

    /** Format of the cached values: bump when their shape changes so other copies are ignored. */
    public const VERSION = 2;

    /** Settings copied into config('atelier.{prefix}.*') by applySettings(). */
    private const CONFIG_PREFIXES = ['contact', 'socials'];

    /** Parts of a snapshot, each an array. */
    private const PARTS = ['translations', 'services', 'settings', 'media', 'gallery', 'by_service', 'slots', 'pages'];

    private const EMPTY = [
        'version' => self::VERSION,
        'stamp' => '',
        'translations' => [],
        'services' => [],
        'settings' => [],
        'media' => [],
        'gallery' => [],
        'by_service' => [],
        'slots' => [],
        'pages' => [],
    ];

    /** Deepest nesting a cached value may have (service content is the deepest, ≈ 7). */
    private const MAX_DEPTH = 32;

    /** Columns of custom_pages in the snapshot (bodies are read on demand). */
    private const PAGE_COLUMNS = ['id', 'slug', 'title_fr', 'title_en', 'meta_fr', 'meta_en', 'cover_media_id', 'is_published',
        'in_footer', 'position', 'updated_at'];

    /** @var array<string, mixed>|null memoized snapshot of this request */
    private ?array $snapshot = null;

    /** Where the memoized snapshot came from: database, cache, lastgood or none (the files only). */
    private string $source = 'none';

    /** The data is missing because the database is unreachable (not merely empty or unmigrated). */
    private bool $outage = false;

    /** A page lookup of this request could not be answered (unreachable database, no cached copy). */
    private bool $unanswered = false;

    /** Logged-in admins read fresh data (no cache read). */
    private bool $fresh = false;

    /** Incremented each time the memoized snapshot is dropped (ServiceCatalog recomputes then). */
    private int $revision = 0;

    /** @var array<int, MediaItem> */
    private array $items = [];

    /** @var array<int, array{fr: string|null, en: string|null}|false|null> page id ⇒ body (false: page gone, null: unreadable) */
    private array $bodies = [];

    /** @var array<string, mixed> config key ⇒ value it had before applySettings() changed it */
    private array $applied = [];

    /**
     * Translation groups whose texts the CMS may override, in menu order ⇒ name of the route
     * previewing them (or null): config('cms.editable_groups'), plus "artists" once the artist
     * pages exist (docs/ARTISTS.md). Shared by the translation overlay and the texts editor.
     *
     * @return array<string, string|null>
     */
    public static function editableGroups(): array
    {
        $groups = [];

        foreach ((array) config('cms.editable_groups', []) as $group => $route) {
            $groups[(string) $group] = is_string($route) && $route !== '' ? $route : null;
        }

        if (! array_key_exists('artists', $groups) && is_file(lang_path('fr/artists.php'))) {
            $groups['artists'] = 'artists.index';
        }

        return $groups;
    }

    /** True when the snapshot came from the database (now or cached), false when a fallback is served. */
    public function available(): bool
    {
        $this->data();

        return $this->source === 'database' || $this->source === 'cache';
    }

    /** True when the last-good copy is served because the database could not be read. */
    public function stale(): bool
    {
        $this->data();

        return $this->source === 'lastgood';
    }

    /**
     * True when the database is unreachable and no copy of its data exists: the CMS cannot tell what
     * exists, so URLs only the CMS knows (free pages, created services, the sitemap) answer 503, not 404.
     */
    public function blind(): bool
    {
        $this->data();

        return ($this->source === 'none' && $this->outage) || $this->unanswered;
    }

    /** Logged-in admins always read fresh data (also drops the memoized snapshot). */
    public function bypassCache(): void
    {
        $this->fresh = true;
        $this->forget();
    }

    /**
     * Forgets what belongs to the previous request (memoized snapshot, admin bypass). Called at the
     * start of every request: tests and long-running workers serve several requests with one container.
     */
    public function reset(): void
    {
        $this->fresh = false;
        $this->forget();
    }

    /**
     * Forgets the cached snapshot and every in-process memo derived from the CMS data (this object's,
     * the loaded translation groups, the services catalog). Called after every CMS write; never throws.
     * The last-good copy is kept: it is only replaced by the next successful load.
     */
    public function flush(): void
    {
        try {
            $this->forget();
            app()->forgetInstance(ServiceCatalog::class);
            $this->store()?->forget($this->key());
        } catch (Throwable $e) {
            rescue(fn () => Log::warning('CMS cache could not be flushed: '.$e->getMessage()), null, false);
        }
    }

    /** Changes whenever the memoized snapshot is dropped: lets dependants drop what they derived from it. */
    public function revision(): int
    {
        return $this->revision;
    }

    /**
     * Text overrides of one translation group.
     *
     * @return array<string, string> dot key ⇒ value
     */
    public function translations(string $locale, string $group): array
    {
        return $this->data()['translations'][$locale][$group] ?? [];
    }

    /**
     * CMS rows of the services (overrides of the content files and services created in the CMS).
     *
     * @return array<string, array{custom: bool, published: bool, position: int|null, category: string|null, accent: string|null, icon: string|null, art_style: string|null, content: array{fr: array<string, mixed>, en: array<string, mixed>}}>
     */
    public function services(): array
    {
        return $this->data()['services'];
    }

    /** A setting: the row's value when a row exists (even ''), else $default. */
    public function setting(string $key, ?string $default = null): ?string
    {
        $settings = $this->settings();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * @return array<string, string|null>
     */
    public function settings(): array
    {
        return $this->data()['settings'];
    }

    /**
     * Copies the contact.* and socials.* settings over config('atelier.contact.*') and
     * config('atelier.socials.*') (a row wins even when empty: the detail is then hidden; a social
     * link that is not a valid https URL is hidden too). The address that receives the quote requests
     * falls back to the contact e-mail when empty. Values changed by an earlier call are restored
     * when their row disappears.
     */
    public function applySettings(): void
    {
        $values = [];

        foreach ($this->settings() as $key => $value) {
            [$prefix, $name] = array_pad(explode('.', (string) $key, 2), 2, '');

            if (! in_array($prefix, self::CONFIG_PREFIXES, true) || $name === ''
                || ! array_key_exists($name, (array) config('atelier.'.$prefix, []))) {
                continue;
            }

            $value = trim((string) $value);

            // Checked again here, not only when saved (rows edited elsewhere, older rows).
            if ($prefix === 'socials' && $value !== '') {
                $value = SafeUrl::external($value) ?? '';
            }

            $values['atelier.'.$prefix.'.'.$name] = $value;
        }

        foreach ($this->applied as $configKey => $original) {
            config([$configKey => $original]);
        }

        $this->applied = [];

        foreach ($values as $configKey => $value) {
            $this->setConfig($configKey, $value);
        }

        $notify = (string) config('atelier.contact.notify', '');
        $email = (string) config('atelier.contact.email', '');

        if (trim($notify) === '' && trim($email) !== '') {
            $this->setConfig('atelier.contact.notify', $email);
        }
    }

    /** Any photo of the library (the snapshot holds the metadata of every photo). */
    public function media(int $id): ?MediaItem
    {
        if (isset($this->items[$id])) {
            return $this->items[$id];
        }

        $row = $this->data()['media'][$id] ?? null;

        return is_array($row) ? $this->items[$id] = MediaItem::fromArray($row) : null;
    }

    /**
     * Photos of the public gallery (in_gallery), position ASC then newest first.
     *
     * @return list<MediaItem>
     */
    public function gallery(): array
    {
        return $this->itemsFor($this->data()['gallery']);
    }

    /**
     * Photos attached to a service ("Réalisations"), position ASC then newest first.
     *
     * @return list<MediaItem>
     */
    public function forService(string $slug): array
    {
        return $this->itemsFor($this->data()['by_service'][$slug] ?? []);
    }

    /** The photo filling a photo spot ("home.feature", "service.{slug}.cover"…), or null. */
    public function slot(string $slot): ?MediaItem
    {
        $id = $this->data()['slots'][$slot] ?? null;

        return is_int($id) ? $this->media($id) : null;
    }

    /**
     * Published free pages, ordered by position then French title — without their bodies (page()).
     *
     * @return list<array{id: int, slug: string, title: array{fr: string, en: string|null}, meta: array{fr: string|null, en: string|null}, cover: int|null, in_footer: bool, position: int, updated_at: string|null}>
     */
    public function pages(): array
    {
        return $this->data()['pages'];
    }

    /**
     * One published free page with its body (['fr' => ?string, 'en' => ?string]), or null. The body
     * is read on demand and cached like the snapshot; when it cannot be read (unreachable database,
     * no cached copy) the answer is null and blind() becomes true.
     *
     * @return array{id: int, slug: string, title: array{fr: string, en: string|null}, body: array{fr: string|null, en: string|null}, meta: array{fr: string|null, en: string|null}, cover: int|null, in_footer: bool, position: int, updated_at: string|null}|null
     */
    public function page(string $slug): ?array
    {
        foreach ($this->pages() as $page) {
            if ($page['slug'] !== $slug) {
                continue;
            }

            $body = $this->body($page);

            if ($body === null) {
                $this->unanswered = true;
            }

            return is_array($body) ? $page + ['body' => $body] : null;
        }

        return null;
    }

    /**
     * Any free page, published or not, read from the database for a logged-in admin's preview
     * ('published' => bool added), or null when unknown — or unreadable (blind() then true).
     *
     * @return array<string, mixed>|null
     */
    public function preview(string $slug): ?array
    {
        $health = $this->health();

        if ($health->tripped()) {
            $this->unanswered = true;

            return null;
        }

        try {
            $row = DB::connection()->table('custom_pages')->where('slug', $slug)
                ->first([...self::PAGE_COLUMNS, 'body_fr', 'body_en']);
        } catch (Throwable $e) {
            $health->failed($e);
            $this->unanswered = $this->unanswered || DatabaseHealth::isOutage($e);

            return null;
        }

        if ($row === null) {
            return null;
        }

        return self::pageFromRow($row, null) + [
            'body' => ['fr' => self::text($row->body_fr), 'en' => self::text($row->body_en)],
            'published' => self::bool($row->is_published),
        ];
    }

    /**
     * Published free pages listed in the footer.
     *
     * @return list<array<string, mixed>>
     */
    public function footerPages(): array
    {
        return array_values(array_filter($this->pages(), fn (array $page): bool => $page['in_footer']));
    }

    /**
     * CMS tables missing from the database (one query). Throws when the database is unreachable.
     *
     * @return list<string>
     */
    public function missingTables(): array
    {
        $tables = array_map('strtolower', Schema::getTableListing(Schema::getCurrentSchemaListing(), false));

        return array_values(array_diff(self::TABLES, $tables));
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        return $this->snapshot ??= $this->resolve();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolve(): array
    {
        $store = $this->store();
        $health = $this->health();
        $this->outage = false;

        if (! $this->fresh) {
            $cached = $this->read($store, $this->key());

            if ($cached !== null && ! isset($cached['failed'])) {
                $this->source = 'cache';

                return $cached;
            }

            if ($cached !== null) {
                $this->outage = $cached['outage'] ?? false;

                return $this->fallback($store);
            }
        }

        // Visitors respect the breaker; an admin (fresh data) only skips the database after a failure
        // met in this very request.
        if ($this->fresh ? $health->tripped() : ! $health->available()) {
            $this->outage = true;

            return $this->fallback($store);
        }

        try {
            $snapshot = $this->load();
        } catch (Throwable $e) {
            $health->failed($e);
            $this->outage = DatabaseHealth::isOutage($e);
            $this->failed($store, $e);

            return $this->fallback($store);
        }

        if ($this->fresh && ! $health->available()) {
            $health->recovered();
        }

        $this->source = 'database';
        $this->put($store, $this->key(), $snapshot);
        $this->put($store, $this->key().'.lastgood', $snapshot, forever: true);

        return $snapshot;
    }

    /**
     * The last-good copy, or the empty snapshot (the files only).
     *
     * @return array<string, mixed>
     */
    private function fallback(?Repository $store): array
    {
        $copy = $this->read($store, $this->key().'.lastgood');

        if ($copy !== null && ! isset($copy['failed'])) {
            $this->source = 'lastgood';

            return $copy;
        }

        $this->source = 'none';

        return self::EMPTY;
    }

    /**
     * Reads the snapshot from the database (6 queries).
     *
     * @return array<string, mixed>
     */
    private function load(): array
    {
        $db = DB::connection();

        $translations = [];

        foreach ($db->table('translation_overrides')->get(['locale', 'group', 'key', 'value']) as $row) {
            $translations[(string) $row->locale][(string) $row->group][(string) $row->key] = (string) $row->value;
        }

        $services = [];

        foreach ($db->table('cms_services')->orderBy('id')->get(['slug', 'is_custom', 'is_published', 'position', 'category', 'accent', 'icon', 'art_style', 'content']) as $row) {
            $content = is_string($row->content) ? json_decode($row->content, true) : null;
            $content = is_array($content) ? $content : [];

            $services[(string) $row->slug] = [
                'custom' => self::bool($row->is_custom),
                'published' => self::bool($row->is_published),
                'position' => $row->position === null ? null : (int) $row->position,
                'category' => self::text($row->category),
                'accent' => self::text($row->accent),
                'icon' => self::text($row->icon),
                'art_style' => self::text($row->art_style),
                'content' => [
                    'fr' => is_array($content['fr'] ?? null) ? $content['fr'] : [],
                    'en' => is_array($content['en'] ?? null) ? $content['en'] : [],
                ],
            ];
        }

        $settings = [];

        foreach ($db->table('settings')->get(['key', 'value']) as $row) {
            $settings[(string) $row->key] = $row->value === null ? null : (string) $row->value;
        }

        $media = $gallery = $byService = [];
        $columns = ['id', 'ulid', 'mime', 'extension', 'width', 'height', 'size', 'original_name', 'variants', 'alt_fr', 'alt_en',
            'caption_fr', 'caption_en', 'focal_x', 'focal_y', 'service_slug', 'in_gallery', 'position', 'updated_at'];

        foreach ($db->table('media')->orderBy('position')->orderByDesc('id')->get($columns) as $row) {
            $item = MediaItem::fromArray((array) $row)->toSnapshot();
            $media[$item['id']] = $item;

            if ($item['in_gallery']) {
                $gallery[] = $item['id'];
            }

            if ($item['service_slug'] !== null) {
                $byService[$item['service_slug']][] = $item['id'];
            }
        }

        $slots = [];

        foreach ($db->table('media_slots')->get(['slot', 'media_id']) as $row) {
            if (isset($media[(int) $row->media_id])) {
                $slots[(string) $row->slot] = (int) $row->media_id;
            }
        }

        $pages = [];

        // Published pages only; filtered here rather than in SQL (boolean literals differ between drivers).
        foreach ($db->table('custom_pages')->orderBy('position')->orderBy('title_fr')->orderBy('id')->get(self::PAGE_COLUMNS) as $row) {
            if (self::bool($row->is_published)) {
                $pages[] = self::pageFromRow($row, $media);
            }
        }

        return [
            'version' => self::VERSION,
            // Identifies this load: free-page bodies are cached per load, so a write (which drops the
            // snapshot) or the snapshot's expiry also renews the bodies.
            'stamp' => bin2hex(random_bytes(8)),
            'translations' => $translations,
            'services' => $services,
            'settings' => $settings,
            'media' => $media,
            'gallery' => $gallery,
            'by_service' => $byService,
            'slots' => $slots,
            'pages' => $pages,
        ];
    }

    /**
     * A free page as listed in the snapshot (no body).
     *
     * @param  array<int, mixed>|null  $media  photos of the snapshot (a missing cover is dropped); null = keep the cover id
     * @return array<string, mixed>
     */
    private static function pageFromRow(object $row, ?array $media): array
    {
        $cover = $row->cover_media_id === null ? null : (int) $row->cover_media_id;

        return [
            'id' => (int) $row->id,
            'slug' => (string) $row->slug,
            'title' => ['fr' => (string) $row->title_fr, 'en' => self::text($row->title_en)],
            'meta' => ['fr' => self::text($row->meta_fr), 'en' => self::text($row->meta_en)],
            'cover' => $cover !== null && ($media === null || isset($media[$cover])) ? $cover : null,
            'in_footer' => self::bool($row->in_footer),
            'position' => (int) $row->position,
            'updated_at' => self::date($row->updated_at),
        ];
    }

    /**
     * The body of a listed page: cached per snapshot load, read from the database on a miss, the
     * last-good copy when that fails.
     *
     * @param  array<string, mixed>  $page
     * @return array{fr: string|null, en: string|null}|false|null false: the page no longer exists, null: unreadable now
     */
    private function body(array $page): array|false|null
    {
        $id = (int) $page['id'];

        if (array_key_exists($id, $this->bodies)) {
            return $this->bodies[$id];
        }

        $store = $this->store();
        $key = $this->key().'.page.'.$id;
        $current = $key.'.'.$this->data()['stamp'];

        if (! $this->fresh && ($cached = $this->readBody($store, $current)) !== null) {
            return $this->bodies[$id] = $cached;
        }

        $health = $this->health();

        if ($this->fresh ? ! $health->tripped() : $health->available()) {
            try {
                $row = DB::connection()->table('custom_pages')->where('id', $id)->first(['body_fr', 'body_en']);

                if ($row === null) {
                    return $this->bodies[$id] = false;
                }

                $body = ['fr' => self::text($row->body_fr), 'en' => self::text($row->body_en)];
                $this->put($store, $current, ['version' => self::VERSION] + $body);
                $this->put($store, $key.'.lastgood', ['version' => self::VERSION] + $body, forever: true);

                return $this->bodies[$id] = $body;
            } catch (Throwable $e) {
                $health->failed($e);
            }
        }

        return $this->bodies[$id] = $this->readBody($store, $key.'.lastgood');
    }

    /**
     * @return array{fr: string|null, en: string|null}|null
     */
    private function readBody(?Repository $store, string $key): ?array
    {
        $value = $this->get($store, $key);

        if (! is_array($value) || ($value['version'] ?? null) !== self::VERSION) {
            return null;
        }

        $fr = $value['fr'] ?? null;
        $en = $value['en'] ?? null;

        return ($fr === null || is_string($fr)) && ($en === null || is_string($en)) ? ['fr' => $fr, 'en' => $en] : null;
    }

    /**
     * A cached snapshot or failed marker of this version, made of plain values only — anything else
     * is a miss (null). A failed marker comes back as ['version', 'failed' => true, 'outage' => bool].
     *
     * @return array<string, mixed>|null
     */
    private function read(?Repository $store, string $key): ?array
    {
        $value = $this->get($store, $key);

        if (! is_array($value) || ($value['version'] ?? null) !== self::VERSION) {
            return null;
        }

        if (($value['failed'] ?? null) === true) {
            return ['version' => self::VERSION, 'failed' => true, 'outage' => ($value['outage'] ?? false) === true];
        }

        if (! is_string($value['stamp'] ?? null)) {
            return null;
        }

        foreach (self::PARTS as $part) {
            if (! is_array($value[$part] ?? null)) {
                return null;
            }
        }

        return self::plain($value, 0) ? $value : null;
    }

    private function get(?Repository $store, string $key): mixed
    {
        if ($store === null) {
            return null;
        }

        try {
            return $store->get($key);
        } catch (Throwable $e) {
            Log::warning('CMS cache unreadable, reading the database: '.$e->getMessage());

            return null;
        }
    }

    /** Logs the failure once per retry window and remembers it for cms.cache.retry seconds. */
    private function failed(?Repository $store, Throwable $e): void
    {
        $retry = (int) config('cms.cache.retry', 30);
        $warn = true;

        try {
            $warn = $store === null || $retry <= 0 || $store->add($this->key().'.warned', true, $retry);
        } catch (Throwable) {
            // The cache is down as well: log every time rather than never.
        }

        if ($warn) {
            Log::warning('CMS data unavailable, the site uses '.($this->read($store, $this->key().'.lastgood') !== null ? 'its last-good copy' : 'the file defaults').': '.$e->getMessage());
        }

        if ($retry > 0) {
            $this->write($store, $this->key(), ['version' => self::VERSION, 'failed' => true, 'outage' => $this->outage], $retry);
        }
    }

    /**
     * Caches a value for cms.cache.ttl seconds, or forever (the last-good copies). A zero lifetime
     * disables the CMS cache altogether.
     *
     * @param  array<string, mixed>  $value
     */
    private function put(?Repository $store, string $key, array $value, bool $forever = false): void
    {
        $ttl = (int) config('cms.cache.ttl', 600);

        if ($ttl > 0) {
            $this->write($store, $key, $value, $forever ? null : $ttl);
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function write(?Repository $store, string $key, array $value, ?int $seconds): void
    {
        if ($store === null) {
            return;
        }

        try {
            $seconds === null ? $store->forever($key, $value) : $store->put($key, $value, $seconds);
        } catch (Throwable $e) {
            Log::warning('CMS cache unwritable: '.$e->getMessage());
        }
    }

    private function store(): ?Repository
    {
        try {
            return Cache::store(config('cms.cache.store') ?: null);
        } catch (Throwable $e) {
            Log::warning('CMS cache store unusable: '.$e->getMessage());

            return null;
        }
    }

    private function health(): DatabaseHealth
    {
        return app(DatabaseHealth::class);
    }

    private function key(): string
    {
        return (string) config('cms.cache.key', 'cms.snapshot.v1');
    }

    private function forget(): void
    {
        $this->snapshot = null;
        $this->source = 'none';
        $this->outage = false;
        $this->unanswered = false;
        $this->items = [];
        $this->bodies = [];
        $this->revision++;

        // Translation groups already loaded hold the old overrides.
        if (app()->resolved('translator')) {
            app('translator')->setLoaded([]);
        }
    }

    private function setConfig(string $key, mixed $value): void
    {
        if (! array_key_exists($key, $this->applied)) {
            $this->applied[$key] = config($key);
        }

        config([$key => $value]);
    }

    /**
     * @param  list<int>  $ids
     * @return list<MediaItem>
     */
    private function itemsFor(array $ids): array
    {
        $items = [];

        foreach ($ids as $id) {
            if (($item = $this->media((int) $id)) !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /** Arrays of strings, ints, floats, bools and nulls only (no object, however deep). */
    private static function plain(mixed $value, int $depth): bool
    {
        if (! is_array($value)) {
            return $value === null || is_string($value) || is_int($value) || is_bool($value) || is_float($value);
        }

        if ($depth >= self::MAX_DEPTH) {
            return false;
        }

        foreach ($value as $item) {
            if (! self::plain($item, $depth + 1)) {
                return false;
            }
        }

        return true;
    }

    /** Booleans as returned by SQLite (0/1), Postgres (true/false) or a stringifying driver ('t'/'f'). */
    private static function bool(mixed $value): bool
    {
        return is_string($value)
            ? in_array(strtolower($value), ['1', 't', 'true', 'y', 'yes', 'on'], true)
            : (bool) $value;
    }

    private static function text(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /** A database timestamp as an ISO 8601 string (null when empty or unreadable). */
    private static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
