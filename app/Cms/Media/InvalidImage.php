<?php

namespace App\Cms\Media;

use RuntimeException;
use Throwable;

/**
 * An upload the photo library refuses (docs/CMS.md §4.5, §13 C8–C10). `reason` selects the message
 * shown to the owner: __('admin_media.errors.'.$e->reason).
 *
 *  - not_image: not a raster image, or a damaged one (any structural error);
 *  - type: an image of a refused type (SVG, BMP, HEIC, AVIF…);
 *  - too_big: over the upload limit, or over the size a stored file may have;
 *  - too_many_pixels: width × height over cms.media.max_pixels;
 *  - interrupted: the upload did not arrive whole (partial upload, no file) — sending it again helps;
 *  - quota: the photo library is full (cms.media.quota_mb);
 *  - storage: the server could not keep the file (database, disk, temporary folder).
 */
final class InvalidImage extends RuntimeException
{
    public const REASONS = ['not_image', 'type', 'too_big', 'too_many_pixels', 'interrupted', 'quota', 'storage'];

    /**
     * @param  string  $reason  one of self::REASONS
     */
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct('Image refused: '.$reason.'.', 0, $previous);
    }
}
