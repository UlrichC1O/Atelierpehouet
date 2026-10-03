<?php

/*
|--------------------------------------------------------------------------
| Vercel entrypoint (vercel-php runtime, see vercel.json)
|--------------------------------------------------------------------------
|
| Vercel serves public/ from its edge and sends every other request here.
| The deployment is read-only except /tmp, so Laravel's writable storage and
| package manifests move there, and sessions/cache stay off the disk.
| Variables set in the Vercel project settings always win over these defaults.
|
*/

use Illuminate\Foundation\Application;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$tmp = '/tmp/laravel';

$defaults = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'LOG_CHANNEL' => 'stderr',
    'LOG_LEVEL' => 'info',
    'SESSION_DRIVER' => 'cookie',
    'SESSION_SECURE_COOKIE' => 'true',
    'CACHE_STORE' => 'array',
    // The CMS snapshot (docs/CMS.md §2) is shared by the requests of one instance through /tmp,
    // and refreshed every minute since a flush only reaches the instance that saved.
    'CMS_CACHE_STORE' => 'file',
    'CMS_CACHE_TTL' => '60',
    // An unreachable (e.g. paused) database is skipped for two minutes before the next try (§13 A1).
    'CMS_CACHE_RETRY' => '120',
    'QUEUE_CONNECTION' => 'sync',
    'APP_PACKAGES_CACHE' => $tmp.'/bootstrap/packages.php',
    'APP_SERVICES_CACHE' => $tmp.'/bootstrap/services.php',
    // The Python engine does not run on Vercel: use the built-in artwork directly.
    'ART_ENGINE_URL' => '',
    'ART_ENGINE_CLI' => 'false',
];

foreach ($defaults as $key => $value) {
    if (getenv($key) === false) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}

foreach (['bootstrap', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $dir) {
    if (! is_dir("{$tmp}/{$dir}")) {
        mkdir("{$tmp}/{$dir}", 0755, true);
    }
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->useStoragePath($tmp.'/storage');

// Requests reach PHP through Vercel's edge and the runtime's local proxy, which set
// X-Forwarded-*: trust them so URLs are generated as https and visitor IPs are real.
TrustProxies::at('*');

// php -S reports any existing repo file (e.g. /composer.json) as the script, which makes
// Laravel route it to "/": pin the front controller so such URLs get the 404 page.
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
unset($_SERVER['PATH_INFO'], $_SERVER['PATH_TRANSLATED'], $_SERVER['ORIG_SCRIPT_NAME']);

$app->handleRequest(Request::capture());
