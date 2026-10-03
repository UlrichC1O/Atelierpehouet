<?php

namespace App\Http\Middleware;

use App\Cms\DatabaseMigrator;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use PDOException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin screens need the whole CMS schema (docs/CMS.md §4.6, §13 E23): until every
 * database/migrations/2026_10_03_* migration has run (the artist pages' included, on purpose) —
 * e.g. right after a deployment — admins are sent to the maintenance page, whose button updates the
 * database. One query on the migrations table (DatabaseMigrator, memoized per request). It runs
 * before SubstituteBindings (bootstrap/app.php), so a missing table never breaks a model binding
 * first. Login, logout and maintenance routes do not use this middleware.
 */
final class EnsureCmsReady
{
    private const MESSAGE = 'admin.maintenance.tables_missing';

    public function __construct(private readonly DatabaseMigrator $migrator) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $pending = $this->migrator->pendingForCms();
        } catch (QueryException|PDOException) {
            $pending = null; // no migrations table yet, or the database is unreachable: Maintenance tells
        }

        if ($pending === []) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            $locale = $request->hasSession() ? $request->session()->get('locale') : null;
            $message = __(self::MESSAGE, [], is_string($locale) && array_key_exists($locale, (array) config('atelier.locales', [])) ? $locale : null);

            return response()->json(['message' => $message, 'missing' => $pending ?? []], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // The key, translated when the flash is shown: this runs before SetLocale.
        return redirect()->route('admin.maintenance')->with('error', self::MESSAGE);
    }
}
