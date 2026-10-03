<?php

namespace Tests\Feature\Admin;

use App\Cms\DatabaseMigrator;
use App\Models\ContactMessage;
use App\Models\CustomPage;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * The first deployment of the admin to production (docs/CMS.md §2, §13 D18/E23/F30), end to end.
 *
 * Production (Vercel + Supabase) already has the base Laravel tables, the contact requests and the
 * owner as user #1 — without the is_admin column and without any CMS table. The owner signs in, is
 * sent to Maintenance, clicks "Mettre à jour la base de données", becomes an administrator through
 * the migration and can then open every admin screen; the public site answers 200 all along.
 *
 * No RefreshDatabase: each test starts from its own in-memory database holding the base tables only.
 */
class ProductionPathTest extends TestCase
{
    use InteractsWithCms;

    /** What production's database held before the admin CMS. */
    private const BASE_MIGRATIONS = [
        '0001_01_01_000000_create_users_table',
        '0001_01_01_000001_create_cache_table',
        '0001_01_01_000002_create_jobs_table',
        '2026_10_02_000000_create_contact_messages_table',
    ];

    private const PUBLIC_PAGES = ['/', '/services', '/services/peinture-murale', '/a-propos', '/galerie', '/contact'];

    private const EMAIL = 'owner@pehouet.test';

    private const PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => array_map(fn (string $name): string => database_path('migrations/'.$name.'.php'), self::BASE_MIGRATIONS),
            '--realpath' => true,
        ])->assertSuccessful();

        app(DatabaseMigrator::class)->reset();
        cms()->flush();
    }

    public function test_the_owner_updates_the_production_database_and_reaches_every_admin_screen(): void
    {
        // Production before the first update: user #1 has no admin column, there are no CMS tables.
        $this->assertFalse(Schema::hasColumn('users', 'is_admin'));
        $this->assertFalse(Schema::hasTable('translation_overrides'));
        $owner = User::factory()->create(['name' => 'Awa Pehouet', 'email' => self::EMAIL, 'password' => self::PASSWORD]);
        $this->assertArrayNotHasKey('is_admin', $owner->fresh()->getAttributes());
        DB::table('contact_messages')->insert([
            'name' => 'Client', 'email' => 'client@pehouet.test', 'message' => 'Bonjour', 'locale' => 'fr',
            'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
        ]);

        $this->assertPublicPagesRender('before the update');
        $this->assertFalse(cms()->available(), 'no CMS table yet: the files are the content');

        // Sign in: the admin screens wait for the database update and send the owner to Maintenance.
        $this->post('/admin/connexion', ['email' => self::EMAIL, 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($owner);
        $this->get('/admin')->assertRedirect(route('admin.maintenance'));
        $this->get('/admin/photos')->assertRedirect(route('admin.maintenance'));

        $expected = $this->cmsMigrationNames();
        $this->assertNotEmpty($expected);
        $page = $this->get('/admin/maintenance')->assertOk()
            ->assertSee(trans_choice('admin.maintenance.migrations.pending', count($expected), ['count' => count($expected)]))
            ->assertSee(route('admin.maintenance.migrate'), false)
            ->assertSee(__('admin.maintenance.migrations.run'))
            ->assertSee(__('admin.maintenance.environment.title'));
        foreach ($expected as $name) {
            $page->assertSee(str_replace('_', ' ', substr($name, 18)));
        }

        // "Mettre à jour la base de données".
        $this->post('/admin/maintenance/base')
            ->assertRedirect(route('admin.maintenance'))
            ->assertSessionHas('status', trans_choice('admin.maintenance.migrations.applied', count($expected), ['count' => count($expected)]));

        $this->assertSame([], app(DatabaseMigrator::class)->pending());
        foreach (['translation_overrides', 'media', 'media_files', 'media_slots', 'cms_services', 'settings', 'custom_pages', 'cms_activity'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
        $this->assertTrue($owner->fresh()->is_admin, 'the update made the existing owner an administrator');
        $this->assertSame('new', DB::table('contact_messages')->value('status'), 'existing requests join the inbox');
        $this->assertDatabaseHas('cms_activity', ['action' => 'maintenance.migrate', 'user_id' => $owner->id]);

        $this->get('/admin/maintenance')->assertOk()
            ->assertSee(__('admin.maintenance.migrations.none'))
            ->assertSee(__('admin.maintenance.migrations.output'))
            ->assertSee($expected[0]);

        // The next request reads the account again, now with its admin right.
        $this->app['auth']->forgetGuards();
        $this->get('/admin')->assertOk()->assertSee('Awa Pehouet')->assertDontSee('id="dashboard-pending"', false);
        $this->assertTrue(auth()->user()->is_admin);

        foreach ($this->adminGetUrls() as $name => $url) {
            $response = $this->get($url);

            if ($name === 'admin.maintenance.export' && $response->isRedirect()) {
                // Built in phase 2 (admin-shell); a friendly note until then.
                $response->assertRedirect(route('admin.maintenance'))->assertSessionHas('info');

                continue;
            }

            $this->assertSame(200, $response->getStatusCode(), $name.' ('.$url.') answered '.$response->getStatusCode());
        }

        $this->assertPublicPagesRender('after the update, signed in');

        $this->post('/admin/deconnexion')->assertRedirect(route('admin.login'));
        $this->assertGuest();
        $this->assertPublicPagesRender('after the update, as a visitor');
        $this->assertTrue(cms()->available(), 'the CMS reads the database once it is up to date');
    }

    public function test_a_later_release_with_a_new_migration_keeps_the_admin_usable_until_the_update(): void
    {
        $this->artisan('migrate')->assertSuccessful();
        $owner = User::factory()->create(['name' => 'Awa Pehouet', 'email' => self::EMAIL, 'password' => self::PASSWORD]);
        $this->assertTrue($owner->fresh()->is_admin === false, 'accounts created after the update are not administrators by default');
        $owner->forceFill(['is_admin' => true])->save();

        // A CMS migration of a later release is not applied yet.
        DB::table('migrations')->where('migration', '2026_10_03_000022_insert_legal_pages')->delete();
        app(DatabaseMigrator::class)->reset();
        CustomPage::query()->delete();

        $this->post('/admin/connexion', ['email' => self::EMAIL, 'password' => self::PASSWORD])->assertRedirect(route('admin.dashboard'));
        $this->get('/admin')->assertRedirect(route('admin.maintenance'));
        $this->get('/admin/maintenance')->assertOk()->assertSee(trans_choice('admin.maintenance.migrations.pending', 1, ['count' => 1]));

        $this->post('/admin/maintenance/base')->assertRedirect(route('admin.maintenance'));

        $this->assertSame(2, CustomPage::query()->count(), 'the legal pages were inserted');
        $this->get('/admin')->assertOk();
        $this->assertPublicPagesRender('after a later update');
    }

    /**
     * Every public page answers 200 in French and in English.
     */
    private function assertPublicPagesRender(string $moment): void
    {
        foreach (self::PUBLIC_PAGES as $path) {
            foreach (['', '?lang=en'] as $query) {
                $response = $this->get($path.$query);
                $this->assertSame(200, $response->getStatusCode(), $path.$query.' '.$moment.': '.$this->excerpt($response));
            }
        }

        // Back to French for the next requests (?lang= is remembered in the session).
        $this->get('/?lang=fr')->assertOk();
    }

    /**
     * Migration files the first update applies (everything but the base tables).
     *
     * @return list<string>
     */
    private function cmsMigrationNames(): array
    {
        $names = array_map(fn (string $path): string => basename($path, '.php'), glob(database_path('migrations/*.php')) ?: []);
        sort($names);

        return array_values(array_diff($names, self::BASE_MIGRATIONS));
    }

    /**
     * The URL of every signed-in admin GET route, with real records for its parameters (routes of
     * modules whose records do not exist are skipped).
     *
     * @return array<string, string> route name ⇒ URL
     */
    private function adminGetUrls(): array
    {
        $message = ContactMessage::query()->firstOrFail();
        $values = [
            'group' => 'home',
            'slug' => 'peinture-murale',
            'media' => $this->uploadPhoto(['in_gallery' => true])->id,
            'page' => CustomPage::query()->value('id'),
            'message' => $message->id,
            'user' => auth()->id(),
        ];

        foreach (['artist' => 'App\\Models\\Artist', 'artwork' => 'App\\Models\\Artwork', 'exhibition' => 'App\\Models\\Exhibition'] as $parameter => $model) {
            $values[$parameter] = class_exists($model) ? rescue(fn () => $model::query()->value('id'), null, false) : null;
        }

        $urls = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'admin.') || ! in_array('GET', $route->methods(), true) || in_array('guest', $route->gatherMiddleware(), true)) {
                continue;
            }

            $parameters = [];
            foreach ($route->parameterNames() as $parameter) {
                $parameters[$parameter] = $values[$parameter] ?? null;
            }

            if (in_array(null, $parameters, true)) {
                continue;
            }

            $urls[$name] = route($name, $parameters);
        }

        // The screens this deployment must open (other modules' routes come on top).
        foreach (['admin.dashboard', 'admin.maintenance', 'admin.texts.index', 'admin.texts.edit', 'admin.services.index', 'admin.services.edit', 'admin.media.index', 'admin.media.edit', 'admin.gallery.index', 'admin.pages.index', 'admin.pages.edit', 'admin.messages.index', 'admin.messages.show', 'admin.settings.edit', 'admin.account.edit', 'admin.maintenance.export'] as $required) {
            $this->assertArrayHasKey($required, $urls);
        }

        return $urls;
    }

    private function excerpt(TestResponse $response): string
    {
        $content = (string) $response->getContent();

        return $response->exception?->getMessage() ?? mb_substr(strip_tags($content), 0, 300);
    }
}
