<?php

namespace App\Cms\Media;

use App\Cms\MediaItem;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Photo files as base64 rows of media_files: works on read-only hosts (Vercel) and through the
 * Supabase pooler, whose emulated prepares cannot bind binary strings.
 */
final class DatabaseMediaStorage implements MediaStorage
{
    public function put(string $key, string $bytes, string $mime): void
    {
        self::guard($key);

        MediaFile::query()->updateOrCreate(['key' => $key], [
            'mime' => $mime,
            'size' => strlen($bytes),
            'contents' => base64_encode($bytes),
        ]);
    }

    public function get(string $key): ?array
    {
        if (preg_match(MediaItem::KEY_PATTERN, $key) !== 1) {
            return null;
        }

        $file = MediaFile::query()->where('key', $key)->first(['key', 'mime', 'size', 'contents', 'created_at']);

        if ($file === null) {
            return null;
        }

        $bytes = base64_decode((string) $file->contents, true);

        if ($bytes === false) {
            Log::warning('Media file '.$key.' is corrupt (invalid base64).');

            return null;
        }

        return [
            'bytes' => $bytes,
            'mime' => $file->mime,
            'size' => strlen($bytes),
            'modified' => $file->created_at?->getTimestamp(),
        ];
    }

    public function delete(string $key): void
    {
        if (preg_match(MediaItem::KEY_PATTERN, $key) === 1) {
            MediaFile::query()->where('key', $key)->delete();
        }
    }

    public function exists(string $key): bool
    {
        return preg_match(MediaItem::KEY_PATTERN, $key) === 1 && MediaFile::query()->where('key', $key)->exists();
    }

    public function driver(): string
    {
        return 'database';
    }

    private static function guard(string $key): void
    {
        if (preg_match(MediaItem::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException('Invalid media key ['.$key.'].');
        }
    }
}
