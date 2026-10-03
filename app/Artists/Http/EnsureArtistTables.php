<?php

namespace App\Artists\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Sends the owner to the maintenance page while the artist tables are missing (migration pending),
 * where "Mettre à jour la base de données" creates them (docs/ARTISTS.md §4.6). Used by the admin
 * controllers of the artist pages (HasMiddleware); App\Models\Artist::resolveRouteBinding() gives the
 * same answer for the routes with an {artist} binding (bindings run before controller middleware).
 */
final class EnsureArtistTables
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::ready() ? $next($request) : self::unavailable($request);
    }

    /** True when the artist tables exist (false when the database cannot tell). */
    public static function ready(): bool
    {
        try {
            return Schema::hasTable('artists');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The answer while the tables are missing: a redirect to the maintenance page with an "error"
     * flash, or a 503 JSON {message} for scripts (the photo uploader).
     */
    public static function unavailable(Request $request): Response
    {
        $message = (string) __('admin_artists.errors.migrate');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! Route::has('admin.maintenance')) {
            return response($message, Response::HTTP_SERVICE_UNAVAILABLE, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return redirect()->route('admin.maintenance')->with('error', $message);
    }
}
