<?php

namespace App\Http\Middleware;

use App\Cms\Cms;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Applies the CMS to each web request (docs/CMS.md §4.6, §13 A5): signed-in admins read fresh CMS
 * data (they see their edits at once) and the site settings edited in the CMS replace the .env
 * values in config('atelier.contact.*') / config('atelier.socials.*').
 *
 * "Signed in" is decided from the session alone, without any database query — never through
 * $request->user(), which would read the users table (and wait for an unreachable database) on
 * every public page.
 *
 * Machine endpoints without a session (photos, artworks, sitemap, robots) are registered without
 * this middleware: they show no contact details and must stay cheap.
 */
final class ApplyCms
{
    public function __construct(private readonly Cms $cms) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->cms->reset();

        if (self::signedIn($request)) {
            $this->cms->bypassCache();
        }

        $this->cms->applySettings();

        return $next($request);
    }

    /**
     * A user is signed in: the session holds the web guard's login key (or the guard already holds
     * a user, e.g. set by an earlier middleware or a test). No database query; never throws.
     */
    public static function signedIn(Request $request): bool
    {
        try {
            $guard = Auth::guard('web');

            if (! $guard instanceof SessionGuard) {
                return false;
            }

            return ($request->hasSession() && $request->session()->has($guard->getName())) || $guard->hasUser();
        } catch (Throwable) {
            return false;
        }
    }
}
