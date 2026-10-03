<?php

namespace App\Artists;

use App\Cms\Activity;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Exhibition;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The reference artist (docs/ARTISTS.md §4.4, §8): a complete, fictional, unpublished artist page,
 * flagged "example", that shows the owner a finished page to edit or delete.
 *
 * Its content is resources/content/artists/example.php; its images are resources/content/artists/
 * example/{file}.webp with the responsive variants {file}-{width}.webp. Each image goes into the CMS
 * photo library through MediaManager::store() — an image that is missing or refused is skipped, and
 * without the photo library the texts are still created. The rows are written in one transaction;
 * the photos already stored are deleted again when it fails.
 */
final class ExampleArtist
{
    /** Suffix of the slug when the example's own slug is already taken. */
    public const SLUG_SUFFIX = 'exemple';

    /** Image names of the content file: letters, digits, "-" and "_" only (no paths). */
    private const FILE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,80}$/';

    private readonly string $directory;

    /**
     * @param  string|null  $directory  holds example.php and example/ (default: resources/content/artists)
     */
    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? resource_path('content/artists'), '/\\');
    }

    /** True when an example artist exists (false when the database cannot tell). */
    public function exists(): bool
    {
        try {
            return $this->existing() !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The example artist: the existing one, else a new one imported from the content file.
     *
     * @throws RuntimeException when the content file is missing or invalid
     * @throws Throwable when the rows cannot be written (nothing is kept then)
     */
    public function create(?int $userId = null): Artist
    {
        if (($existing = $this->existing()) !== null) {
            return $existing;
        }

        $content = $this->content();
        $artist = $content['artist'];
        $name = self::text($artist['name'] ?? null, 120) ?? '';

        /** @var list<int> $stored photos written by this import */
        $stored = [];

        $portrait = $this->photo($artist['portrait'] ?? null, $userId, $stored);
        $artworks = [];
        $exhibitions = [];

        foreach (self::entries($content['artworks'] ?? []) as $entry) {
            $artworks[] = [$entry, $this->photo($entry, $userId, $stored)];
        }

        foreach (self::entries($content['exhibitions'] ?? []) as $entry) {
            $exhibitions[] = [$entry, $this->photo($entry, $userId, $stored)];
        }

        try {
            $model = DB::transaction(function () use ($artist, $name, $portrait, $artworks, $exhibitions, $userId): Artist {
                $model = Artist::query()->create([
                    'slug' => $this->slug(is_string($artist['slug'] ?? null) ? $artist['slug'] : $name, $name),
                    'name' => $name,
                    'discipline_fr' => self::text($artist['fr']['discipline'] ?? null, 120),
                    'discipline_en' => self::text($artist['en']['discipline'] ?? null, 120),
                    'location' => self::text($artist['location'] ?? null, 120),
                    'statement_fr' => self::text($artist['fr']['statement'] ?? null, 400),
                    'statement_en' => self::text($artist['en']['statement'] ?? null, 400),
                    'bio_fr' => self::text($artist['fr']['bio'] ?? null, 10000),
                    'bio_en' => self::text($artist['en']['bio'] ?? null, 10000),
                    'meta_fr' => self::text($artist['fr']['meta'] ?? null, 170),
                    'meta_en' => self::text($artist['en']['meta'] ?? null, 170),
                    'portrait_media_id' => $portrait,
                    'accent' => in_array($artist['accent'] ?? null, (array) config('atelier.accents', []), true) ? $artist['accent'] : Artist::DEFAULT_ACCENT,
                    'website' => self::https($artist['website'] ?? null),
                    'instagram' => self::https($artist['instagram'] ?? null),
                    'is_published' => false,
                    'is_example' => true,
                    'position' => (int) Artist::query()->max('position') + 1,
                    'updated_by' => $userId,
                ]);

                foreach ($artworks as $index => [$entry, $mediaId]) {
                    Artwork::query()->create([
                        'artist_id' => $model->id,
                        'media_id' => $mediaId,
                        'title_fr' => self::text($entry['fr']['title'] ?? null, 160) ?? (string) __('admin_artists.artworks.untitled'),
                        'title_en' => self::text($entry['en']['title'] ?? null, 160),
                        'year' => self::text($entry['year'] ?? null, 20),
                        'medium_fr' => self::text($entry['fr']['medium'] ?? null, 160),
                        'medium_en' => self::text($entry['en']['medium'] ?? null, 160),
                        'dimensions' => self::text($entry['dimensions'] ?? null, 80),
                        'description_fr' => self::text($entry['fr']['description'] ?? null, 1500),
                        'description_en' => self::text($entry['en']['description'] ?? null, 1500),
                        'availability' => in_array($entry['availability'] ?? null, Artwork::AVAILABILITIES, true) ? $entry['availability'] : 'none',
                        'is_published' => true,
                        'position' => $index + 1,
                    ]);
                }

                $today = CarbonImmutable::today();

                foreach ($exhibitions as [$entry, $mediaId]) {
                    $starts = self::day($today, $entry['starts'] ?? null);
                    $ends = $starts === null ? null : self::day($today, $entry['ends'] ?? null);
                    $ends = $ends !== null && $ends->lessThan($starts) ? null : $ends;
                    $yearsAgo = is_numeric($entry['years_ago'] ?? null) ? max(0, (int) $entry['years_ago']) : 0;

                    Exhibition::query()->create([
                        'artist_id' => $model->id,
                        'media_id' => $mediaId,
                        'title_fr' => self::text($entry['fr']['title'] ?? null, 160) ?? $name,
                        'title_en' => self::text($entry['en']['title'] ?? null, 160),
                        'kind' => in_array($entry['kind'] ?? null, Exhibition::KINDS, true) ? $entry['kind'] : 'other',
                        'venue' => self::text($entry['venue'] ?? null, 160),
                        'city' => self::text($entry['city'] ?? null, 120),
                        'year' => $starts?->year ?? $today->year - $yearsAgo,
                        'starts_on' => $starts?->format('Y-m-d'),
                        'ends_on' => $ends?->format('Y-m-d'),
                        'description_fr' => self::text($entry['fr']['description'] ?? null, 1500),
                        'description_en' => self::text($entry['en']['description'] ?? null, 1500),
                        'url' => self::https($entry['url'] ?? null),
                        'is_published' => true,
                    ]);
                }

                return $model;
            });
        } catch (Throwable $e) {
            Photos::delete($stored);

            throw $e;
        }

        if (class_exists(Activity::class)) {
            Activity::record('artists.example', (string) __('admin_artists.activity.example', ['name' => $model->name]), 'artist:'.$model->id);
        }

        return $model;
    }

    /** The existing example artist (the oldest one), or null. */
    private function existing(): ?Artist
    {
        return Artist::query()->where('is_example', true)->orderBy('id')->first();
    }

    /**
     * The content file (§8), checked for the parts every import needs.
     *
     * @return array{artist: array<string, mixed>, artworks?: mixed, exhibitions?: mixed}
     *
     * @throws RuntimeException
     */
    private function content(): array
    {
        $file = $this->directory.'/example.php';

        if (! is_file($file)) {
            throw new RuntimeException('The example artist content file is missing: '.$file);
        }

        $content = (static fn (string $path): mixed => require $path)($file);

        if (! is_array($content) || ! is_array($content['artist'] ?? null) || self::text($content['artist']['name'] ?? null, 120) === null) {
            throw new RuntimeException('The example artist content file is invalid (an "artist" with a "name" is required): '.$file);
        }

        foreach (['fr', 'en'] as $locale) {
            $content['artist'][$locale] = is_array($content['artist'][$locale] ?? null) ? $content['artist'][$locale] : [];
        }

        return $content;
    }

    /**
     * The entries of a list of the content file, each with its "fr" and "en" blocks as arrays.
     *
     * @return list<array<string, mixed>>
     */
    private static function entries(mixed $list): array
    {
        $entries = [];

        foreach (is_array($list) ? $list : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach (['fr', 'en'] as $locale) {
                $entry[$locale] = is_array($entry[$locale] ?? null) ? $entry[$locale] : [];
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Stores the image of an entry ({file}.webp + its variants) in the photo library; null when there is
     * no image, no library, or the image is refused (logged).
     *
     * @param  list<int>  $stored  receives the id of the stored photo
     */
    private function photo(mixed $entry, ?int $userId, array &$stored): ?int
    {
        $file = is_array($entry) ? ($entry['file'] ?? null) : null;

        if (! is_string($file) || preg_match(self::FILE_PATTERN, $file) !== 1) {
            return null;
        }

        $path = $this->directory.'/example/'.$file.'.webp';

        if (! is_file($path) || ($manager = Photos::manager()) === null) {
            return null;
        }

        $variants = [];

        foreach ((array) config('cms.media.widths', []) as $width) {
            $variant = $this->directory.'/example/'.$file.'-'.(int) $width.'.webp';

            if (is_file($variant)) {
                $variants[(int) $width] = new UploadedFile($variant, basename($variant), 'image/webp', null, true);
            }
        }

        try {
            $media = $manager->store(new UploadedFile($path, $file.'.webp', 'image/webp', null, true), $variants, [
                'alt_fr' => self::text($entry['alt']['fr'] ?? $entry['fr']['title'] ?? null, 300),
                'alt_en' => self::text($entry['alt']['en'] ?? $entry['en']['title'] ?? null, 300),
                'original_name' => $file.'.webp',
            ], $userId);
        } catch (Throwable $e) {
            Log::warning('Example artist: image '.$file.' skipped: '.$e->getMessage());

            return null;
        }

        $stored[] = (int) $media->getKey();

        return (int) $media->getKey();
    }

    /** A free slug: the wanted one, else "{slug}-exemple", "{slug}-exemple-2"… */
    private function slug(string $wanted, string $name): string
    {
        $base = self::slugify($wanted);
        $base = $base !== '' ? $base : self::slugify($name);
        $base = $base !== '' ? $base : self::SLUG_SUFFIX;

        if (! $this->taken($base)) {
            return $base;
        }

        for ($n = 1; ; $n++) {
            $suffix = '-'.self::SLUG_SUFFIX.($n > 1 ? '-'.$n : '');
            $candidate = rtrim(substr($base, 0, Artist::SLUG_MAX - strlen($suffix)), '-').$suffix;

            if (! $this->taken($candidate)) {
                return $candidate;
            }
        }
    }

    private function taken(string $slug): bool
    {
        return Artist::query()->where('slug', $slug)->exists();
    }

    private static function slugify(string $value): string
    {
        return trim(substr(Str::slug($value), 0, Artist::SLUG_MAX), '-');
    }

    /** Today moved by a relative modifier ("-20 days", "+2 months"…), or null. */
    private static function day(CarbonImmutable $today, mixed $modifier): ?CarbonImmutable
    {
        if (! is_string($modifier) || trim($modifier) === '') {
            return null;
        }

        try {
            $day = $today->modify($modifier);
        } catch (Throwable) {
            return null;
        }

        return $day instanceof CarbonInterface ? CarbonImmutable::instance($day)->startOfDay() : null;
    }

    /** Trimmed text with "\n" line endings, cut to $max characters; null when empty. */
    private static function text(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private static function https(mixed $value): ?string
    {
        $url = self::text($value, 255);

        return $url !== null && str_starts_with(strtolower($url), 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }
}
