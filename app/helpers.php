<?php

use App\Cms\Cms;
use App\Cms\MediaItem;

if (! function_exists('ap_asset')) {
    /**
     * URL of a file in public/ with a cache-busting version derived from its
     * modification time, e.g. ap_asset('css/02-base.css') → /css/02-base.css?v=1727860000.
     */
    function ap_asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}

if (! function_exists('cms')) {
    /**
     * The CMS read model (docs/CMS.md §4.1): text overrides, services, settings, photos, free pages.
     * Never throws: without a database it answers as if the CMS were empty.
     */
    function cms(): Cms
    {
        return app(Cms::class);
    }
}

if (! function_exists('ap_share_image')) {
    /**
     * Absolute URL of the photo of a spot for social previews (og:image), e.g. the site-wide
     * "site.share" spot, at the smallest variant at least $width wide; null when the spot is empty.
     */
    function ap_share_image(?string $slot = 'site.share', int $width = 1600): ?string
    {
        if ($slot === null || $slot === '') {
            return null;
        }

        return cms()->slot($slot)?->url($width);
    }
}

if (! function_exists('ap_service_cover')) {
    /** The cover photo of a service (spot "service.{slug}.cover"), or null. */
    function ap_service_cover(string $slug): ?MediaItem
    {
        return cms()->slot('service.'.$slug.'.cover');
    }
}
