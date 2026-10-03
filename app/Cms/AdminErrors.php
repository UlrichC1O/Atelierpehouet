<?php

namespace App\Cms;

use App\Cms\Media\MediaManager;
use App\Http\Middleware\ApplyCms;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\LostConnectionException;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Http\Request;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Error answers of the admin CMS registered in bootstrap/app.php (docs/CMS.md §13 A3, C10, D18):
 *  - a database error on an admin URL (unreachable database — e.g. a paused Supabase project —,
 *    pending migrations…) ⇒ a friendly French 503 page, never a 500;
 *  - a signed-in account without the admin right ⇒ the admin's 403 page, with a way to sign out;
 *  - a request over the server's size limit (PostTooLargeException) ⇒ a French 413 (JSON for the
 *    uploader's XHR, else a page with a way back).
 *
 * Both can happen before the session, locale and header middleware ran (or while the database they
 * need is down): the locale comes from the session when there is one, the security and admin headers
 * are set here, and nothing queries the database.
 */
final class AdminErrors
{
    /** Inputs never flashed back into the 4 KB cookie session (docs/CMS.md §13 E21; the artist pages' long texts included). */
    public const DONT_FLASH = [
        't', 'reset', 'content', 'body_fr', 'body_en', 'meta_fr', 'meta_en', 'alt_fr', 'alt_en', 'caption_fr', 'caption_en',
        'notes', 'announcement', 'bio_fr', 'bio_en', 'statement_fr', 'statement_en', 'description_fr', 'description_en',
    ];

    public static function isAdmin(Request $request): bool
    {
        return $request->is('admin', 'admin/*');
    }

    /** The database error behind $e (itself or one of its previous exceptions), or null. */
    public static function databaseError(Throwable $e): ?Throwable
    {
        for ($error = $e; $error !== null; $error = $error->getPrevious()) {
            if ($error instanceof QueryException || $error instanceof PDOException
                || $error instanceof SQLiteDatabaseDoesNotExistException || $error instanceof LostConnectionException) {
                return $error;
            }
        }

        return null;
    }

    /** The friendly 503 of an admin page that met a database error (opens the circuit breaker on an outage). */
    public static function unavailable(Request $request, Throwable $error): Response
    {
        app(DatabaseHealth::class)->failed($error);
        $outage = DatabaseHealth::isOutage($error);
        self::useSessionLocale($request);

        if ($request->expectsJson()) {
            $response = response()->json(['message' => __($outage ? 'admin.unavailable.json_outage' : 'admin.unavailable.json_error')], Response::HTTP_SERVICE_UNAVAILABLE);
        } else {
            $response = response()->view('admin.errors.unavailable', [
                'outage' => $outage,
                'retryUrl' => $request->isMethod('GET') ? $request->fullUrl() : self::previousAdminUrl($request),
                'signedIn' => self::signedIn($request),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return self::secure($response);
    }

    /** The 403 of a signed-in account without the admin right: a page to sign out from. */
    public static function forbidden(Request $request): Response
    {
        self::useSessionLocale($request);

        $response = $request->expectsJson()
            ? response()->json(['message' => __('admin.forbidden.json')], Response::HTTP_FORBIDDEN)
            : response()->view('admin.errors.forbidden', ['signedIn' => self::signedIn($request)], Response::HTTP_FORBIDDEN);

        return self::secure($response);
    }

    /** The French 413 of an admin request over the size limit. */
    public static function tooLarge(Request $request): Response
    {
        self::useSessionLocale($request);
        $limit = self::uploadLimit();
        $size = $limit === null ? null : __('admin.too_large.unit', ['size' => self::megabytes($limit)]);

        if ($request->expectsJson()) {
            $response = response()->json([
                'message' => $size === null ? __('admin.too_large.json_unknown') : __('admin.too_large.json', ['size' => $size]),
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        } else {
            $response = response()->view('admin.errors.too-large', [
                'size' => $size,
                'back' => self::previousAdminUrl($request),
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return self::secure($response);
    }

    /**
     * Largest request body this server accepts, in bytes: the media pipeline's effective upload limit
     * when it exposes one (docs/CMS.md §13 C10), else PHP's post_max_size; null when unknown.
     */
    public static function uploadLimit(): ?int
    {
        if (method_exists(MediaManager::class, 'maxUploadBytes')) {
            $limit = rescue(fn () => app(MediaManager::class)->maxUploadBytes(), null, false);

            if (is_int($limit) && $limit > 0) {
                return $limit;
            }
        }

        $bytes = self::iniBytes((string) ini_get('post_max_size'));

        return $bytes > 0 ? $bytes : null;
    }

    /** "8M" ⇒ 8388608 (php.ini shorthand). */
    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /** Bytes as megabytes for people: 4000000 ⇒ "4", 5242880 ⇒ "5,2" (French decimal comma). */
    private static function megabytes(int $bytes): string
    {
        $value = rtrim(rtrim(number_format($bytes / 1000000, 1, '.', ''), '0'), '.');

        return app()->getLocale() === 'fr' ? str_replace('.', ',', $value) : $value;
    }

    /** The admin page the request came from (same site only), else the dashboard. */
    private static function previousAdminUrl(Request $request): string
    {
        $fallback = route('admin.dashboard');
        $previous = SafeUrl::internal($request->headers->get('referer'), $fallback);

        return str_starts_with($previous, url('/admin')) ? $previous : $fallback;
    }

    /** Signed in, decided without a query (the database may be the problem). */
    private static function signedIn(Request $request): bool
    {
        return ApplyCms::signedIn($request);
    }

    /** The admin's language when the session already knows it (SetLocale may not have run). */
    private static function useSessionLocale(Request $request): void
    {
        try {
            $locale = $request->hasSession() ? $request->session()->get('locale') : null;
        } catch (Throwable) {
            return;
        }

        if (is_string($locale) && array_key_exists($locale, (array) config('atelier.locales', []))) {
            app()->setLocale($locale);
        }
    }

    /** The headers the middleware would have added: security, CSP, admin (noindex, no-store). */
    private static function secure(Response $response): Response
    {
        foreach (SecurityHeaders::HEADERS + ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store, private'] as $name => $value) {
            $response->headers->set($name, $value);
        }

        if (! $response->headers->has('Content-Security-Policy') && ! $response->headers->has('Content-Security-Policy-Report-Only')) {
            $response->headers->set(config('app.debug') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy', SecurityHeaders::CONTENT_SECURITY_POLICY);
        }

        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
