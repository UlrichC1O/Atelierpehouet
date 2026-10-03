<?php

namespace Tests\Feature\Cms;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * Who may use the admin (docs/CMS.md §13 D17–D20): the explicit admin right (users.is_admin,
 * Gate "admin"), sessions tied to the password (auth.session), 30-day "remember me", the password
 * reset and CSRF token routes.
 */
class AdminAccessTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    private function isAdminMigration(): object
    {
        return require database_path('migrations/2026_10_03_000020_add_is_admin_to_users_table.php');
    }

    public function test_the_migration_makes_every_existing_account_an_administrator(): void
    {
        $migration = $this->isAdminMigration();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'is_admin'));

        $owner = User::factory()->create(['email' => 'owner@pehouet.test']);
        $other = User::factory()->create();

        $migration->up();

        $this->assertTrue($owner->fresh()->is_admin);
        $this->assertTrue($other->fresh()->is_admin);
        $this->assertFalse(User::factory()->create()->fresh()->is_admin, 'later accounts are not administrators by default');
    }

    public function test_while_the_column_is_missing_every_account_may_reach_the_admin(): void
    {
        $this->isAdminMigration()->down();
        $owner = User::factory()->create()->fresh();

        $this->assertArrayNotHasKey('is_admin', $owner->getAttributes());
        $this->assertTrue(Gate::forUser($owner)->allows('admin'));

        $this->actingAs($owner)->getJson('/admin/jeton')->assertOk();
    }

    public function test_a_signed_in_account_without_the_admin_right_gets_403(): void
    {
        $user = $this->admin(['is_admin' => false]);

        $this->assertFalse(Gate::forUser($user)->allows('admin'));
        $this->assertFalse(Gate::forUser($user->fresh())->allows('admin'));

        foreach (['/admin', '/admin/photos', '/admin/maintenance', '/admin/jeton', '/admin/guide'] as $uri) {
            $this->get($uri)->assertForbidden()->assertSee(__('admin.forbidden.heading'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }

        $this->getJson('/admin/photos')->assertForbidden()->assertExactJson(['message' => __('admin.forbidden.json')]);

        $this->post('/admin/deconnexion')->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_administrators_pass_the_gate(): void
    {
        $admin = $this->admin();

        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Gate::forUser($admin->fresh())->allows('admin'));
        $this->getJson('/admin/jeton')->assertOk();
    }

    public function test_atelier_admin_grants_the_admin_right(): void
    {
        $existing = User::factory()->create(['email' => 'equipe@pehouet.test', 'is_admin' => false]);

        $this->artisan('atelier:admin', ['email' => 'equipe@pehouet.test', '--password' => 'un-mot-de-passe-solide'])->assertSuccessful();
        $this->artisan('atelier:admin', ['email' => 'nouveau@pehouet.test', '--password' => 'un-mot-de-passe-solide'])->assertSuccessful();

        $this->assertTrue($existing->fresh()->is_admin);
        $this->assertTrue(User::query()->where('email', 'nouveau@pehouet.test')->sole()->is_admin);
    }

    public function test_the_admin_right_is_never_mass_assigned_and_is_a_real_boolean(): void
    {
        $user = new User(['name' => 'X', 'email' => 'x@pehouet.test', 'password' => 'un-mot-de-passe-solide', 'is_admin' => true]);
        $this->assertArrayNotHasKey('is_admin', $user->getAttributes());

        $user->is_admin = '1';
        $this->assertTrue($user->getAttributes()['is_admin']);
        $user->is_admin = 0;
        $this->assertFalse($user->getAttributes()['is_admin']);
    }

    public function test_a_password_changed_elsewhere_signs_this_session_out(): void
    {
        $admin = $this->admin();
        $this->getJson('/admin/jeton')->assertOk();

        $this->withSession(['password_hash_web' => 'hash-of-an-old-password'])
            ->get('/admin/jeton')
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
        $this->assertNotNull($admin->fresh());
    }

    public function test_remember_me_lasts_thirty_days(): void
    {
        $this->assertSame(43200, config('auth.guards.web.remember'));
        $this->assertSame(43200, (fn (): int => $this->rememberDuration)->call(auth()->guard('web')));
    }

    public function test_the_token_route_returns_the_csrf_token_without_caching(): void
    {
        $this->get('/admin/jeton')->assertRedirect(route('admin.login'));

        $this->admin();
        $response = $this->withSession(['_token' => 'jeton-de-session-7F3A'])->getJson('/admin/jeton')->assertOk();

        $response->assertExactJson(['token' => 'jeton-de-session-7F3A']);
        $this->assertTrue($response->headers->getCacheControlDirective('no-store'));
        $this->assertSame('admin.token', app('router')->getRoutes()->match(request()->create('/admin/jeton'))->getName());
    }

    public function test_password_reset_routes_are_for_guests_and_links_point_to_the_admin(): void
    {
        $this->assertSame(url('/admin/mot-de-passe-oublie'), route('admin.password.request'));
        $this->assertSame(url('/admin/mot-de-passe-oublie'), route('admin.password.email'));
        $this->assertSame(url('/admin/reinitialiser/abc123'), route('admin.password.reset', ['token' => 'abc123']));
        $this->assertSame(url('/admin/reinitialiser/abc123'), route('admin.password.update', ['token' => 'abc123']));

        $user = User::factory()->create(['email' => 'owner@pehouet.test']);
        $mail = (new ResetPassword('abc123'))->toMail($user);
        $this->assertSame(url('/admin/reinitialiser/abc123').'?email=owner%40pehouet.test', $mail->actionUrl);

        $this->admin();
        $this->get('/admin/mot-de-passe-oublie')->assertRedirect(route('admin.dashboard'));
    }
}
