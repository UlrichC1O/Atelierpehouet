<?php

namespace App\Cms;

use App\Models\Media;
use DateTimeInterface;

/**
 * A photo of the CMS library as the pages use it (docs/CMS.md §4.2): URLs of the main file and of the
 * responsive variants, texts per language, focal point. Built from a Media model or from the arrays of
 * the CMS snapshot; never touches the database.
 *
 * URLs are generated when asked (never cached): they depend on the host of the request.
 */
final readonly class MediaItem
{
    /** Storage keys: {ulid}.{ext} for the main file, {ulid}-{width}.{ext} for a variant. */
    public const KEY_PATTERN = '/^([0-9a-z]{26})(?:-([0-9]{2,4}))?\.(jpg|png|webp|gif)$/';

    /**
     * @param  list<array{width: int, height: int, key: string, size: int}>  $variants  ascending widths, main excluded
     * @param  array{fr: string|null, en: string|null}  $alt
     * @param  array{fr: string|null, en: string|null}  $caption
     */
    public function __construct(
        public int $id,
        public string $ulid,
        public string $extension,
        public string $mime,
        public int $width,
        public int $height,
        public array $variants,
        public array $alt,
        public array $caption,
        public int $focalX,
        public int $focalY,
        public ?string $service,
        public bool $inGallery,
        public int $position,
        public ?string $originalName,
        public ?string $updatedAt,
        public int $size = 0,
    ) {}

    public static function fromModel(Media $media): self
    {
        // The Carbon instance first: attributesToArray() would serialize it as ISO-8601, the snapshot
        // keeps the database format.
        return self::fromArray(['id' => $media->getKey(), 'updated_at' => $media->updated_at] + $media->attributesToArray());
    }

    /**
     * From a database row (alt_fr, caption_en, focal_x, service_slug…), a snapshot entry (toSnapshot())
     * or the output of toArray().
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $variants = $data['variants'] ?? [];
        $variants = is_string($variants) ? json_decode($variants, true) : $variants;
        $updatedAt = $data['updated_at'] ?? $data['updatedAt'] ?? null;

        return new self(
            id: (int) ($data['id'] ?? 0),
            ulid: strtolower((string) ($data['ulid'] ?? '')),
            extension: (string) ($data['extension'] ?? ''),
            mime: (string) ($data['mime'] ?? ''),
            width: max(1, (int) ($data['width'] ?? 1)),
            height: max(1, (int) ($data['height'] ?? 1)),
            variants: self::variants(is_array($variants) ? $variants : []),
            alt: self::pair($data, 'alt'),
            caption: self::pair($data, 'caption'),
            focalX: self::percent($data['focal_x'] ?? $data['focalX'] ?? 50),
            focalY: self::percent($data['focal_y'] ?? $data['focalY'] ?? 50),
            service: self::text($data['service_slug'] ?? $data['service'] ?? null),
            inGallery: filter_var($data['in_gallery'] ?? $data['inGallery'] ?? false, FILTER_VALIDATE_BOOLEAN),
            position: max(0, (int) ($data['position'] ?? 0)),
            originalName: self::text($data['original_name'] ?? $data['originalName'] ?? null),
            updatedAt: $updatedAt instanceof DateTimeInterface ? $updatedAt->format('Y-m-d H:i:s') : self::text($updatedAt),
            size: max(0, (int) ($data['size'] ?? 0)),
        );
    }

    /**
     * JSON-safe description for the admin and scripts: every property plus `key`, `url` (main file),
     * `thumb` (≥ 480 px variant), `srcset`, `ratio`, `object_position`, `landscape`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'extension' => $this->extension,
            'mime' => $this->mime,
            'width' => $this->width,
            'height' => $this->height,
            'size' => $this->size,
            'variants' => $this->variants,
            'alt' => $this->alt,
            'caption' => $this->caption,
            'focal_x' => $this->focalX,
            'focal_y' => $this->focalY,
            'service' => $this->service,
            'in_gallery' => $this->inGallery,
            'position' => $this->position,
            'original_name' => $this->originalName,
            'updated_at' => $this->updatedAt,
            'key' => $this->key(),
            'url' => $this->url(),
            'thumb' => $this->url(480),
            'srcset' => $this->srcset(),
            'ratio' => $this->ratio(),
            'object_position' => $this->objectPosition(),
            'landscape' => $this->isLandscape(),
        ];
    }

    /**
     * The row shape kept in the CMS snapshot (no URL: the cached snapshot serves every host).
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'extension' => $this->extension,
            'mime' => $this->mime,
            'width' => $this->width,
            'height' => $this->height,
            'size' => $this->size,
            'variants' => $this->variants,
            'alt_fr' => $this->alt['fr'],
            'alt_en' => $this->alt['en'],
            'caption_fr' => $this->caption['fr'],
            'caption_en' => $this->caption['en'],
            'focal_x' => $this->focalX,
            'focal_y' => $this->focalY,
            'service_slug' => $this->service,
            'in_gallery' => $this->inGallery,
            'position' => $this->position,
            'original_name' => $this->originalName,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * Storage key of the smallest variant at least $width pixels wide (2 px tolerance), or of the
     * main file when $width is null or no variant is wide enough.
     */
    public function key(?int $width = null): string
    {
        if ($width !== null) {
            foreach ($this->variants as $variant) {
                if ($variant['width'] >= $width - 2) {
                    return $variant['key'];
                }
            }
        }

        return $this->ulid.'.'.$this->extension;
    }

    /** Absolute URL of key($width) (GET /media/{key}). */
    public function url(?int $width = null): string
    {
        return route('media.show', ['key' => $this->key($width)]);
    }

    /** "…-480.webp 480w, …-960.webp 960w, ….webp {width}w" for <img srcset>. */
    public function srcset(): string
    {
        $candidates = [];

        foreach ($this->variants as $variant) {
            $candidates[] = route('media.show', ['key' => $variant['key']]).' '.$variant['width'].'w';
        }

        $candidates[] = $this->url().' '.$this->width.'w';

        return implode(', ', $candidates);
    }

    /** Alternative text: the locale's, else French, else the caption, else ''. */
    public function alt(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return self::text($this->alt[$locale] ?? null) ?? self::text($this->alt['fr']) ?? $this->caption($locale) ?? '';
    }

    /** Caption: the locale's, else French, else null. */
    public function caption(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return self::text($this->caption[$locale] ?? null) ?? self::text($this->caption['fr']);
    }

    /** CSS aspect-ratio value, e.g. "1600 / 1200". */
    public function ratio(): string
    {
        return $this->width.' / '.$this->height;
    }

    /** CSS object-position from the focal point, e.g. "50% 30%". */
    public function objectPosition(): string
    {
        return $this->focalX.'% '.$this->focalY.'%';
    }

    public function isLandscape(): bool
    {
        return $this->width > $this->height;
    }

    /**
     * @param  array<mixed>  $variants
     * @return list<array{width: int, height: int, key: string, size: int}>
     */
    private static function variants(array $variants): array
    {
        $valid = [];

        foreach ($variants as $variant) {
            if (! is_array($variant) || ! is_string($variant['key'] ?? null) || preg_match(self::KEY_PATTERN, $variant['key']) !== 1) {
                continue;
            }

            $valid[] = [
                'width' => max(1, (int) ($variant['width'] ?? 1)),
                'height' => max(1, (int) ($variant['height'] ?? 1)),
                'key' => $variant['key'],
                'size' => max(0, (int) ($variant['size'] ?? 0)),
            ];
        }

        usort($valid, fn (array $a, array $b): int => $a['width'] <=> $b['width']);

        return $valid;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{fr: string|null, en: string|null}
     */
    private static function pair(array $data, string $field): array
    {
        $pair = is_array($data[$field] ?? null) ? $data[$field] : [];

        return [
            'fr' => self::text($pair['fr'] ?? $data[$field.'_fr'] ?? null),
            'en' => self::text($pair['en'] ?? $data[$field.'_en'] ?? null),
        ];
    }

    private static function percent(mixed $value): int
    {
        return max(0, min(100, (int) $value));
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
}
