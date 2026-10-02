<?php

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
