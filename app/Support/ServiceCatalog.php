<?php

namespace App\Support;

use App\Cms\Cms;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * The services of the atelier, read from resources/content/services/{slug}.php
 * (docs/ARCHITECTURE.md §4) with the CMS edits laid over them (docs/CMS.md §4.4), served as
 * localized arrays.
 *
 * A localized service array holds the meta keys
 *   slug, order, number ('01'… = display position), category, category_label, accent, icon, art_style,
 *   scene, url, published, custom, modified
 * followed by the content keys of the requested locale
 *   title, short, tagline, intro, body, features, process, ideal_for, faq, scene_alt, meta_description
 * Missing or empty content keys of a locale fall back to French.
 *
 * CMS rows (Cms::services()) override a file's content leaves, meta (when valid), order and
 * visibility; rows flagged "custom" whose slug has no file are services created in the CMS (their
 * scene is the generic one). Hidden services are left out unless the instance is withHidden() (admin).
 *
 * Registered as a container singleton; files are read once per instance, the overlay is recomputed
 * whenever the CMS snapshot changes.
 *
 * @phpstan-type Service array<string, mixed>
 */
final class ServiceCatalog
{
    /** Keys every content file defines next to its "fr"/"en" blocks. */
    public const META_KEYS = ['slug', 'order', 'category', 'accent', 'icon', 'art_style', 'scene'];

    /** Keys of each locale block. */
    public const CONTENT_KEYS = [
        'title', 'short', 'tagline', 'intro', 'body', 'features', 'process',
        'ideal_for', 'faq', 'scene_alt', 'meta_description',
    ];

    /** Content keys holding lists (default to [] instead of ''). */
    private const LIST_KEYS = ['body', 'features', 'process', 'ideal_for', 'faq'];

    private const FALLBACK_LOCALE = 'fr';

    /** Slugs of services created in the CMS (and their URLs). */
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Icon and art style of a CMS-created service that has none. */
    private const CUSTOM_ICON = 'triangle';

    private const CUSTOM_ART_STYLE = 'pehouet';

    /** Highest list index an override may address (a list never grows beyond it). */
    private const MAX_LIST_ITEMS = 12;

    /** Fields of the items of the lists made of pairs. */
    private const ITEM_FIELDS = ['features' => ['title', 'text'], 'process' => ['title', 'text'], 'faq' => ['q', 'a']];

    private readonly string $directory;

    /** @var array<string, array<string, mixed>>|null raw content files keyed by slug, sorted by order */
    private ?array $files = null;

    /** @var array<string, string> content file path keyed by slug */
    private array $paths = [];

    /** @var array<string, array<string, mixed>>|null every service with the CMS overlay (hidden ones included), sorted */
    private ?array $services = null;

    /** @var array<string, array<string, mixed>>|null the services this instance exposes */
    private ?array $raw = null;

    /** @var array<string, list<array<string, mixed>>> localized services keyed by locale */
    private array $localized = [];

    /** CMS snapshot revision the overlay was computed for. */
    private ?int $revision = null;

    private ?self $hidden = null;

    public function __construct(
        ?string $directory = null,
        private readonly ?Cms $cms = null,
        private readonly bool $withHidden = false,
    ) {
        $this->directory = rtrim($directory ?? resource_path('content/services'), '/');
    }

    /** The same catalog including the hidden services (admin screens, previews, photo spots). */
    public function withHidden(): self
    {
        return $this->withHidden ? $this : $this->hidden ??= new self($this->directory, $this->cms, true);
    }

    /** Directory the content files are read from. */
    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Every service, ordered.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function all(?string $locale = null): Collection
    {
        return new Collection($this->localizedList($locale));
    }

    /**
     * One service, or null when the slug is unknown (or hidden, unless withHidden()).
     *
     * @return array<string, mixed>|null
     */
    public function find(string $slug, ?string $locale = null): ?array
    {
        foreach ($this->localizedList($locale) as $service) {
            if ($service['slug'] === $slug) {
                return $service;
            }
        }

        return null;
    }

    public function has(string $slug): bool
    {
        return array_key_exists($slug, $this->raw());
    }

    /**
     * Service slugs in display order.
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->raw());
    }

    public function count(): int
    {
        return count($this->raw());
    }

    /** Absolute path of a service's content file (null when unknown or created in the CMS). */
    public function path(string $slug): ?string
    {
        $this->files();

        return $this->paths[$slug] ?? null;
    }

    /**
     * A service's content file as written (no CMS overlay), or null when it has no file.
     *
     * @return array<string, mixed>|null
     */
    public function fileData(string $slug): ?array
    {
        return $this->files()[$slug] ?? null;
    }

    /** True for a service created in the CMS (no content file), hidden or not. */
    public function isCustom(string $slug): bool
    {
        return ($this->services()[$slug]['custom'] ?? false) === true;
    }

    /**
     * Category labels in display order (config('atelier.categories')).
     *
     * @return array<string, string> category key ⇒ localized label
     */
    public function categories(?string $locale = null): array
    {
        $labels = [];

        foreach (array_keys((array) config('atelier.categories', [])) as $key) {
            $labels[$key] = $this->categoryLabel((string) $key, $this->locale($locale));
        }

        return $labels;
    }

    /**
     * Services grouped by category, categories in config order (empty ones omitted,
     * unknown ones last).
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public function byCategory(?string $locale = null): Collection
    {
        $groups = [];

        foreach (array_keys((array) config('atelier.categories', [])) as $key) {
            $groups[(string) $key] = [];
        }

        foreach ($this->localizedList($locale) as $service) {
            $groups[$service['category']][] = $service;
        }

        return (new Collection($groups))
            ->filter(fn (array $services): bool => $services !== [])
            ->map(fn (array $services): Collection => new Collection($services));
    }

    /**
     * Previous and next services (wrapping around), or null when the slug is unknown.
     *
     * @return array{prev: array<string, mixed>, next: array<string, mixed>}|null
     */
    public function neighbors(string $slug, ?string $locale = null): ?array
    {
        $list = $this->localizedList($locale);
        $index = $this->indexOf($list, $slug);

        if ($index === null) {
            return null;
        }

        $count = count($list);

        return [
            'prev' => $list[($index - 1 + $count) % $count],
            'next' => $list[($index + 1) % $count],
        ];
    }

    /**
     * Services to suggest next to $slug: same category first, then the others,
     * each group in catalog order starting right after $slug (wrapping around).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function related(string $slug, int $limit = 3, ?string $locale = null): Collection
    {
        $list = $this->localizedList($locale);
        $index = $this->indexOf($list, $slug);

        if ($index === null || $limit < 1) {
            return new Collection;
        }

        $count = count($list);
        $category = $list[$index]['category'];
        $candidates = [];

        for ($step = 1; $step < $count; $step++) {
            $candidates[] = $list[($index + $step) % $count];
        }

        // usort is stable: the cyclic order is kept inside each group.
        usort($candidates, fn (array $a, array $b): int => ($a['category'] === $category ? 0 : 1) <=> ($b['category'] === $category ? 0 : 1));

        return new Collection(array_slice($candidates, 0, $limit));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function localizedList(?string $locale): array
    {
        $locale = $this->locale($locale);
        $raw = $this->raw();

        if (! isset($this->localized[$locale])) {
            $list = [];

            foreach (array_values($raw) as $index => $service) {
                $list[] = $this->localize($service, $locale, $index + 1);
            }

            $this->localized[$locale] = $list;
        }

        return $this->localized[$locale];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function localize(array $raw, string $locale, int $position): array
    {
        $fallback = is_array($raw[self::FALLBACK_LOCALE] ?? null) ? $raw[self::FALLBACK_LOCALE] : [];
        $own = is_array($raw[$locale] ?? null) ? $raw[$locale] : [];

        $content = [];

        foreach (array_unique([...self::CONTENT_KEYS, ...array_keys($fallback), ...array_keys($own)]) as $key) {
            $value = $own[$key] ?? null;

            if (blank($value)) {
                $value = $fallback[$key] ?? null;
            }

            $content[$key] = $value ?? (in_array($key, self::LIST_KEYS, true) ? [] : '');
        }

        $slug = $raw['slug'];

        $meta = [
            'slug' => $slug,
            'order' => $raw['order'],
            'number' => str_pad((string) $position, 2, '0', STR_PAD_LEFT),
            'category' => $raw['category'],
            'category_label' => $this->categoryLabel($raw['category'], $locale),
            'accent' => $raw['accent'],
            'icon' => $raw['icon'],
            'art_style' => $raw['art_style'],
            'scene' => $raw['scene'],
            'url' => Route::has('services.show') ? route('services.show', ['slug' => $slug]) : url('services/'.$slug),
            'published' => $raw['published'],
            'custom' => $raw['custom'],
            'modified' => $raw['modified'],
        ];

        return $meta + $content;
    }

    /**
     * The services this instance exposes, keyed by slug, in display order.
     *
     * @return array<string, array<string, mixed>>
     */
    private function raw(): array
    {
        $services = $this->services();

        return $this->raw ??= $this->withHidden
            ? $services
            : array_filter($services, fn (array $service): bool => $service['published']);
    }

    /**
     * Every service with the CMS overlay, keyed by slug, sorted by order then slug.
     *
     * @return array<string, array<string, mixed>>
     */
    private function services(): array
    {
        $revision = $this->cms?->revision();

        if ($revision !== $this->revision) {
            $this->revision = $revision;
            $this->services = $this->raw = null;
            $this->localized = [];
        }

        if ($this->services !== null) {
            return $this->services;
        }

        $services = [];

        foreach ($this->files() as $slug => $file) {
            $services[$slug] = $file + ['published' => true, 'custom' => false, 'modified' => false];
        }

        $rows = [];

        try {
            $rows = $this->cms?->services() ?? [];
        } catch (Throwable $e) {
            Log::warning('Service edits of the CMS ignored: '.$e->getMessage());
        }

        $unordered = [];

        foreach ($rows as $slug => $row) {
            $slug = (string) $slug;

            if (isset($services[$slug])) {
                $services[$slug] = $this->applyRow($services[$slug], $row);
            } elseif (($row['custom'] ?? false) === true) {
                $custom = $this->customService($slug, $row);

                if ($custom !== null) {
                    $services[$slug] = $custom;

                    if ($custom['order'] === null) {
                        $unordered[] = $slug;
                    }
                }
            }
        }

        // CMS-created services without a position come last, alphabetically.
        $next = max([0, ...array_filter(array_column($services, 'order'), 'is_int')]) + 1;
        sort($unordered);

        foreach ($unordered as $slug) {
            $services[$slug]['order'] = $next++;
        }

        uasort($services, fn (array $a, array $b): int => [$a['order'], $a['slug']] <=> [$b['order'], $b['slug']]);

        return $this->services = $services;
    }

    /**
     * A file service with its CMS row applied: content leaves, valid meta, order, visibility.
     *
     * @param  array<string, mixed>  $service
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyRow(array $service, array $row): array
    {
        $modified = false;

        foreach ($this->locales() as $locale) {
            $override = $row['content'][$locale] ?? null;

            if (! is_array($override)) {
                continue;
            }

            $base = is_array($service[$locale] ?? null) ? $service[$locale] : [];
            $merged = $this->mergeContent($base, $override);

            if ($merged !== $base) {
                $service[$locale] = $merged;
                $modified = true;
            }
        }

        foreach ($this->validMeta($row) as $key => $value) {
            if ($value !== $service[$key]) {
                $service[$key] = $value;
                $modified = true;
            }
        }

        if (is_int($row['position'] ?? null) && $row['position'] !== $service['order']) {
            $service['order'] = $row['position'];
            $modified = true;
        }

        $service['published'] = ($row['published'] ?? true) !== false;
        $service['modified'] = $modified;

        return $service;
    }

    /**
     * A service created in the CMS, or null (logged) when its row is unusable.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function customService(string $slug, array $row): ?array
    {
        $content = [];

        foreach ($this->locales() as $locale) {
            $content[$locale] = $this->mergeContent([], is_array($row['content'][$locale] ?? null) ? $row['content'][$locale] : []);
        }

        $problem = match (true) {
            strlen($slug) > 80 || preg_match(self::SLUG_PATTERN, $slug) !== 1 => 'invalid slug',
            blank($content[self::FALLBACK_LOCALE]['title'] ?? null) => 'missing the French title',
            default => null,
        };

        if ($problem !== null) {
            Log::warning('CMS service "'.$slug.'" skipped: '.$problem.'.');

            return null;
        }

        $categories = array_keys((array) config('atelier.categories', []));
        $meta = $this->validMeta($row);
        $category = $meta['category'] ?? (string) ($categories[0] ?? 'peinture');
        $accents = (array) config('atelier.accents', []);
        $accent = $meta['accent'] ?? config('atelier.categories.'.$category.'.accent');
        $styles = (array) config('atelier.art_styles', []);

        return [
            'slug' => $slug,
            'order' => is_int($row['position'] ?? null) ? $row['position'] : null,
            'category' => $category,
            'accent' => is_string($accent) && in_array($accent, $accents, true) ? $accent : (string) ($accents[0] ?? 'yellow'),
            'icon' => $meta['icon'] ?? self::CUSTOM_ICON,
            'art_style' => $meta['art_style'] ?? (in_array(self::CUSTOM_ART_STYLE, $styles, true) ? self::CUSTOM_ART_STYLE : (string) ($styles[0] ?? self::CUSTOM_ART_STYLE)),
            'scene' => $slug,
            'published' => ($row['published'] ?? true) !== false,
            'custom' => true,
            'modified' => true,
        ] + $content;
    }

    /**
     * Meta overrides of a CMS row that are valid (others are ignored).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function validMeta(array $row): array
    {
        $valid = [];
        $allowed = [
            'category' => array_map('strval', array_keys((array) config('atelier.categories', []))),
            'accent' => (array) config('atelier.accents', []),
            'art_style' => (array) config('atelier.art_styles', []),
        ];

        foreach ($allowed as $key => $values) {
            if (is_string($row[$key] ?? null) && in_array($row[$key], $values, true)) {
                $valid[$key] = $row[$key];
            }
        }

        if (is_string($row['icon'] ?? null) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $row['icon']) === 1) {
            $valid['icon'] = $row['icon'];
        }

        return $valid;
    }

    /**
     * array_replace_recursive($base, $override) restricted to the content keys and to the shape of the
     * schema: strings stay strings (blank overrides keep the original), lists stay lists of strings or
     * of string fields (title/text, q/a). A list item overridden with null is hidden ("Masquer cet
     * élément", docs/CMS.md §13 F26), and items left without their required text are dropped.
     *
     * @param  array<string, mixed>  $base
     * @param  array<array-key, mixed>  $override
     * @return array<string, mixed>
     */
    private function mergeContent(array $base, array $override): array
    {
        foreach (self::CONTENT_KEYS as $key) {
            if (! array_key_exists($key, $override)) {
                continue;
            }

            $value = $override[$key];

            if (in_array($key, self::LIST_KEYS, true)) {
                if (is_array($value)) {
                    $base[$key] = $this->mergeList($key, is_array($base[$key] ?? null) ? $base[$key] : [], $value);
                }
            } elseif (is_string($value) && trim($value) !== '' && ! is_array($base[$key] ?? null)) {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * @param  array<array-key, mixed>  $base
     * @param  array<array-key, mixed>  $override
     * @return list<mixed>
     */
    private function mergeList(string $key, array $base, array $override): array
    {
        $base = array_values($base);
        $hidden = [];

        foreach ($override as $index => $item) {
            if (! is_int($index) || $index < 0 || $index >= self::MAX_LIST_ITEMS) {
                continue;
            }

            if ($item === null) {
                $hidden[$index] = true;

                continue;
            }

            $current = $base[$index] ?? null;

            if (is_string($item)) {
                if (trim($item) !== '' && ($current === null || is_string($current))) {
                    $base[$index] = $item;
                }

                continue;
            }

            if (! is_array($item) || ($current !== null && ! is_array($current))) {
                continue;
            }

            $fields = is_array($current) ? $current : [];

            foreach ($item as $field => $text) {
                if (is_string($field) && is_string($text) && trim($text) !== ''
                    && ($current === null || is_string($current[$field] ?? null))) {
                    $fields[$field] = $text;
                }
            }

            if ($fields !== []) {
                $base[$index] = $fields;
            }
        }

        ksort($base);

        $list = [];

        foreach ($base as $index => $item) {
            if (! isset($hidden[$index]) && self::filledItem($key, $item)) {
                // Every field of the schema present (views read them without checks).
                $list[] = is_array($item) ? $item + array_fill_keys(self::ITEM_FIELDS[$key] ?? [], '') : $item;
            }
        }

        return $list;
    }

    /**
     * Whether a list item has the text it needs: a non-blank string (body, ideal_for), a title
     * (features, process), a question and its answer (faq).
     */
    private static function filledItem(string $key, mixed $item): bool
    {
        $filled = static fn (mixed $value): bool => is_string($value) && trim($value) !== '';

        return match ($key) {
            'features', 'process' => is_array($item) && $filled($item['title'] ?? null),
            'faq' => is_array($item) && $filled($item['q'] ?? null) && $filled($item['a'] ?? null),
            default => $filled($item),
        };
    }

    /**
     * Valid content files keyed by slug, sorted by order (then slug).
     *
     * @return array<string, array<string, mixed>>
     */
    private function files(): array
    {
        if ($this->files !== null) {
            return $this->files;
        }

        $services = [];
        $files = is_dir($this->directory) ? (glob($this->directory.'/*.php') ?: []) : [];
        sort($files);

        foreach ($files as $file) {
            $data = $this->load($file);
            $problem = $this->problem($data);

            if ($problem === null && isset($services[$data['slug']])) {
                $problem = 'duplicate slug "'.$data['slug'].'"';
            }

            if ($problem !== null) {
                Log::warning('Service content file skipped: '.basename($file).' — '.$problem.'.');

                continue;
            }

            $data['order'] = (int) $data['order'];
            $services[$data['slug']] = $data;
            $this->paths[$data['slug']] = $file;
        }

        uasort($services, fn (array $a, array $b): int => [$a['order'], $a['slug']] <=> [$b['order'], $b['slug']]);

        return $this->files = $services;
    }

    private function load(string $file): mixed
    {
        try {
            return (static fn (string $path): mixed => require $path)($file);
        } catch (Throwable $e) {
            return $e;
        }
    }

    /** Why a loaded content file is unusable, or null when it is valid. */
    private function problem(mixed $data): ?string
    {
        if ($data instanceof Throwable) {
            return $data->getMessage();
        }

        if (! is_array($data)) {
            return 'the file must return an array';
        }

        foreach (self::META_KEYS as $key) {
            if ($key === 'order') {
                $order = $data['order'] ?? null;

                if (! is_int($order) && ! (is_string($order) && ctype_digit($order))) {
                    return 'missing or invalid "order"';
                }

                continue;
            }

            if (! is_string($data[$key] ?? null) || $data[$key] === '') {
                return 'missing "'.$key.'"';
            }
        }

        if (preg_match('/^[a-z0-9-]+$/', $data['slug']) !== 1) {
            return 'invalid slug "'.$data['slug'].'"';
        }

        if (! is_array($data[self::FALLBACK_LOCALE] ?? null) || blank($data[self::FALLBACK_LOCALE]['title'] ?? null)) {
            return 'missing the French content block';
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $list
     */
    private function indexOf(array $list, string $slug): ?int
    {
        foreach ($list as $index => $service) {
            if ($service['slug'] === $slug) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Content locales of the site (French first).
     *
     * @return list<string>
     */
    private function locales(): array
    {
        $locales = array_map('strval', array_keys((array) config('atelier.locales', [self::FALLBACK_LOCALE => 'Français'])));

        return array_values(array_unique([self::FALLBACK_LOCALE, ...$locales]));
    }

    private function categoryLabel(string $key, string $locale): string
    {
        $line = 'ui.categories.'.$key;

        return Lang::has($line, $locale) ? (string) __($line, [], $locale) : Str::headline($key);
    }

    private function locale(?string $locale): string
    {
        return $locale ?? app()->getLocale();
    }
}
