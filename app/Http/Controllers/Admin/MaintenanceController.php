<?php

namespace App\Http\Controllers\Admin;

use App\Artists\ArtistDirectory;
use App\Cms\Activity;
use App\Cms\DatabaseHealth;
use App\Cms\DatabaseMigrator;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Maintenance (docs/CMS.md §7.2, §13 E23/F30) — the page a fresh deployment sends the owner to.
 *
 * "Mettre à jour la base de données" runs the pending migrations (DatabaseMigrator::run(): `migrate
 * --force` under a lock, then the CMS cache is flushed): Vercel has no shell, this button is how the
 * production database gets its new tables. Also "Vider le cache du contenu" and the server's
 * technical details (collapsed). The route has no cms.ready: it works while the CMS tables are missing.
 *
 * The update log is kept 10 minutes in the database cache store and shown by the page the POST
 * redirects to: Vercel's 4 KB cookie session could not carry it, and a page rendered by the POST
 * itself would turn a reload into a 405.
 */
final class MaintenanceController extends Controller
{
    /** Session flash key holding the token of the last update's log. */
    private const LOG_FLASH = 'adm_maintenance_log';

    /** Cache key prefix of the logs (database store: shared by every server). */
    private const LOG_KEY = 'cms.maintenance.log.';

    private const LOG_SECONDS = 600;

    /** Longest log kept, in characters (the end of a failure's log is the useful part). */
    private const LOG_MAX = 20000;

    /** Longest database error shown in the technical details. */
    private const ERROR_MAX = 600;

    /** Lock of the fallback update (same name as DatabaseMigrator's). */
    private const LOCK = 'cms-migrate';

    /**
     * Migrations not run yet: DatabaseMigrator::pending() when it exists, else the framework
     * migrator's repository. When the migrations table does not exist yet, every migration file is
     * pending (the update creates the table). Never throws.
     *
     * @return array{pending: list<string>|null, error: string|null, outage: bool} pending = null when
     *                                                                             the database cannot tell
     */
    public static function migrationState(): array
    {
        try {
            if (class_exists(DatabaseMigrator::class)) {
                return ['pending' => app(DatabaseMigrator::class)->pending(), 'error' => null, 'outage' => false];
            }

            $migrator = app('migrator');

            return ['pending' => array_values(array_diff(array_keys(self::migrationFiles()), $migrator->getRepository()->getRan())), 'error' => null, 'outage' => false];
        } catch (Throwable $e) {
            if (rescue(fn (): bool => ! app('migrator')->repositoryExists(), false, false)) {
                return ['pending' => array_keys(self::migrationFiles()), 'error' => null, 'outage' => false];
            }

            return [
                'pending' => null,
                'error' => Str::limit(trim(get_class($e).': '.$e->getMessage()), self::ERROR_MAX),
                'outage' => class_exists(DatabaseHealth::class) && DatabaseHealth::isOutage($e),
            ];
        }
    }

    /** GET /admin/maintenance (admin.maintenance). */
    public function index(Request $request): View
    {
        $state = self::migrationState();

        return view('admin.maintenance.index', [
            'pending' => $state['pending'] === null ? null : array_map(self::describe(...), $state['pending']),
            'databaseError' => $state['error'],
            'outage' => $state['outage'],
            'log' => $this->pullLog($request),
            'environment' => $this->environment(),
        ]);
    }

    /** POST /admin/maintenance/base (admin.maintenance.migrate): "Mettre à jour la base de données". */
    public function migrate(Request $request): RedirectResponse
    {
        $before = self::migrationState()['pending'];
        $result = class_exists(DatabaseMigrator::class) ? app(DatabaseMigrator::class)->run() : $this->runMigrations();
        $this->flushArtists();

        $busy = __('admin.maintenance.migrations.busy');
        if (! $result['ok'] && $result['output'] === $busy) {
            return redirect()->route('admin.maintenance')->with('error', $busy);
        }

        // How many ran (unknown when the database could not tell before or after).
        $after = self::migrationState()['pending'];
        $applied = $before !== null && $after !== null ? max(0, count($before) - count($after)) : null;

        if ($result['ok']) {
            Activity::record('maintenance.migrate', $applied === null
                ? __('admin.maintenance.migrations.done')
                : trans_choice('admin.maintenance.activity.migrated', $applied, ['count' => $applied]));
            $redirect = redirect()->route('admin.maintenance')->with('status', $applied === null
                ? __('admin.maintenance.migrations.done')
                : trans_choice('admin.maintenance.migrations.applied', $applied, ['count' => $applied]));
        } else {
            Activity::record('maintenance.migrate_failed', __('admin.maintenance.activity.migrate_failed'));
            $redirect = redirect()->route('admin.maintenance')->with('error', __('admin.maintenance.migrations.failed'));
        }

        $token = $this->keepLog($result['ok'], $result['output']);

        return $token === null ? $redirect : $redirect->with(self::LOG_FLASH, $token);
    }

    /** POST /admin/maintenance/cache (admin.maintenance.flush): "Vider le cache du contenu". */
    public function flush(): RedirectResponse
    {
        cms()->flush();
        $this->flushArtists();

        // This request reached the database (the signed-in user was read from it): an outage marker is stale.
        if (class_exists(DatabaseHealth::class)) {
            rescue(fn () => app(DatabaseHealth::class)->recovered(), null, false);
        }

        Activity::record('maintenance.flush', __('admin.maintenance.activity.flushed'));

        return redirect()->route('admin.maintenance')->with('status', __('admin.maintenance.cache.done'));
    }

    /**
     * GET /admin/maintenance/export (admin.maintenance.export): the JSON export comes in phase 2
     * (admin-shell); until then a friendly note instead of a 501.
     */
    public function export(): RedirectResponse
    {
        return redirect()->route('admin.maintenance')->with('info', __('admin.maintenance.export.soon'));
    }

    /**
     * A migration name split for display: "2026_10_03_000001_add_crm_columns…" ⇒ id "2026_10_03_000001"
     * + label "add crm columns…" (words, so long names wrap on a phone).
     *
     * @return array{name: string, id: string|null, label: string}
     */
    private static function describe(string $name): array
    {
        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_(.+)$/', $name, $parts) === 1) {
            return ['name' => $name, 'id' => $parts[1], 'label' => str_replace('_', ' ', $parts[2])];
        }

        return ['name' => $name, 'id' => null, 'label' => str_replace('_', ' ', $name)];
    }

    /**
     * Migration files of the application (database/migrations and the paths packages registered).
     *
     * @return array<string, string> name ⇒ path
     */
    private static function migrationFiles(): array
    {
        $migrator = app('migrator');

        return $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
    }

    /**
     * The server's configuration, for the collapsed technical details (no secrets: drivers and names only).
     *
     * @return array<string, string>
     */
    private function environment(): array
    {
        $connection = (string) config('database.default');
        $cache = (string) config('cache.default');
        $store = fn (mixed $name): string => is_string($name) && $name !== ''
            ? $name
            : __('admin.maintenance.environment.default_store', ['store' => $cache]);

        return [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'environment' => (string) app()->environment(),
            'database' => (string) config('database.connections.'.$connection.'.driver', '—'),
            'connection' => $connection,
            'media' => (string) config('cms.media.driver', 'database'),
            'cache' => $cache,
            'cms_cache' => $store(config('cms.cache.store')),
            'limiter' => $store(config('cms.login_limiter_store')),
            'session' => (string) config('session.driver'),
            'url' => url('/'),
        ];
    }

    /** The artist pages keep their own cached copy (docs/ARTISTS.md): forgotten with the CMS one. */
    private function flushArtists(): void
    {
        if (class_exists(ArtistDirectory::class)) {
            rescue(fn () => app(ArtistDirectory::class)->flush(), null, false);
        }
    }

    /** Keeps an update's log for the next page; returns its token, or null when it cannot be kept. */
    private function keepLog(bool $ok, string $output): ?string
    {
        $output = trim($output);

        if ($output === '') {
            return null;
        }

        if (mb_strlen($output) > self::LOG_MAX) {
            $output = '…'.mb_substr($output, -self::LOG_MAX);
        }

        $token = Str::random(40);

        try {
            Cache::store('database')->put(self::LOG_KEY.$token, ['ok' => $ok, 'output' => $output], self::LOG_SECONDS);
        } catch (Throwable $e) {
            Log::notice('The database update log could not be kept: '.$e->getMessage());

            return null;
        }

        return $token;
    }

    /**
     * The log of the update this session just ran (once), or null.
     *
     * @return array{ok: bool, output: string}|null
     */
    private function pullLog(Request $request): ?array
    {
        $token = $request->hasSession() ? $request->session()->get(self::LOG_FLASH) : null;

        if (! is_string($token) || preg_match('/^[A-Za-z0-9]{40}$/', $token) !== 1) {
            return null;
        }

        $log = rescue(fn () => Cache::store('database')->pull(self::LOG_KEY.$token), null, false);

        return is_array($log) && is_string($log['output'] ?? null)
            ? ['ok' => ($log['ok'] ?? false) === true, 'output' => $log['output']]
            : null;
    }

    /**
     * The update without DatabaseMigrator (docs/CMS.md §13 E23): `migrate --force` under the same
     * lock, then the CMS cache is flushed. Never throws.
     *
     * @return array{ok: bool, output: string}
     */
    private function runMigrations(): array
    {
        $lock = null;

        try {
            $lock = Cache::store('database')->lock(self::LOCK, 120);

            if (! $lock->get()) {
                return ['ok' => false, 'output' => (string) __('admin.maintenance.migrations.busy')];
            }
        } catch (Throwable $e) {
            Log::warning('Database update runs without its lock: '.$e->getMessage());
            $lock = null;
        }

        try {
            $ok = Artisan::call('migrate', ['--force' => true]) === 0;
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            report($e);
            $ok = false;
            $output = $e->getMessage();
        } finally {
            if ($lock instanceof Lock) {
                rescue(fn () => $lock->release(), null, false);
            }
        }

        cms()->flush();

        return ['ok' => $ok, 'output' => $output];
    }
}
