<?php

namespace App\Cms\Media;

use App\Cms\MediaItem;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Photo files on a Laravel disk (config('cms.media.disk'), by default "media" = storage/app/media,
 * never public: files are always served through GET /media/{key}). For hosts with a persistent disk.
 */
final class FilesystemMediaStorage implements MediaStorage
{
    public function __construct(private readonly string $disk) {}

    public function put(string $key, string $bytes, string $mime): void
    {
        if (preg_match(MediaItem::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException('Invalid media key ['.$key.'].');
        }

        if (! $this->disk()->put($key, $bytes)) {
            throw new RuntimeException('Media file '.$key.' could not be written to the "'.$this->disk.'" disk.');
        }
    }

    public function get(string $key): ?array
    {
        if (preg_match(MediaItem::KEY_PATTERN, $key, $matches) !== 1) {
            return null;
        }

        $bytes = $this->disk()->get($key);

        if (! is_string($bytes)) {
            return null;
        }

        $types = array_flip((array) config('cms.media.types', []));
        $modified = rescue(fn () => $this->disk()->lastModified($key), null, false);

        return [
            'bytes' => $bytes,
            'mime' => (string) ($types[$matches[3]] ?? 'application/octet-stream'),
            'size' => strlen($bytes),
            'modified' => is_int($modified) ? $modified : null,
        ];
    }

    public function delete(string $key): void
    {
        if (preg_match(MediaItem::KEY_PATTERN, $key) === 1) {
            $this->disk()->delete($key);
        }
    }

    public function exists(string $key): bool
    {
        return preg_match(MediaItem::KEY_PATTERN, $key) === 1 && $this->disk()->exists($key);
    }

    public function driver(): string
    {
        return 'filesystem';
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->disk);
    }
}
