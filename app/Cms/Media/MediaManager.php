<?php

namespace App\Cms\Media;

use App\Cms\Text;
use App\Models\CustomPage;
use App\Models\Media;
use App\Models\MediaFile;
use App\Models\MediaSlot;
use Closure;
use finfo;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Writes the photo library (docs/CMS.md §4.5, §13 C8–C12): validates uploads (raster images only,
 * sniffed with finfo and getimagesize — no GD needed), rebuilds them without their metadata, stores
 * the main file and its responsive variants, and creates, replaces or deletes the Media rows.
 *
 * The browser resizes photos and builds the variants before uploading. When it sent none, GD may
 * resize them here — only with cms.media.server_variants on. An animation (GIF, APNG, animated WebP)
 * never has variants: they would be still images.
 *
 * Replaced and deleted files are retired (RetiredFiles) rather than deleted: pages still cached with
 * the old URLs keep working for a while, and each later write purges what has expired.
 */
final class MediaManager
{
    /** Attributes store() accepts besides the file. */
    public const ATTRIBUTES = [
        'alt_fr', 'alt_en', 'caption_fr', 'caption_en', 'service_slug', 'in_gallery', 'position', 'focal_x', 'focal_y', 'original_name',
    ];

    /** Largest file kept (main image or variant, metadata stripped): Vercel answers at most 4.5 MB. */
    public const MAX_STORED_BYTES = 3_500_000;

    /** Room left in post_max_size for the other fields of an upload form (texts, variants' headers…). */
    private const FORM_OVERHEAD = 256 * 1024;

    /** Variants differing from the main image's aspect ratio by more than this are refused. */
    private const RATIO_TOLERANCE = 0.02;

    /** Pixels a variant may differ from its nominal width. */
    private const WIDTH_TOLERANCE = 2;

    /** Largest image (pixels) decoded by the optional GD path (GD needs ~5 bytes per pixel). */
    private const GD_MAX_PIXELS = 24_000_000;

    public function __construct(
        private readonly MediaStorageManager $storages,
        private readonly RetiredFiles $retired,
    ) {}

    /**
     * Adds a photo to the library.
     *
     * @param  array<int|string, UploadedFile>  $variants  nominal width (one of cms.media.widths) ⇒ resized copy
     * @param  array<string, mixed>  $attributes  any of self::ATTRIBUTES (others are ignored)
     *
     * @throws InvalidImage
     */
    public function store(UploadedFile $photo, array $variants = [], array $attributes = [], ?int $userId = null): Media
    {
        $this->purgeRetired();
        $prepared = $this->prepare($photo, $variants);
        $this->checkQuota($prepared);
        $ulid = self::ulid();
        $storage = $this->storage();

        $texts = $this->attributes($attributes);
        $texts['original_name'] ??= self::fileName($photo->getClientOriginalName());

        $media = new Media;
        $media->forceFill($texts);
        $media->forceFill($this->fileAttributes($ulid, $storage, $prepared) + ['uploaded_by' => $userId]);

        $this->persist($storage, $this->files($ulid, $prepared), fn () => $media->save());

        return $media;
    }

    /**
     * Replaces the file of a photo everywhere it is used: same id, texts, flags and placements, but a
     * new ulid, hence new URLs (the old ones are cached as immutable). The old files are retired with
     * the change and stay servable for RetiredFiles::grace() seconds. The photo's original name becomes
     * $originalName (the admin uploader sends it apart from the resized file), else the uploaded file's name.
     *
     * @param  array<int|string, UploadedFile>  $variants
     *
     * @throws InvalidImage
     */
    public function replace(Media $media, UploadedFile $photo, array $variants = [], ?string $originalName = null): Media
    {
        $this->purgeRetired();
        $prepared = $this->prepare($photo, $variants);
        $this->checkQuota($prepared, $media);
        $ulid = self::ulid();
        $storage = $this->storage();
        $oldDriver = $media->driver;
        $oldKeys = $media->keys();
        $retire = $this->canRetire();

        try {
            $this->persist($storage, $this->files($ulid, $prepared), function () use ($media, $photo, $originalName, $ulid, $storage, $prepared, $retire, $oldDriver, $oldKeys): void {
                $media->forceFill($this->fileAttributes($ulid, $storage, $prepared) + [
                    'original_name' => self::fileName($originalName) ?? self::fileName($photo->getClientOriginalName()) ?? $media->original_name,
                ])->save();

                if ($retire) {
                    $this->retired->retire($oldDriver, $oldKeys);
                }
            });
        } catch (InvalidImage $e) {
            rescue(fn () => $media->refresh(), null, false);

            throw $e;
        }

        if (! $retire) {
            $this->afterCommit(fn () => $this->deleteFiles($oldDriver, $oldKeys));
        }

        return $media;
    }

    /**
     * Deletes a photo: its row and the photo spots it filled; free pages lose their cover. Its files
     * are retired (servable for RetiredFiles::grace() seconds, then purged by a later write).
     */
    public function delete(Media $media): void
    {
        $this->purgeRetired();
        $driver = $media->driver;
        $keys = $media->keys();
        $id = $media->getKey();
        $retire = $this->canRetire();

        DB::transaction(function () use ($media, $id, $driver, $keys, $retire): void {
            MediaSlot::query()->where('media_id', $id)->delete();
            CustomPage::query()->where('cover_media_id', $id)->update(['cover_media_id' => null]);
            $media->delete();

            if ($retire) {
                $this->retired->retire($driver, $keys);
            }
        });

        if (! $retire) {
            $this->afterCommit(fn () => $this->deleteFiles($driver, $keys));
        }
    }

    /**
     * Largest file the admin can upload in one request (docs/CMS.md §13 C10): the library's limit
     * (cms.media.max_kb), PHP's upload_max_filesize and post_max_size (minus room for the other
     * fields) and the host's request limit (Vercel), whichever is smallest. The uploader receives it.
     */
    public function maxUploadBytes(): int
    {
        $host = config('cms.media.host_max_bytes');

        return self::uploadLimit(
            max(1, (int) config('cms.media.max_kb', 8192)) * 1024,
            (string) ini_get('upload_max_filesize'),
            (string) ini_get('post_max_size'),
            is_numeric($host) && (int) $host > 0 ? (int) $host : null,
        );
    }

    /**
     * min($maxBytes, upload_max_filesize, post_max_size − 256 KB, $hostBytes). A php.ini value of 0
     * (or one that cannot be read) sets no limit, as in PHP.
     */
    public static function uploadLimit(int $maxBytes, string $uploadMaxFilesize, string $postMaxSize, ?int $hostBytes = null): int
    {
        $limits = [$maxBytes];
        $upload = self::iniBytes($uploadMaxFilesize);
        $post = self::iniBytes($postMaxSize);

        if ($upload > 0) {
            $limits[] = $upload;
        }

        if ($post > 0) {
            $limits[] = $post - self::FORM_OVERHEAD;
        }

        if ($hostBytes !== null) {
            $limits[] = $hostBytes;
        }

        return max(1, min($limits));
    }

    /** The library's size limit in bytes (cms.media.quota_mb), or null when there is none. */
    public function quotaBytes(): ?int
    {
        $megabytes = (int) config('cms.media.quota_mb', 0);

        return $megabytes > 0 ? $megabytes * 1024 * 1024 : null;
    }

    /**
     * Bytes held by the storage new photos go to: every file of the database driver (retired ones
     * included until they are purged), or the files of the photos kept on the disk. Throws on
     * database errors.
     */
    public function usage(): int
    {
        $driver = (string) config('cms.media.driver', 'database');

        if ($driver === 'database') {
            return (int) MediaFile::query()->sum('size');
        }

        $bytes = 0;

        Media::query()->where('driver', $driver)->select(['id', 'size', 'variants'])
            ->chunkById(200, function ($photos) use (&$bytes): void {
                foreach ($photos as $photo) {
                    $bytes += self::mediaBytes($photo);
                }
            });

        return $bytes;
    }

    /**
     * The storage new files go to (config('cms.media.driver')).
     *
     * @throws InvalidImage when the configured driver is unknown
     */
    private function storage(): MediaStorage
    {
        try {
            return $this->storages->driver();
        } catch (InvalidArgumentException $e) {
            Log::warning('Photo storage unusable: '.$e->getMessage());

            throw new InvalidImage('storage', $e);
        }
    }

    /**
     * Refuses a photo that would take the library over its quota. A replacement counts without the
     * files it replaces (they are purged a few minutes later).
     *
     * @param  array{main: array<string, mixed>, variants: array<int, array<string, mixed>>}  $prepared
     *
     * @throws InvalidImage
     */
    private function checkQuota(array $prepared, ?Media $replacing = null): void
    {
        $quota = $this->quotaBytes();

        if ($quota === null) {
            return;
        }

        try {
            $used = $this->usage();
        } catch (Throwable $e) {
            Log::warning('Photo library size unknown: '.$e->getMessage());

            throw new InvalidImage('storage', $e);
        }

        $freed = $replacing !== null && $replacing->driver === config('cms.media.driver', 'database') ? self::mediaBytes($replacing) : 0;
        $added = $prepared['main']['size'] + array_sum(array_column($prepared['variants'], 'size'));

        if ($used - $freed + $added > $quota) {
            throw new InvalidImage('quota');
        }
    }

    /**
     * Validates the main file and its variants (invalid variants are skipped).
     *
     * @param  array<int|string, mixed>  $variants
     * @return array{main: array<string, mixed>, variants: array<int, array<string, mixed>>}
     */
    private function prepare(UploadedFile $photo, array $variants): array
    {
        $main = $this->read($photo);

        if ($main['animated']) {
            return ['main' => $main, 'variants' => []]; // an animation has no still copies: served as it is
        }

        $widths = array_map('intval', (array) config('cms.media.widths', []));
        $valid = [];

        foreach ($variants as $width => $file) {
            $width = filter_var($width, FILTER_VALIDATE_INT);

            if ($width === false || ! in_array($width, $widths, true) || $width >= $main['width'] || ! $file instanceof UploadedFile) {
                continue;
            }

            try {
                $variant = $this->read($file);
            } catch (InvalidImage) {
                continue;
            }

            // A variant is a smaller copy of a still image: never an animation (it would move at some widths only).
            if (! $variant['animated'] && abs($variant['width'] - $width) <= self::WIDTH_TOLERANCE && self::sameRatio($variant, $main)) {
                $valid[$width] = $variant;
            }
        }

        if ($valid === [] && filter_var(config('cms.media.server_variants', false), FILTER_VALIDATE_BOOLEAN)) {
            $valid = $this->generate($main, $widths);
        }

        ksort($valid);

        return ['main' => $main, 'variants' => $valid];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidImage
     */
    private function read(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw new InvalidImage(match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'too_big',
                UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_FILE => 'interrupted',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'storage',
                default => 'not_image',
            });
        }

        $max = max(1, (int) config('cms.media.max_kb', 8192)) * 1024;
        $size = $file->getSize();

        if (! is_int($size) || $size > $max) {
            throw new InvalidImage('too_big');
        }

        $path = $file->getRealPath();
        $bytes = is_string($path) ? @file_get_contents($path) : false;

        if (! is_string($bytes) || $bytes === '') {
            throw new InvalidImage('not_image');
        }

        if (strlen($bytes) > $max) {
            throw new InvalidImage('too_big');
        }

        return $this->inspect($bytes);
    }

    /**
     * Sniffs, checks and rebuilds an image without its metadata. Width and height are the displayed
     * ones (a JPEG's kept EXIF orientation applied), so the pages reserve the right box.
     *
     * @return array{bytes: string, mime: string, extension: string, width: int, height: int, size: int, animated: bool}
     *
     * @throws InvalidImage
     */
    private function inspect(string $bytes): array
    {
        $types = (array) config('cms.media.types', []);
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $mime = $mime === 'image/apng' ? 'image/png' : $mime; // newer libmagic names animated PNGs apart

        if (! isset($types[$mime])) {
            // An SVG is an image, but a scriptable one: a refused type rather than "not an image".
            throw new InvalidImage(str_starts_with($mime, 'image/') ? 'type' : 'not_image');
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false || ($info['mime'] ?? null) !== $mime || $info[0] < 1 || $info[1] < 1) {
            throw new InvalidImage('not_image');
        }

        [$width, $height] = [(int) $info[0], (int) $info[1]];

        if ($width * $height > (int) config('cms.media.max_pixels', 40000000)) {
            throw new InvalidImage('too_many_pixels');
        }

        try {
            $bytes = match ($mime) {
                'image/jpeg' => JpegSanitizer::strip($bytes),
                'image/png' => PngSanitizer::strip($bytes),
                'image/webp' => WebpSanitizer::strip($bytes),
                'image/gif' => GifSanitizer::strip($bytes),
                default => throw new InvalidImage('type'), // a configured type nothing can clean is never stored
            };
        } catch (InvalidArgumentException) {
            throw new InvalidImage('not_image');
        }

        // The rebuilt file must still read as the same image.
        $rebuilt = @getimagesizefromstring($bytes);

        if ($rebuilt === false || ($rebuilt['mime'] ?? null) !== $mime || [(int) $rebuilt[0], (int) $rebuilt[1]] !== [$width, $height]) {
            throw new InvalidImage('not_image');
        }

        if (strlen($bytes) > self::MAX_STORED_BYTES) {
            throw new InvalidImage('too_big');
        }

        if ($mime === 'image/jpeg' && ExifOrientation::swapsDimensions(JpegSanitizer::orientation($bytes))) {
            [$width, $height] = [$height, $width];
        }

        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'extension' => (string) $types[$mime],
            'width' => $width,
            'height' => $height,
            'size' => strlen($bytes),
            // Every GIF counts as one: the uploader sends them untouched, and a still GIF is small anyway.
            'animated' => match ($mime) {
                'image/gif' => true,
                'image/png' => PngSanitizer::animated($bytes),
                'image/webp' => WebpSanitizer::animated($bytes),
                default => false,
            },
        ];
    }

    /**
     * Optional (cms.media.server_variants): variants resized with GD when the browser sent none.
     *
     * @param  array<string, mixed>  $main
     * @param  list<int>  $widths
     * @return array<int, array<string, mixed>>
     */
    private function generate(array $main, array $widths): array
    {
        if (! extension_loaded('gd') || $main['width'] * $main['height'] > self::GD_MAX_PIXELS) {
            return [];
        }

        $encode = match ($main['mime']) {
            'image/jpeg' => fn (GdImage $image): bool => imagejpeg($image, null, 86),
            'image/png' => fn (GdImage $image): bool => imagepng($image, null, 6),
            'image/webp' => function_exists('imagewebp') ? fn (GdImage $image): bool => imagewebp($image, null, 82) : null,
            default => null,
        };

        if ($encode === null) {
            return [];
        }

        try {
            $source = @imagecreatefromstring($main['bytes']);

            if (! $source instanceof GdImage) {
                return [];
            }

            $source = self::orient($source, $main['mime'] === 'image/jpeg' ? JpegSanitizer::orientation($main['bytes']) : null);
            $variants = [];

            foreach ($widths as $width) {
                if ($width >= $main['width']) {
                    continue;
                }

                $scaled = imagescale($source, $width, max(1, (int) round($main['height'] * $width / $main['width'])), IMG_BICUBIC);

                if (! $scaled instanceof GdImage) {
                    continue;
                }

                imagesavealpha($scaled, true);
                ob_start();

                try {
                    $encoded = $encode($scaled);
                } finally {
                    $bytes = (string) ob_get_clean(); // never leave the buffer open: it would swallow the response
                }

                if (! $encoded || $bytes === '') {
                    continue;
                }

                try {
                    $variants[$width] = $this->inspect($bytes);
                } catch (InvalidImage) {
                    continue;
                }
            }

            return $variants;
        } catch (Throwable $e) {
            Log::warning('Photo variants could not be generated with GD: '.$e->getMessage());

            return [];
        }
    }

    /** Applies an EXIF orientation to the pixels (GD ignores it). */
    private static function orient(GdImage $image, ?int $orientation): GdImage
    {
        $rotate = static fn (GdImage $image, int $angle): GdImage => imagerotate($image, $angle, 0) ?: $image;

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $image;
            case 3:
                return $rotate($image, 180);
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);

                return $image;
            case 5: // transpose
                $image = $rotate($image, 270);
                imageflip($image, IMG_FLIP_HORIZONTAL);

                return $image;
            case 6: // 90° clockwise (imagerotate turns counter-clockwise)
                return $rotate($image, 270);
            case 7: // transverse
                $image = $rotate($image, 270);
                imageflip($image, IMG_FLIP_VERTICAL);

                return $image;
            case 8:
                return $rotate($image, 90);
            default:
                return $image;
        }
    }

    /**
     * Writes the row and the files in one transaction; files already written to a non-transactional
     * storage are removed when anything fails.
     *
     * @param  array<string, array<string, mixed>>  $files  storage key ⇒ prepared file
     *
     * @throws InvalidImage
     */
    private function persist(MediaStorage $storage, array $files, Closure $saveRow): void
    {
        $written = [];

        try {
            DB::transaction(function () use ($storage, $files, $saveRow, &$written): void {
                $saveRow();

                foreach ($files as $key => $file) {
                    $storage->put($key, $file['bytes'], $file['mime']);
                    $written[] = $key;
                }
            });
        } catch (Throwable $e) {
            foreach ($written as $key) {
                rescue(fn () => $storage->delete($key), null, false);
            }

            Log::warning('Photo could not be stored: '.$e->getMessage());

            throw $e instanceof InvalidImage ? $e : new InvalidImage('storage', $e);
        }
    }

    /**
     * @param  array{main: array<string, mixed>, variants: array<int, array<string, mixed>>}  $prepared
     * @return array<string, mixed>
     */
    private function fileAttributes(string $ulid, MediaStorage $storage, array $prepared): array
    {
        $main = $prepared['main'];
        $variants = [];

        foreach ($prepared['variants'] as $width => $variant) {
            $variants[] = [
                'width' => $variant['width'],
                'height' => $variant['height'],
                'size' => $variant['size'],
                'key' => $ulid.'-'.$width.'.'.$variant['extension'],
            ];
        }

        return [
            'ulid' => $ulid,
            'driver' => $storage->driver(),
            'mime' => $main['mime'],
            'extension' => $main['extension'],
            'width' => $main['width'],
            'height' => $main['height'],
            'size' => $main['size'],
            'variants' => $variants,
        ];
    }

    /**
     * @param  array{main: array<string, mixed>, variants: array<int, array<string, mixed>>}  $prepared
     * @return array<string, array<string, mixed>> storage key ⇒ prepared file, main first
     */
    private function files(string $ulid, array $prepared): array
    {
        $files = [$ulid.'.'.$prepared['main']['extension'] => $prepared['main']];

        foreach ($prepared['variants'] as $width => $variant) {
            $files[$ulid.'-'.$width.'.'.$variant['extension']] = $variant;
        }

        return $files;
    }

    /**
     * The accepted attributes, typed and trimmed to their column sizes.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributes(array $attributes): array
    {
        $clean = [];

        foreach (self::ATTRIBUTES as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $value = $attributes[$key];

            $clean[$key] = match ($key) {
                'alt_fr', 'alt_en' => self::text($value, 300),
                'caption_fr', 'caption_en' => self::text($value, 500),
                'original_name' => self::fileName($value),
                'service_slug' => is_string($value) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1 ? mb_substr($value, 0, 80) : null,
                'in_gallery' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'position' => max(0, (int) (is_numeric($value) ? $value : 0)),
                default => max(0, min(100, (int) (is_numeric($value) ? $value : 50))), // focal_x, focal_y
            };
        }

        return $clean;
    }

    /**
     * Whether old files can be retired (the media_retired table exists). When it cannot be told, the
     * old files are deleted at once, as before retirement existed.
     */
    private function canRetire(): bool
    {
        return rescue(fn () => $this->retired->ready(), false, false) === true;
    }

    /** Purges the retired files whose grace period is over, once the current transaction commits. */
    private function purgeRetired(): void
    {
        $this->afterCommit(fn () => $this->retired->purge());
    }

    /**
     * @param  list<string>  $keys
     */
    private function deleteFiles(string $driver, array $keys): void
    {
        try {
            $storage = $this->storages->driver($driver);
        } catch (Throwable $e) {
            Log::warning('Photo files not deleted (driver '.$driver.'): '.$e->getMessage());

            return;
        }

        foreach ($keys as $key) {
            try {
                $storage->delete($key);
            } catch (Throwable $e) {
                Log::warning('Photo file '.$key.' not deleted: '.$e->getMessage());
            }
        }
    }

    /**
     * Runs $callback once the current transaction commits (at once outside a transaction). The
     * callbacks given here never throw, so the fallback cannot run one twice.
     */
    private function afterCommit(Closure $callback): void
    {
        try {
            DB::afterCommit($callback);
        } catch (Throwable) {
            $callback();
        }
    }

    /** Bytes of a photo's files: the main one and its variants. */
    private static function mediaBytes(Media $media): int
    {
        $bytes = (int) $media->size;

        foreach ((array) $media->variants as $variant) {
            $bytes += is_array($variant) ? (int) ($variant['size'] ?? 0) : 0;
        }

        return $bytes;
    }

    /** "8M" ⇒ 8388608 (php.ini shorthand); 0 when empty or unreadable. */
    private static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        try {
            return max(0, (int) @ini_parse_quantity($value));
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @param  array<string, mixed>  $variant
     * @param  array<string, mixed>  $main
     */
    private static function sameRatio(array $variant, array $main): bool
    {
        $ratio = ($variant['width'] / $variant['height']) / ($main['width'] / $main['height']);

        return abs($ratio - 1) <= self::RATIO_TOLERANCE;
    }

    private static function ulid(): string
    {
        return strtolower((string) Str::ulid());
    }

    /** A text attribute storable in Postgres (valid UTF-8, no NUL byte, cut to its column — App\Cms\Text). */
    private static function text(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(str_replace(["\r\n", "\r"], "\n", (string) Text::column((string) $value, PHP_INT_MAX)));

        return $value === '' ? null : Text::column($value, $max);
    }

    /** A client file name reduced to a safe, displayable base name (or null). */
    private static function fileName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', basename(str_replace('\\', '/', (string) Text::column($name, PHP_INT_MAX)))));

        return $name === '' ? null : Text::column($name, 255);
    }
}
