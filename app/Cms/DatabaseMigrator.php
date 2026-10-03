<?php

namespace App\Cms;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The database updates of the admin (docs/CMS.md §13 E23), a container singleton.
 *
 * pending() lists the migration files not run yet (one query on the migrations table, memoized for
 * the request); the admin screens need every CMS migration — every database/migrations/2026_10_03_*
 * file, the artist pages' included — before they open (App\Http\Middleware\EnsureCmsReady). run() is
 * the "Mettre à jour la base de données" button of Maintenance (and the first-login bootstrap):
 * `migrate --force` under a lock, so two clicks never migrate at the same time.
 */
final class DatabaseMigrator
{
    /** Prefix of the migrations the admin CMS (and its modules) depends on. */
    public const CMS_PREFIX = '2026_10_03_';

    /** Lock held while migrating (database cache store: shared by every server). */
    private const LOCK = 'cms-migrate';

    private const LOCK_SECONDS = 120;

    /** @var list<string>|null migrations already run, as read during this request */
    private ?array $ran = null;

    /**
     * Migration files not run yet, oldest first. Throws when the migrations table cannot be read
     * (database unreachable, nothing migrated yet).
     *
     * @return list<string> migration names (file names without .php)
     */
    public function pending(): array
    {
        $this->ran ??= array_map('strval', app('migrator')->getRepository()->getRan());

        return array_values(array_diff(array_keys($this->files()), $this->ran));
    }

    /**
     * Pending migrations the admin screens depend on (2026_10_03_*).
     *
     * @return list<string>
     */
    public function pendingForCms(): array
    {
        return array_values(array_filter($this->pending(), fn (string $name): bool => str_starts_with($name, self::CMS_PREFIX)));
    }

    /**
     * Runs `php artisan migrate --force`, then drops every cached CMS read. Never throws.
     *
     * @return array{ok: bool, output: string}
     */
    public function run(): array
    {
        $lock = $this->lock();

        if ($lock === false) {
            return ['ok' => false, 'output' => (string) __('admin.maintenance.migrations.busy')];
        }

        try {
            $ok = Artisan::call('migrate', ['--force' => true]) === 0;
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            report($e);
            $ok = false;
            $output = $e->getMessage();
        } finally {
            if ($lock !== null) {
                rescue(fn () => $lock->release(), null, false);
            }
        }

        $this->ran = null;
        cms()->flush();

        if ($ok) {
            app(DatabaseHealth::class)->recovered();
        }

        return ['ok' => $ok, 'output' => $output];
    }

    /** Forgets the migrations read during the previous request. */
    public function reset(): void
    {
        $this->ran = null;
    }

    /**
     * Migration files of the application (database/migrations and the paths packages registered).
     *
     * @return array<string, string> name ⇒ path
     */
    private function files(): array
    {
        $migrator = app('migrator');

        return $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
    }

    /**
     * The migration lock: a Lock when acquired, false when another update is running, null when the
     * lock store is unusable (e.g. a database without its cache tables yet) — then it runs unlocked.
     */
    private function lock(): Lock|false|null
    {
        try {
            $lock = Cache::store('database')->lock(self::LOCK, self::LOCK_SECONDS);

            return $lock->get() ? $lock : false;
        } catch (Throwable $e) {
            Log::warning('Database update runs without its lock: '.$e->getMessage());

            return null;
        }
    }
}
