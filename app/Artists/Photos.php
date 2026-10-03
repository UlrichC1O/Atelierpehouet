<?php

namespace App\Artists;

use App\Cms\Media\InvalidImage;
use App\Cms\Media\MediaManager;
use App\Cms\MediaItem;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * The bridge between the artist pages and the CMS photo library (docs/ARTISTS.md §1): photos are
 * written by App\Cms\Media\MediaManager only (store/replace/delete) and read as App\Cms\MediaItem.
 * Every method is guarded, so the artist pages keep working ("no photos") before the CMS core is
 * installed or while its tables are missing.
 *
 * Public pages read photos through ArtistDirectory::media() (the CMS snapshot), never through this class.
 */
final class Photos
{
    /** The CMS media manager, or null when the photo library is not installed. */
    public static function manager(): ?MediaManager
    {
        if (! class_exists(MediaManager::class)) {
            return null;
        }

        try {
            return app(MediaManager::class);
        } catch (Throwable $e) {
            Log::warning('Artist pages: the photo library is unavailable: '.$e->getMessage());

            return null;
        }
    }

    /** The Media row of a photo id, or null (no id, no library, unknown photo, database error). */
    public static function model(?int $id): ?Media
    {
        if ($id === null || $id < 1 || ! class_exists(Media::class)) {
            return null;
        }

        try {
            return Media::query()->find($id);
        } catch (Throwable) {
            return null;
        }
    }

    /** The value object of a photo read from the database (admin screens, JSON answers), or null. */
    public static function item(?Media $media): ?MediaItem
    {
        if ($media === null || ! class_exists(MediaItem::class)) {
            return null;
        }

        try {
            return MediaItem::fromModel($media);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The "media" object of the uploader's JSON answers (docs/CMS.md §7.6): MediaItem::toArray() plus
     * the URL of the photo's page in the library.
     *
     * @return array<string, mixed>|null
     */
    public static function json(?Media $media): ?array
    {
        $item = self::item($media);

        if ($item === null) {
            return null;
        }

        return $item->toArray() + [
            'edit_url' => Route::has('admin.media.edit') ? route('admin.media.edit', $item->id) : null,
        ];
    }

    /**
     * Puts a newly uploaded file on a photo slot of an artist row (the portrait, an exhibition visual).
     * The current photo's file is replaced in place when no other part of the site uses it — no orphan
     * is left in the library — otherwise the upload becomes a new photo and the current one stays
     * wherever else it is used (gallery, a page spot, another artist row…).
     *
     * @param  array<int, UploadedFile>  $variants  resized copies sent by the CMS uploader (width ⇒ file)
     * @param  array{artists?: list<int>, artworks?: list<int>, exhibitions?: list<int>}  $ignore  the row itself (MediaUsage::deletable())
     * @param  array<string, mixed>  $attributes  of a new photo (alt texts, original_name)
     * @return array{0: Media, 1: bool} the photo, and whether it is the current one with a new file
     *
     * @throws InvalidImage when the library refuses the file
     */
    public static function put(MediaManager $manager, ?int $currentId, UploadedFile $photo, array $variants, array $ignore, array $attributes, ?int $userId): array
    {
        $current = self::model($currentId);

        if ($current !== null && MediaUsage::deletable((int) $current->getKey(), $ignore)) {
            $name = $attributes['original_name'] ?? null;

            return [$manager->replace($current, $photo, $variants, is_string($name) ? $name : null), true];
        }

        return [$manager->store($photo, $variants, $attributes, $userId), false];
    }

    /**
     * Deletes photos through the media manager (row, files, spots). A failure is logged and skipped.
     *
     * @param  list<int>  $ids
     * @return int number of photos deleted
     */
    public static function delete(array $ids): int
    {
        $manager = $ids === [] ? null : self::manager();

        if ($manager === null) {
            return 0;
        }

        $deleted = 0;

        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $media = self::model($id);

            if ($media === null) {
                continue;
            }

            try {
                $manager->delete($media);
                $deleted++;
            } catch (Throwable $e) {
                Log::warning('Artist pages: photo #'.$id.' not deleted: '.$e->getMessage());
            }
        }

        return $deleted;
    }
}
