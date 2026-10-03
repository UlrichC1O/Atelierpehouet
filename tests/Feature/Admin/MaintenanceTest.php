<?php

namespace Tests\Feature\Admin;

use App\Cms\DatabaseMigrator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithCms;
use Tests\Feature\Admin\Concerns\AddsLaterMigrations;
use Tests\TestCase;

/**
 * Maintenance (docs/CMS.md §7.2, §13 E23/F30): the database state and its update button, the
 * content cache, the technical details and the export placeholder.
 */
class MaintenanceTest extends TestCase
{
    use AddsLaterMigrations, InteractsWithCms, RefreshDatabase;

    public function test_the_page_shows_an_up_to_date_database_and_collapsed_technical_details(): void
    {
        $this->admin();

        $response = $this->get('/admin/maintenance')->assertOk()
            ->assertSee(__('admin.maintenance.title'))
            ->assertSee(__('admin.maintenance.migrations.none'))
            ->assertSee(route('admin.dashboard'), false)
            ->assertDontSee(route('admin.maintenance.migrate'), false)
            ->assertSee(route('admin.maintenance.flush'), false)
            ->assertSee(route('admin.maintenance.export'), false)
            ->assertSee(__('admin.maintenance.export.title'));

        // Technical details: collapsed, drivers and names only.
        $this->assertMatchesRegularExpression('/<details class="accordion"\s*>/', (string) $response->getContent());
        $response->assertSee(__('admin.maintenance.environment.title'))
            ->assertSee(PHP_VERSION)
            ->assertSee(config('database.connections.'.config('database.default').'.driver'))
            ->assertSee(__('admin.maintenance.environment.session'))
            ->assertSee(__('admin.maintenance.environment.cms_cache'))
            ->assertSee(__('admin.maintenance.environment.default_store', ['store' => config('cache.default')]))
            ->assertSee(__('admin.maintenance.environment.limiter'))
            ->assertSee((string) config('cms.login_limiter_store'))
            ->assertSee(__('admin.maintenance.environment.media'))
            ->assertDontSee((string) config('app.key'));
    }

    public function test_the_migrations_of_a_new_release_are_listed_with_the_update_button(): void
    {
        $this->admin();
        $this->laterMigration();

        $this->get('/admin/maintenance')->assertOk()
            ->assertSee(trans_choice('admin.maintenance.migrations.pending', 1, ['count' => 1]))
            ->assertSee('2099_01_01_000000')
            ->assertSee('create later release notes table')
            ->assertSee(route('admin.maintenance.migrate'), false)
            ->assertSee(__('admin.maintenance.migrations.run'));
    }

    public function test_the_update_applies_them_shows_its_log_once_and_flushes_the_content(): void
    {
        $owner = $this->admin();
        $this->useSerializingCmsCache();
        $this->assertTrue(cms()->available());
        $this->assertTrue(Cache::store('cms_serializing')->has(config('cms.cache.key')));
        $this->laterMigration();

        $this->post('/admin/maintenance/base')
            ->assertRedirect(route('admin.maintenance'))
            ->assertSessionHas('status', trans_choice('admin.maintenance.migrations.applied', 1, ['count' => 1]));

        $this->assertTrue(Schema::hasTable('later_release_notes'));
        $this->assertSame([], app(DatabaseMigrator::class)->pending());
        $this->assertFalse(Cache::store('cms_serializing')->has(config('cms.cache.key')), 'the content cache was flushed');
        $this->assertDatabaseHas('cms_activity', ['action' => 'maintenance.migrate', 'user_id' => $owner->id]);

        $this->get('/admin/maintenance')->assertOk()
            ->assertSee(__('admin.maintenance.migrations.none'))
            ->assertSee(__('admin.maintenance.migrations.output'))
            ->assertSee(self::LATER);

        $this->get('/admin/maintenance')->assertOk()->assertDontSee(self::LATER);
    }

    public function test_an_up_to_date_database_is_said_so(): void
    {
        $this->admin();

        $this->post('/admin/maintenance/base')
            ->assertRedirect(route('admin.maintenance'))
            ->assertSessionHas('status', trans_choice('admin.maintenance.migrations.applied', 0, ['count' => 0]));
    }

    public function test_a_failed_update_says_so_and_keeps_its_log(): void
    {
        $this->admin();
        $this->laterMigration('La table existe déjà');

        $this->post('/admin/maintenance/base')
            ->assertRedirect(route('admin.maintenance'))
            ->assertSessionHas('error', __('admin.maintenance.migrations.failed'));

        $this->assertSame([self::LATER], app(DatabaseMigrator::class)->pending());
        $this->assertDatabaseHas('cms_activity', ['action' => 'maintenance.migrate_failed']);

        $this->get('/admin/maintenance')->assertOk()
            ->assertSee(__('admin.maintenance.migrations.failed_lead'))
            ->assertSee('La table existe déjà')
            ->assertSee(__('admin.maintenance.migrations.run'));
    }

    public function test_a_long_log_never_goes_into_the_cookie_session(): void
    {
        config(['session.driver' => 'cookie']);
        $this->admin();
        $this->laterMigration(str_repeat('Une très longue erreur de la base de données. ', 200));

        $response = $this->post('/admin/maintenance/base')->assertRedirect(route('admin.maintenance'));

        $this->assertNotEmpty($response->headers->getCookies());
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertLessThan(4096, strlen((string) $cookie), $cookie->getName().' fits in a browser cookie');
        }

        $this->get('/admin/maintenance')->assertOk()->assertSee('Une très longue erreur');
    }

    public function test_two_updates_never_run_at_once(): void
    {
        $this->admin();
        $this->laterMigration();
        $lock = Cache::store('database')->lock('cms-migrate', 120);
        $this->assertTrue($lock->get());

        $this->post('/admin/maintenance/base')
            ->assertRedirect(route('admin.maintenance'))
            ->assertSessionHas('error', __('admin.maintenance.migrations.busy'));

        $this->assertFalse(Schema::hasTable('later_release_notes'));
        $lock->release();
    }

    public function test_the_update_is_post_only_and_for_administrators(): void
    {
        $this->post('/admin/maintenance/base')->assertRedirect(route('admin.login'));
        $this->post('/admin/maintenance/cache')->assertRedirect(route('admin.login'));

        $this->admin(['is_admin' => false]);
        $this->post('/admin/maintenance/base')->assertForbidden();
        $this->get('/admin/maintenance')->assertForbidden();

        // A link or a prefetch never updates the database.
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->laterMigration();
        $this->assertContains($this->get('/admin/maintenance/base')->getStatusCode(), [404, 405]);
        $this->assertSame([self::LATER], app(DatabaseMigrator::class)->pending());
    }

    public function test_flushing_the_content_cache(): void
    {
        $owner = $this->admin();
        $this->useSerializingCmsCache();
        $this->assertTrue(cms()->available());
        $this->assertTrue(Cache::store('cms_serializing')->has(config('cms.cache.key')));

        $this->post('/admin/maintenance/cache')
            ->assertRedirect(route('admin.maintenance'))
            ->assertSessionHas('status', __('admin.maintenance.cache.done'));

        $this->assertFalse(Cache::store('cms_serializing')->has(config('cms.cache.key')));
        $this->assertDatabaseHas('cms_activity', ['action' => 'maintenance.flush', 'user_id' => $owner->id]);
    }

    public function test_the_export_answers_with_a_friendly_note_until_it_is_built(): void
    {
        $this->admin();

        $this->get('/admin/maintenance/export')
            ->assertRedirect(route('admin.maintenance'))
            ->assertSessionHas('info', __('admin.maintenance.export.soon'));

        $this->get('/admin/maintenance')->assertOk()->assertSee(__('admin.maintenance.export.soon'));
    }

    public function test_without_a_migrations_table_every_migration_is_pending(): void
    {
        $this->admin();
        config(['database.migrations.table' => 'table_des_migrations_absente']);
        app()->forgetInstance('migration.repository');
        app()->forgetInstance('migrator');
        app(DatabaseMigrator::class)->reset();

        $count = count(glob(database_path('migrations/*.php')) ?: []);

        $this->get('/admin/maintenance')->assertOk()
            ->assertSee(trans_choice('admin.maintenance.migrations.pending', $count, ['count' => $count]))
            ->assertSee(route('admin.maintenance.migrate'), false);
    }

    public function test_an_unreachable_database_still_opens_the_page(): void
    {
        $this->admin();
        $this->breakDatabase();

        $response = $this->get('/admin/maintenance')->assertOk()
            ->assertSee(__('admin.maintenance.migrations.unknown'))
            ->assertSee(__('admin.maintenance.migrations.unknown_outage'))
            ->assertSee(__('admin.maintenance.environment.database_error'));

        // The technical details open by themselves to show the error.
        $this->assertMatchesRegularExpression('/<details class="accordion"\s+open\s*>/', (string) $response->getContent());
    }

    public function test_the_page_is_in_english_too(): void
    {
        $this->admin();

        $this->get('/admin/maintenance?lang=en')->assertOk()
            ->assertSee(__('admin.maintenance.migrations.none', [], 'en'))
            ->assertSee(__('admin.maintenance.environment.title', [], 'en'));
    }
}
