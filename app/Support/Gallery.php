<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Artworks of the generative gallery.
 *
 * Reads the manifest written by `php artisan atelier:generate-art`
 * (public/generated/gallery/manifest.json, docs/ARCHITECTURE.md §11):
 *   {"version":1,"items":[{"file":"gallery/pehouet-1.svg","style":"pehouet","seed":"…",
 *                          "title":{"fr":"…","en":"…"},"width":800,"height":800}]}
 * File paths are relative to the manifest's parent directory (public/generated).
 *
 * Until the gallery has been generated, every style is shown through the live
 * art endpoint (route "generator.art") so the pages are never empty.
 *
 * Artwork array: ['src' => url, 'style' => key, 'seed' => string, 'title' => string,
 *                 'width' => int, 'height' => int]
 */
final class Gallery
{
    /** Artworks per style when the gallery has not been generated yet. */
    public const FALLBACK_PER_STYLE = 3;

    private readonly string $manifestPath;

    /** @var list<array{file: string, style: string, seed: string, title: array<string, string>, width: int, height: int}>|null */
    private ?array $items = null;

    public function __construct(?string $manifestPath = null)
    {
        $this->manifestPath = $manifestPath ?? public_path('generated/gallery/manifest.json');
    }

    public function manifestPath(): string
    {
        return $this->manifestPath;
    }

    /** True when a generated gallery (manifest + files) is available. */
    public function generated(): bool
    {
        return $this->items() !== [];
    }

    /**
     * Every artwork, grouped by style in config order.
     *
     * @return list<array{src: string, style: string, seed: string, title: string, width: int, height: int}>
     */
    public function all(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        if (! $this->generated()) {
            return $this->fallback($locale);
        }

        return array_map(fn (array $item): array => [
            'src' => asset('generated/'.$item['file']),
            'style' => $item['style'],
            'seed' => $item['seed'],
            'title' => $item['title'][$locale] ?? $item['title']['fr'] ?? $this->styleName($item['style'], $locale),
            'width' => $item['width'],
            'height' => $item['height'],
        ], $this->items());
    }

    /**
     * A varied selection: styles take turns (first artwork of each style, then the
     * second…), limited to $count.
     *
     * @return list<array{src: string, style: string, seed: string, title: string, width: int, height: int}>
     */
    public function preview(int $count = 8, ?string $locale = null): array
    {
        if ($count < 1) {
            return [];
        }

        $byStyle = [];

        foreach ($this->all($locale) as $artwork) {
            $byStyle[$artwork['style']][] = $artwork;
        }

        $preview = [];

        for ($round = 0; count($preview) < $count; $round++) {
            $added = false;

            foreach ($byStyle as $artworks) {
                if (isset($artworks[$round]) && count($preview) < $count) {
                    $preview[] = $artworks[$round];
                    $added = true;
                }
            }

            if (! $added) {
                break;
            }
        }

        return $preview;
    }

    /**
     * Style keys that have at least one artwork, in config order.
     *
     * @return list<string>
     */
    public function styles(): array
    {
        $present = array_unique(array_column($this->all(), 'style'));

        return array_values(array_filter($this->knownStyles(), fn (string $style): bool => in_array($style, $present, true)));
    }

    /**
     * Valid manifest items whose file exists, ordered by style (config order).
     *
     * @return list<array{file: string, style: string, seed: string, title: array<string, string>, width: int, height: int}>
     */
    private function items(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        if (! is_file($this->manifestPath)) {
            return $this->items = [];
        }

        $manifest = json_decode((string) @file_get_contents($this->manifestPath), true);

        if (! is_array($manifest) || ! is_array($manifest['items'] ?? null)) {
            Log::warning('Gallery manifest ignored: '.$this->manifestPath.' is not valid.');

            return $this->items = [];
        }

        $root = dirname($this->manifestPath, 2);
        $styles = $this->knownStyles();
        $items = [];

        foreach ($manifest['items'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $file = $item['file'] ?? null;
            $style = $item['style'] ?? null;

            if (! is_string($file) || preg_match('#^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*\.svg$#', $file) !== 1
                || ! in_array($style, $styles, true) || ! is_file($root.'/'.$file)) {
                continue;
            }

            $title = $item['title'] ?? [];
            $title = is_string($title) ? ['fr' => $title] : (is_array($title) ? $title : []);

            $items[] = [
                'file' => $file,
                'style' => $style,
                'seed' => is_scalar($item['seed'] ?? null) ? (string) $item['seed'] : pathinfo($file, PATHINFO_FILENAME),
                'title' => array_filter($title, fn (mixed $text): bool => is_string($text) && $text !== ''),
                'width' => $this->dimension($item['width'] ?? null),
                'height' => $this->dimension($item['height'] ?? null),
            ];
        }

        // Group by style in config order, keeping the manifest order inside a style.
        usort($items, fn (array $a, array $b): int => array_search($a['style'], $styles, true) <=> array_search($b['style'], $styles, true));

        return $this->items = $items;
    }

    /**
     * Live-rendered artworks used until the gallery is generated.
     *
     * @return list<array{src: string, style: string, seed: string, title: string, width: int, height: int}>
     */
    private function fallback(string $locale): array
    {
        if (! Route::has('generator.art')) {
            return [];
        }

        $artworks = [];

        foreach ($this->knownStyles() as $style) {
            for ($n = 1; $n <= self::FALLBACK_PER_STYLE; $n++) {
                $seed = 'galerie-'.$style.'-'.$n;

                $artworks[] = [
                    'src' => route('generator.art', ['style' => $style, 'seed' => $seed, 'size' => 800]),
                    'style' => $style,
                    'seed' => $seed,
                    'title' => $this->styleName($style, $locale).' · '.$n,
                    'width' => 800,
                    'height' => 800,
                ];
            }
        }

        return $artworks;
    }

    /**
     * @return list<string>
     */
    private function knownStyles(): array
    {
        return array_values(array_filter((array) config('atelier.art_styles', []), 'is_string'));
    }

    private function styleName(string $style, string $locale): string
    {
        $line = 'generator.styles.'.$style.'.name';

        return Lang::has($line, $locale) ? (string) __($line, [], $locale) : ucfirst($style);
    }

    private function dimension(mixed $value): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : 800;
    }
}
