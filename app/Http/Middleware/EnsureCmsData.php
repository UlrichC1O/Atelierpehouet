<?php

namespace App\Http\Middleware;

use App\Cms\Cms;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * URLs only the CMS knows — free pages (/{slug}), services created in the CMS (/services/{slug})
 * and the sitemap — while the CMS is blind (database unreachable, no cached copy, docs/CMS.md §13
 * A3): "not found" would be a lie, so they answer 503 + Retry-After: 300 + Cache-Control: no-store
 * (the public 503 page) instead of a 404 crawlers and caches would remember.
 *
 * Route middleware "cms.data"; "cms.data:always" (the sitemap) answers 503 before the controller
 * runs, otherwise only a 404 is replaced.
 */
final class EnsureCmsData
{
    /** Seconds after which crawlers may come back. */
    public const RETRY_AFTER = 300;

    public function __construct(private readonly Cms $cms) {}

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        if ($mode === 'always' && $this->cms->blind()) {
            self::unavailable();
        }

        $response = $next($request);

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND && $this->cms->blind()) {
            self::unavailable();
        }

        return $response;
    }

    /** Rendered by the exception handler with the public 503 page (errors/503.blade.php). */
    private static function unavailable(): never
    {
        throw new HttpException(Response::HTTP_SERVICE_UNAVAILABLE, 'The CMS data cannot be read right now.', null, [
            'Retry-After' => (string) self::RETRY_AFTER,
            'Cache-Control' => 'no-store',
        ]);
    }
}
