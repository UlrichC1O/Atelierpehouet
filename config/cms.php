<?php

/*
|--------------------------------------------------------------------------
| Ateliers Pehouet — admin CMS (docs/CMS.md)
|--------------------------------------------------------------------------
|
| The language files and resources/content/services/*.php stay the defaults:
| the CMS stores overrides (and what the owner creates) in the database, and
| the public site falls back to the files whenever the database is unavailable.
|
*/

return [

    // Translation groups editable in "Textes des pages", in menu order ⇒ route of the public page to preview (or null).
    'editable_groups' => [
        'home' => 'home',
        'about' => 'about',
        'community' => 'community',
        'services' => 'services.index',
        'gallery' => 'gallery',
        'generator' => 'generator',
        'motion' => 'motion',
        'contact' => 'contact',
        'ui' => 'home',
        'components' => 'home',
        'photos' => 'gallery',
        'pages' => null,
        'notices' => null,
        'errors' => null,
        'mail' => null,
    ],

    'cache' => [
        // null = the default cache store (Vercel: "file" in /tmp, see api/index.php).
        'store' => env('CMS_CACHE_STORE'),
        // Seconds a public snapshot of the CMS data is reused (writes flush it at once on this server).
        'ttl' => (int) env('CMS_CACHE_TTL', 600),
        // Seconds a failed load is remembered, and an unreachable database skipped by the circuit
        // breaker (App\Cms\DatabaseHealth), before the next try (Vercel: 120, see api/index.php).
        'retry' => (int) env('CMS_CACHE_RETRY', 30),
        'key' => 'cms.snapshot.v1',
    ],

    'media' => [
        // database (base64 rows — works on read-only hosts such as Vercel) | filesystem (Laravel disk below)
        'driver' => env('CMS_MEDIA_DRIVER', 'database'),
        'disk' => env('CMS_MEDIA_DISK', 'media'),
        // Per uploaded file. Browsers resize photos before sending them; the effective limit is
        // App\Cms\Media\MediaManager::maxUploadBytes() (php.ini and Vercel's 4.5 MB bodies, §13 C10).
        'max_kb' => (int) env('CMS_MEDIA_MAX_KB', 8192),
        // Largest request body the host accepts, in bytes (Vercel: 4.5 MB ⇒ uploads stay under 4 MB there).
        'host_max_bytes' => env('VERCEL') ? 4000000 : null,
        // Decompression-bomb guard (width × height).
        'max_pixels' => 40000000,
        // Raster types rebuilt without their metadata (no AVIF: nothing here can clean it, §13 C8).
        'types' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ],
        // Responsive variants (only those narrower than the main image are kept); the main image
        // serves wide screens and the lightbox (§13 C9).
        'widths' => [480, 960],
        // The browser resizes the main image to this longest edge…
        'max_edge' => 1920,
        // …and encodes it as WebP at this quality (JPEG fallback 0.86).
        'quality' => 0.82,
        // Total size of the stored photo files, in MB (0 = no limit): uploads beyond it are refused
        // ("quota"). 300 by default in the database (Supabase's free plan holds 500 MB, base64 adds a third).
        'quota_mb' => (int) env('CMS_MEDIA_QUOTA_MB', env('CMS_MEDIA_DRIVER', 'database') === 'database' ? 300 : 0),
        // Resize the variants on the server (GD) when the browser sent none — never for animations (§13 C11).
        'server_variants' => (bool) env('CMS_MEDIA_SERVER_VARIANTS', false),
    ],

    // Fixed photo spots of the pages (labels: admin.slots.{key}); "service.{slug}.cover" exists for every service.
    'slots' => [
        'home.feature' => ['page' => 'home', 'ratio' => '4/3'],
        'about.portrait' => ['page' => 'about', 'ratio' => '4/5'],
        'about.atelier' => ['page' => 'about', 'ratio' => '16/9'],
        'community.feature' => ['page' => 'community', 'ratio' => '16/9'],
        'site.share' => ['page' => 'ui', 'ratio' => '1200/630'],
    ],

    'service_cover_ratio' => '4/3',

    // First URL segments a free page may never use (every registered route's first segment is refused too).
    'reserved_slugs' => [
        'admin', 'services', 'a-propos', 'communaute', 'galerie', 'atelier-numerique', 'mouvement', 'contact', 'artistes',
        'langue', 'media', 'storage', 'up', 'api', 'css', 'js', 'fonts', 'images', 'generated', 'build', 'vendor',
        'login', 'logout', 'register', 'sitemap', 'robots', 'favicon', 'index',
    ],

    // First administrator: created at the first successful login while the users table is empty.
    'bootstrap_admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    // Pipeline of the contact requests (labels: admin.messages.statuses.{status}).
    'message_statuses' => ['new', 'in_progress', 'done', 'archived'],

    // Failed logins allowed per minute for one e-mail address + IP.
    'login_attempts' => 5,

    // Cache store of the login limiter (docs/CMS.md §13 D15). It must outlive a request — not Vercel's
    // "array" default store: "database" is the cache table every install already has.
    // An empty CMS_LOGIN_LIMITER_STORE= keeps "database" (an empty name would pick the default store).
    'login_limiter_store' => env('CMS_LOGIN_LIMITER_STORE') ?: 'database',

    // The official site (docs/CMS.md §13 F25): every other copy (the Codespace…) says so on each admin page.
    // Set it when the site moves to its own domain.
    'primary_url' => env('CMS_PRIMARY_URL', 'https://atelierpehouet.vercel.app'),

];
