<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the visitor's language on every web request (docs/ARCHITECTURE.md §10):
 * ?lang=fr|en (remembered in the session) ⇒ session('locale') ⇒ the default locale,
 * i.e. the first of config('atelier.locales'). Only those locales are accepted.
 *
 * (config('app.locale') is not used as the default: App::setLocale() overwrites it.)
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        $response = $next($request);

        if (! $response->headers->has('Content-Language')) {
            $response->headers->set('Content-Language', $locale);
        }

        return $response;
    }

    private function resolve(Request $request): string
    {
        $session = $request->hasSession() ? $request->session() : null;
        $requested = $request->query('lang');

        if ($this->supported($requested)) {
            $session?->put('locale', $requested);

            return $requested;
        }

        $stored = $session?->get('locale');

        if ($this->supported($stored)) {
            return $stored;
        }

        return self::defaultLocale();
    }

    /** The site's default language: the first locale of config('atelier.locales'). */
    public static function defaultLocale(): string
    {
        return (string) (array_key_first((array) config('atelier.locales', [])) ?? 'fr');
    }

    /**
     * @phpstan-assert-if-true string $locale
     */
    private function supported(mixed $locale): bool
    {
        return is_string($locale) && array_key_exists($locale, $this->locales());
    }

    /**
     * @return array<string, string>
     */
    private function locales(): array
    {
        return (array) config('atelier.locales', ['fr' => 'Français']);
    }
}
