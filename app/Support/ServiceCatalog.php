<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * The 18 services of the atelier, read from resources/content/services/{slug}.php
 * (docs/ARCHITECTURE.md §4) and served as localized arrays.
 *
 * A localized service array holds the meta keys
 *   slug, order, number ('01'…), category, category_label, accent, icon, art_style, scene, url
 * followed by the content keys of the requested locale
 *   title, short, tagline, intro, body, features, process, ideal_for, faq, scene_alt, meta_description
 * Missing or empty content keys of a locale fall back to French.
 *
 * Registered as a container singleton; files are read once per instance.
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

    private readonly string $directory;

    /** @var array<string, array<string, mixed>>|null raw content files keyed by slug, sorted by order */
    private ?array $raw = null;

    /** @var array<string, string> content file path keyed by slug */
    private array $paths = [];

    /** @var array<string, list<array<string, mixed>>> localized services keyed by locale */
    private array $localized = [];

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? resource_path('content/services'), '/');
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
     * One service, or null when the slug is unknown.
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

    /** Absolute path of a service's content file (null when unknown). */
    public function path(string $slug): ?string
    {
        $this->raw();

        return $this->paths[$slug] ?? null;
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

        return $this->localized[$locale] ??= array_values(array_map(
            fn (array $raw): array => $this->localize($raw, $locale),
            $this->raw(),
        ));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function localize(array $raw, string $locale): array
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
        $order = $raw['order'];

        $meta = [
            'slug' => $slug,
            'order' => $order,
            'number' => str_pad((string) $order, 2, '0', STR_PAD_LEFT),
            'category' => $raw['category'],
            'category_label' => $this->categoryLabel($raw['category'], $locale),
            'accent' => $raw['accent'],
            'icon' => $raw['icon'],
            'art_style' => $raw['art_style'],
            'scene' => $raw['scene'],
            'url' => Route::has('services.show') ? route('services.show', ['slug' => $slug]) : url('services/'.$slug),
        ];

        return $meta + $content;
    }

    /**
     * Valid content files keyed by slug, sorted by order (then slug).
     *
     * @return array<string, array<string, mixed>>
     */
    private function raw(): array
    {
        if ($this->raw !== null) {
            return $this->raw;
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

        return $this->raw = $services;
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
