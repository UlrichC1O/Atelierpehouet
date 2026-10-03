<?php

namespace Tests\Feature\Cms;

use App\Cms\DatabaseHealth;
use App\Cms\DatabaseMigrator;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * The database updates of the admin (docs/CMS.md §13 E23): the pending migrations (one query,
 * memoized), the admin screens waiting for every 2026_10_03_* migration, and "Mettre à jour la base
 * de données" running `migrate --force` under a lock, then dropping every cached CMS read.
 */
class DatabaseMigratorTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    private function migrator(): DatabaseMigrator
    {
        return app(DatabaseMigrator::class);
    }

    public function test_nothing_is_pending_once_migrated(): void
    {
        $this->assertSame([], $this->migrator()->pending());
        $this->assertSame([], $this->migrator()->pendingForCms());
    }

    public function test_every_cms_migration_on_disk_is_required_including_the_artist_pages(): void
    {
        $files = array_map(fn (string $path): string => basename($path, '.php'), glob(database_path('migrations/2026_10_03_*.php')));
        $this->assertContains('2026_10_03_000020_add_is_admin_to_users_table', $files);

        DB::table('migrations')->where('migration', 'like', '2026_10_03_%')->delete();
        DB::table('migrations')->where('migration', '0001_01_01_000002_create_jobs_table')->delete();
        $this->migrator()->reset();

        $this->assertSame($files, $this->migrator()->pendingForCms());
        $this->assertSame(['0001_01_01_000002_create_jobs_table', ...$files], $this->migrator()->pending());
    }

    public function test_the_admin_check_is_one_memoized_query(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->migrator()->pendingForCms();
        $this->migrator()->pending();
        $this->migrator()->pendingForCms();

        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_admin_screens_wait_for_a_pending_cms_migration(): void
    {
        $this->admin();
        DB::table('migrations')->where('migration', '2026_10_03_000022_insert_legal_pages')->delete();

        $this->get('/admin')->assertRedirect(route('admin.maintenance'))->assertSessionHas('error', 'admin.maintenance.tables_missing');
        $this->getJson('/admin/photos')->assertStatus(503)->assertJsonPath('missing', ['2026_10_03_000022_insert_legal_pages']);
        $this->assertNotSame(route('admin.maintenance'), $this->get('/admin/maintenance')->headers->get('Location'));
    }

    public function test_an_unreadable_migrations_table_sends_the_admin_to_maintenance(): void
    {
        $this->admin();
        config(['database.migrations.table' => 'table_des_migrations_absente']);
        app()->forgetInstance('migration.repository');
        app()->forgetInstance('migrator');

        $this->get('/admin')->assertRedirect(route('admin.maintenance'));
    }

    public function test_run_migrates_flushes_the_cms_and_closes_the_breaker(): void
    {
        $this->useSerializingCmsCache();
        $this->assertTrue(cms()->available());
        Setting::query()->getQuery()->insert(['key' => 'contact.phone', 'value' => '01 02 03 04 05']);
        app(DatabaseHealth::class)->failed(new RuntimeException('could not connect'));

        $result = $this->migrator()->run();

        $this->assertTrue($result['ok']);
        $this->assertIsString($result['output']);
        $this->assertNull(Cache::store('cms_serializing')->get(config('cms.cache.key')), 'the CMS cache was flushed');
        $this->assertSame('01 02 03 04 05', cms()->setting('contact.phone'));
        $this->assertTrue(app(DatabaseHealth::class)->available(), 'the database answered: breaker closed');
    }

    public function test_run_applies_pending_migrations(): void
    {
        $migration = require database_path('migrations/2026_10_03_000021_add_versions_to_cms_activity_table.php');
        $migration->down();
        DB::table('migrations')->where('migration', '2026_10_03_000021_add_versions_to_cms_activity_table')->delete();
        $this->migrator()->reset();
        $this->assertSame(['2026_10_03_000021_add_versions_to_cms_activity_table'], $this->migrator()->pendingForCms());

        $result = $this->migrator()->run();

        $this->assertTrue($result['ok'], $result['output']);
        $this->assertStringContainsString('2026_10_03_000021_add_versions_to_cms_activity_table', $result['output']);
        $this->assertSame([], $this->migrator()->pendingForCms());
    }

    public function test_two_updates_never_run_at_once(): void
    {
        $lock = Cache::store('database')->lock('cms-migrate', 120);
        $this->assertTrue($lock->get());

        $result = $this->migrator()->run();

        $this->assertFalse($result['ok']);
        $this->assertSame(__('admin.maintenance.migrations.busy'), $result['output']);
        $lock->release();
        $this->assertTrue($this->migrator()->run()['ok']);
    }
}
