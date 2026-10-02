<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Throwable;

/**
 * GET /langue/{locale}: remembers the language in the session and sends the visitor
 * back to the page they came from — only pages of this site, never elsewhere.
 */
final class LocaleController extends Controller
{
    /** Routes that are not pages a visitor should land back on. */
    private const NOT_PAGES = ['locale.switch', 'generator.art', 'contact.store', 'sitemap', 'robots'];

    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, (array) config('atelier.locales', [])), 404);

        $request->session()->put('locale', $locale);
        app()->setLocale($locale);

        return redirect()->to($this->target($request));
    }

    private function target(Request $request): string
    {
        foreach ([$request->headers->get('referer'), $request->session()->previousUrl()] as $candidate) {
            $url = is_string($candidate) ? $this->sameSitePage($candidate, $request) : null;

            if ($url !== null) {
                return $url;
            }
        }

        return route('home');
    }

    /** The URL without its ?lang= parameter when it is a page of this site, else null. */
    private function sameSitePage(string $url, Request $request): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['host']) || strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return null;
        }

        $path = '/'.ltrim($parts['path'] ?? '/', '/');
        $base = $request->getBasePath();
        $routePath = $base !== '' && str_starts_with($path, $base.'/') ? substr($path, strlen($base)) : $path;

        if (! $this->isPage($routePath)) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);
        unset($query['lang']);
        $query = http_build_query($query);

        return ($parts['scheme'] ?? $request->getScheme()).'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .$path.($query !== '' ? '?'.$query : '');
    }

    private function isPage(string $path): bool
    {
        try {
            $route = Router::getRoutes()->match(Request::create($path));
        } catch (Throwable) {
            return false;
        }

        return $route instanceof Route
            && ! $route->isFallback
            && $route->getName() !== null
            && ! in_array($route->getName(), self::NOT_PAGES, true);
    }
}
