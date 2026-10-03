<?php

use Illuminate\Http\Middleware\TrustProxies;

/*
 * Router for PHP's built-in server when the site is served live from a GitHub Codespace
 * (bin/live.sh). The Codespace port-forwarding proxy terminates HTTPS and forwards plain
 * HTTP, so Laravel must trust its X-Forwarded-* headers to build https:// URLs and secure
 * cookies. The container is only reachable through that proxy, so trusting it is safe here.
 * (Vercel does the same in api/index.php.)
 */

$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$public = __DIR__.'/../public';

// Let the built-in server send real static files (CSS, JS, fonts, images, generated art).
if ($uri !== '/' && is_file($public.$uri)) {
    return false;
}

require __DIR__.'/../vendor/autoload.php';

TrustProxies::at('*');

$_SERVER['SCRIPT_FILENAME'] = $public.'/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require $public.'/index.php';
