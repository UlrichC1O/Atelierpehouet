<?php

namespace Tests\Feature\Admin;

use App\Cms\MediaItem;
use App\Http\Controllers\Admin\Concerns\RendersInvalidForms;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Mockery;
use PDOException;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * Admin sign-in (docs/CMS.md §7.1, §13 D15–D20, F25) and the admin shell, components and shared
 * partials of the admin-ui area (§6): layouts, navigation, CSP-safe markup, x-admin.* components.
 */
class AuthTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    private const EMAIL = 'owner@pehouet.test';

    private const PASSWORD = 'correct-horse-battery';

    private function owner(): User
    {
        $attributes = ['name' => 'Awa Pehouet', 'email' => self::EMAIL, 'password' => self::PASSWORD];

        // The explicit admin right (§13 D18), once its column exists.
        if (Schema::hasColumn('users', 'is_admin')) {
            $attributes['is_admin'] = true;
        }

        return User::factory()->create($attributes);
    }

    /** A new serverless request: Vercel's "array" default cache starts empty every time. */
    private function freshRequestCache(): void
    {
        Cache::store('array')->flush();
    }

    /** The real CSRF check (Laravel skips it while running tests). */
    private function enforceCsrf(): void
    {
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    /** @return array{email: string, password: string} */
    private function credentials(string $password = self::PASSWORD): array
    {
        return ['email' => self::EMAIL, 'password' => $password];
    }

    private function assertCspSafe(TestResponse $response): void
    {
        $csp = $response->headers->get('Content-Security-Policy') ?? $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertNotNull($csp, 'the admin page has no Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);

        $html = (string) $response->getContent();
        preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is', $html, $scripts, PREG_SET_ORDER);
        $this->assertNotEmpty($scripts);
        foreach ($scripts as [$tag, $attributes, $body]) {
            $this->assertMatchesRegularExpression('/\ssrc="[^"]+"/', $attributes, "inline script: $tag");
            $this->assertSame('', trim($body), "inline script code: $tag");
        }
        $this->assertDoesNotMatchRegularExpression('/<[^>]+\son[a-z]+\s*=/i', $html, 'an on* event attribute is present');
    }

    // --- Login page ---------------------------------------------------------------------------

    public function test_login_page_renders_in_french_and_english(): void
    {
        $this->get('/admin/connexion')
            ->assertOk()
            ->assertSee('<html lang="fr"', false)
            ->assertSee('<body class="admin admin--guest"', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('name="csrf-token"', false)
            ->assertSee('Bienvenue à l’atelier')
            ->assertSee('Se connecter')
            ->assertSee('action="'.route('admin.login.attempt').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee('css/admin/admin.css', false)
            ->assertSee('js/admin/admin.js', false)
            // Never the public loader, cursor or page-transition layers.
            ->assertDontSee('class="loader', false)
            ->assertDontSee('js/fx.js', false)
            ->assertDontSee('js/loader.js', false)
            ->assertDontSee('css/03-layout.css', false)
            ->assertDontSee('css/05-anim-brand.css', false);

        $this->get('/admin/connexion?lang=en')
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('Welcome to the atelier')
            ->assertSee('Keep me signed in on this device')
            ->assertDontSee('Bienvenue à l’atelier');
    }

    public function test_the_login_page_says_how_long_remember_me_lasts(): void
    {
        config(['auth.guards.web.remember' => 43200]);

        $this->get('/admin/connexion')
            ->assertOk()
            ->assertSee('aria-describedby="login-remember-hint"', false)
            ->assertSee('Pendant 30 jours. À éviter sur un ordinateur partagé.');

        // Laravel's default (5 years) is not worth announcing.
        config(['auth.guards.web.remember' => null]);
        $this->get('/admin/connexion')->assertOk()->assertDontSee('login-remember-hint', false);
    }

    public function test_the_forgot_link_leads_to_the_password_page(): void
    {
        if (! Route::has('admin.password.request')) {
            $this->markTestSkipped('The password routes belong to routes/admin.php (core-h).');
        }

        $this->get('/admin/connexion')->assertSee('href="'.route('admin.password.request').'"', false);
    }

    public function test_admin_html_has_a_csp_and_no_inline_script(): void
    {
        $this->assertCspSafe($this->get('/admin/connexion')->assertOk());

        $this->actingAs($this->owner());
        $this->assertCspSafe($this->get('/admin/guide')->assertOk());
        $this->assertCspSafe($this->get('/admin/guide?demo=errors')->assertOk());
    }

    public function test_admin_responses_are_never_indexed_nor_stored(): void
    {
        $responses = [
            $this->get('/admin/connexion'),
            $this->post('/admin/connexion', $this->credentials('nope')),
        ];
        $this->actingAs($this->owner());
        $responses[] = $this->get('/admin/guide');
        $responses[] = $this->post('/admin/deconnexion');

        foreach ($responses as $response) {
            $this->assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        }
    }

    // --- Sign-in -------------------------------------------------------------------------------

    public function test_wrong_password_is_rejected_with_the_email_kept(): void
    {
        $this->owner();

        // What the owner sees: the form again, the message on the e-mail field, the e-mail kept.
        $this->followingRedirects()
            ->post('/admin/connexion', $this->credentials('wrong-password'))
            ->assertOk()
            ->assertSee(__('admin.auth.failed'))
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('value="'.self::EMAIL.'"', false)
            ->assertDontSee('wrong-password');
        $this->assertGuest();

        $this->post('/admin/connexion', $this->credentials('wrong-password'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => __('admin.auth.failed')])
            ->assertSessionHasInput('email', self::EMAIL)
            ->assertSessionMissing('_old_input.password');
    }

    public function test_invalid_input_is_validated_before_any_attempt(): void
    {
        $this->post('/admin/connexion', ['email' => 'not-an-email', 'password' => ''])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $this->owner();
        $this->assertSame(5, config('cms.login_attempts'));

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/admin/connexion', $this->credentials('wrong-'.$attempt))
                ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);
        }

        // Locked, even with the right password.
        $this->post('/admin/connexion', $this->credentials())->assertSessionHasErrors('email');
        $this->assertGuest();
        $message = session('errors')->first('email');
        $this->assertStringContainsString('Trop de tentatives', $message);
        $this->assertMatchesRegularExpression('/\d+ secondes/', $message);

        // The minute bucket is per e-mail + IP: another address is not locked.
        $this->post('/admin/connexion', ['email' => 'someone@pehouet.test', 'password' => 'whatever'])
            ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);

        // A minute later the address may try again.
        $this->travel(61)->seconds();
        $this->post('/admin/connexion', $this->credentials())->assertRedirect(route('admin.dashboard'));
    }

    public function test_the_limiter_outlives_the_per_request_array_cache_of_vercel(): void
    {
        // Vercel: CACHE_STORE=array lives for one request; the limiter has a store of its own (§13 D15).
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('database', config('cms.login_limiter_store'));
        $this->owner();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->freshRequestCache();
            $this->post('/admin/connexion', $this->credentials('wrong-'.$attempt))
                ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);
        }

        // The 6th attempt, in yet another request, is refused — even with the right password.
        $this->freshRequestCache();
        $this->post('/admin/connexion', $this->credentials())->assertSessionHasErrors('email');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
        $this->assertGuest();

        // The attempts live in the cache table, keyed without the address in clear.
        $keys = DB::table('cache')->pluck('key')->implode(' ');
        $this->assertStringContainsString('admin-login', $keys);
        $this->assertStringNotContainsString(self::EMAIL, $keys);
    }

    public function test_a_per_request_store_would_never_lock_anyone(): void
    {
        // Control of the test above: what the dedicated store prevents.
        config(['cms.login_limiter_store' => 'array']);
        $this->owner();

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->freshRequestCache();
            $this->post('/admin/connexion', $this->credentials('wrong-'.$attempt))
                ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);
        }
    }

    public function test_the_e_mail_bucket_ignores_case_spaces_and_ip(): void
    {
        $this->owner();

        // 20 failures per hour for one address, from 20 different IPs, whatever its spelling.
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $email = $attempt % 2 ? '  OWNER@Pehouet.test ' : self::EMAIL;
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$attempt])
                ->post('/admin/connexion', ['email' => $email, 'password' => 'wrong-'.$attempt])
                ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->post('/admin/connexion', $this->credentials())
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertMatchesRegularExpression('/Réessayez dans \d+ minutes\./', session('errors')->first('email'));

        // Other addresses are not concerned.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->post('/admin/connexion', ['email' => 'someone@pehouet.test', 'password' => 'whatever'])
            ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);
    }

    public function test_the_ip_bucket_stops_a_spray_over_many_addresses(): void
    {
        $this->owner();

        for ($attempt = 1; $attempt <= 50; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
                ->post('/admin/connexion', ['email' => 'guess'.$attempt.'@pehouet.test', 'password' => 'whatever'])
                ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);
        }

        // That IP is locked for the hour, even for the right credentials…
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.7'])
            ->post('/admin/connexion', $this->credentials())
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('minutes', session('errors')->first('email'));

        // …the owner, elsewhere, is not.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.8'])
            ->post('/admin/connexion', $this->credentials())
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_successful_login_regenerates_the_session_and_opens_the_dashboard(): void
    {
        $user = $this->owner();
        $this->get('/admin/connexion');
        $before = session()->getId();

        // E-mails match whatever their case.
        $this->post('/admin/connexion', ['email' => 'Owner@Pehouet.TEST', 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_login_is_recorded_in_the_activity_log_when_available(): void
    {
        $user = $this->owner();

        $this->post('/admin/connexion', $this->credentials())->assertRedirect(route('admin.dashboard'));

        if (! Schema::hasTable('cms_activity')) {
            $this->markTestSkipped('The cms_activity table belongs to the core migrations.');
        }
        $this->assertDatabaseHas('cms_activity', ['action' => 'auth.login', 'user_id' => $user->id, 'subject' => 'user:'.$user->id]);
    }

    public function test_the_page_a_guest_was_sent_from_is_restored_after_login(): void
    {
        $this->owner();

        $this->get('/admin/photos?filtre=galerie')->assertRedirect(route('admin.login'));
        $this->post('/admin/connexion', $this->credentials())->assertRedirect(url('/admin/photos?filtre=galerie'));
    }

    public function test_external_or_odd_intended_urls_are_ignored(): void
    {
        $this->owner();

        $hostile = [
            'https://evil.example/admin', '//evil.example/admin', '/\\evil.example', '\\\\evil.example', "\t//evil.example",
            'https://evil.example\\@localhost/', 'javascript://localhost/%0aalert(1)', 'javascript:alert(1)',
            'http://localhost.evil.example/admin', "/admin\r\nX-Injected: 1", url('/admin/connexion'),
        ];

        foreach ($hostile as $intended) {
            $this->withSession(['url.intended' => $intended])
                ->post('/admin/connexion', $this->credentials())
                ->assertRedirect(route('admin.dashboard'));

            Auth::guard()->logout();
        }
    }

    public function test_remember_me_sets_the_recaller_cookie_only_when_asked(): void
    {
        $this->owner();
        $recaller = Auth::guard()->getRecallerName();

        $this->post('/admin/connexion', $this->credentials())->assertCookieMissing($recaller);
        Auth::guard()->logout();

        $this->post('/admin/connexion', $this->credentials() + ['remember' => '1'])->assertCookie($recaller);
    }

    public function test_logout_is_a_post_that_invalidates_the_session(): void
    {
        $user = $this->owner();
        $this->actingAs($user);

        // A GET never signs anyone out.
        $this->get('/admin/deconnexion');
        $this->assertAuthenticatedAs($user);

        $this->withSession(['locale' => 'en', 'secret' => 'kept?'])
            ->post('/admin/deconnexion')
            ->assertRedirect(route('admin.login'))
            ->assertSessionMissing('secret');
        $this->assertGuest();

        // The language survives; the login page says goodbye.
        $this->get('/admin/connexion')
            ->assertSee('<html lang="en"', false)
            ->assertSee(__('admin.auth.logged_out', [], 'en'));
    }

    public function test_guests_are_sent_to_the_login_page_and_admins_away_from_it(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/guide')->assertRedirect(route('admin.login'));
        $this->getJson('/admin')->assertUnauthorized();
        $this->post('/admin/deconnexion')->assertRedirect(route('admin.login'));

        $this->actingAs($this->owner())->get('/admin/connexion')->assertRedirect(route('admin.dashboard'));
    }

    // --- First administrator (ADMIN_EMAIL / ADMIN_PASSWORD) ------------------------------------

    public function test_bootstrap_admin_is_created_only_while_no_user_exists(): void
    {
        config(['cms.bootstrap_admin' => ['email' => 'Atelier@Pehouet.test', 'password' => 'first-admin-password']]);
        $this->assertDatabaseCount('users', 0);

        $this->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => 'not-the-one'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 0);

        $this->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => 'first-admin-password'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status', __('admin.auth.bootstrapped'));

        $this->assertAuthenticated();
        $this->assertDatabaseCount('users', 1);
        $admin = User::query()->sole();
        $this->assertSame('atelier@pehouet.test', $admin->email);
        $this->assertTrue(Hash::check('first-admin-password', $admin->password));

        if (Schema::hasColumn('users', 'is_admin')) {
            $this->assertTrue($admin->is_admin);
        }

        // The owner is told to remove ADMIN_PASSWORD from the Vercel settings.
        $this->assertStringContainsString('ADMIN_PASSWORD', __('admin.auth.bootstrapped'));
        $this->assertStringContainsString('Vercel', __('admin.auth.bootstrapped'));
    }

    public function test_bootstrap_needs_both_values_and_a_ten_character_password(): void
    {
        Log::spy();

        $cases = [
            ['email' => '', 'password' => 'first-admin-password'],
            ['email' => 'atelier@pehouet.test', 'password' => 'nine-char'],
            ['email' => ['atelier@pehouet.test'], 'password' => 'first-admin-password'],
        ];

        foreach ($cases as $index => $settings) {
            config(['cms.bootstrap_admin' => $settings]);

            $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.'.$index])
                ->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => is_string($settings['password']) ? $settings['password'] : 'x'])
                ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);
        }

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        Log::shouldHaveReceived('warning')->with(Mockery::pattern('/Admin bootstrap disabled/'))->times(3);
    }

    public function test_without_admin_password_the_bootstrap_is_silently_off(): void
    {
        // The normal state once the owner removed ADMIN_PASSWORD (ADMIN_EMAIL may stay).
        Log::spy();
        config(['cms.bootstrap_admin' => ['email' => 'atelier@pehouet.test', 'password' => null]]);

        $this->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => 'first-admin-password'])
            ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);

        Log::shouldNotHaveReceived('warning');
    }

    public function test_bootstrap_compares_a_trimmed_lower_cased_email_and_the_exact_password(): void
    {
        config(['cms.bootstrap_admin' => ['email' => '  Atelier@Pehouet.TEST ', 'password' => 'First-Admin-Password']]);

        $this->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => 'first-admin-password'])
            ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);
        $this->assertDatabaseCount('users', 0);

        $this->post('/admin/connexion', ['email' => 'ATELIER@pehouet.test', 'password' => 'First-Admin-Password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertSame('atelier@pehouet.test', User::query()->sole()->email);
    }

    public function test_bootstrap_credentials_are_still_limited(): void
    {
        config(['cms.bootstrap_admin' => ['email' => 'atelier@pehouet.test', 'password' => 'first-admin-password']]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => 'guess-'.$attempt])
                ->assertSessionHasErrors('email');
        }

        // Checked before the bootstrap comparison: the right ones are refused while locked.
        $this->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => 'first-admin-password'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_bootstrap_on_a_brand_new_database_creates_every_table_first(): void
    {
        // An empty database: no users table, no cache table for the limiter, no CMS table.
        $this->useFreshDatabase();

        // Wrong credentials never migrate anything (and nothing can be counted yet).
        $this->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => 'not-the-one'])
            ->assertSessionHas('error', __('admin.auth.unavailable'));
        $this->assertFalse(Schema::hasTable('users'));

        $this->assertFreshDatabaseBootstraps();
    }

    public function test_bootstrap_migrates_a_database_without_users_even_with_another_limiter_store(): void
    {
        $limiterPath = sys_get_temp_dir().'/ap-limiter-'.uniqid();
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($limiterPath));
        config(['cms.login_limiter_store' => 'file', 'cache.stores.file.path' => $limiterPath]);
        $this->useFreshDatabase();

        // No users table to check other credentials against: the friendly message, nothing migrated.
        $this->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => 'not-the-one'])
            ->assertSessionHas('error', __('admin.auth.unavailable'));
        $this->assertFalse(Schema::hasTable('users'));

        $this->assertFreshDatabaseBootstraps();
    }

    /** An empty SQLite file as the default connection, ADMIN_EMAIL / ADMIN_PASSWORD set. */
    private function useFreshDatabase(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ap-fresh-db-');
        $this->beforeApplicationDestroyed(fn () => @unlink($path));
        $this->useDefaultConnection('fresh', ['driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => true]);
        $this->assertFalse(Schema::hasTable('users'));
        config(['cms.bootstrap_admin' => ['email' => 'atelier@pehouet.test', 'password' => 'first-admin-password']]);
        config(['logging.default' => 'null']);
    }

    private function assertFreshDatabaseBootstraps(): void
    {
        $this->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => 'first-admin-password'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('status', __('admin.auth.bootstrapped'));

        $this->assertAuthenticated();
        foreach (['users', 'cache', 'migrations', 'translation_overrides', 'media', 'cms_activity'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "table $table");
        }
        $this->assertSame('atelier@pehouet.test', User::query()->sole()->email);
    }

    public function test_a_user_the_admin_gate_refuses_never_gets_a_session(): void
    {
        $user = $this->owner();
        Gate::define('admin', fn (User $candidate): bool => $candidate->isNot($user));

        $this->post('/admin/connexion', $this->credentials())
            ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);
        $this->assertGuest();
    }

    public function test_bootstrap_credentials_are_ignored_once_an_admin_exists(): void
    {
        $this->owner();
        config(['cms.bootstrap_admin' => ['email' => 'intruder@pehouet.test', 'password' => 'first-admin-password']]);

        $this->post('/admin/connexion', ['email' => 'intruder@pehouet.test', 'password' => 'first-admin-password'])
            ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'intruder@pehouet.test']);
    }

    public function test_a_short_bootstrap_password_never_creates_an_account(): void
    {
        config(['cms.bootstrap_admin' => ['email' => 'atelier@pehouet.test', 'password' => 'short']]);
        config(['logging.default' => 'null']);

        $this->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => 'short'])
            ->assertSessionHasErrors(['email' => __('admin.auth.failed')]);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_without_bootstrap_settings_an_empty_users_table_creates_nobody(): void
    {
        config(['cms.bootstrap_admin' => ['email' => null, 'password' => null]]);

        $this->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => ''])->assertSessionHasErrors('password');
        $this->post('/admin/connexion', ['email' => 'atelier@pehouet.test', 'password' => 'anything'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_unreachable_database_gives_a_friendly_message_not_a_500(): void
    {
        $this->owner();
        config(['logging.default' => 'null']);
        DB::connection()->beforeExecuting(function (): void {
            throw new PDOException('SQLSTATE[08006] [7] could not connect to server');
        });

        $this->post('/admin/connexion', $this->credentials())
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', __('admin.auth.unavailable'));
        $this->assertGuest();

        $this->get('/admin/connexion')->assertOk()->assertSee(__('admin.auth.unavailable'));
    }

    public function test_a_failing_limiter_store_gives_the_friendly_message(): void
    {
        $this->owner();
        config(['logging.default' => 'null']);

        // A store whose table is missing, then a store that does not exist.
        config(['cache.stores.broken_limiter' => ['driver' => 'database', 'table' => 'no_such_cache_table', 'connection' => null]]);

        foreach (['broken_limiter', 'no_such_store'] as $store) {
            config(['cms.login_limiter_store' => $store]);

            $this->post('/admin/connexion', $this->credentials())
                ->assertRedirect(route('admin.login'))
                ->assertSessionHas('error', __('admin.auth.unavailable'));
            $this->assertGuest();
        }
    }

    public function test_cookie_sessions_stay_under_four_kilobytes(): void
    {
        config(['session.driver' => 'cookie']);
        $this->owner();

        $responses = [
            $this->get('/admin/connexion'),
            $this->post('/admin/connexion', ['email' => self::EMAIL, 'password' => str_repeat('x', 900)]),
            $this->post('/admin/connexion', ['email' => str_repeat('a', 180).'@pehouet.test', 'password' => 'whatever']),
        ];

        foreach ($responses as $response) {
            $cookies = $response->headers->getCookies();
            $this->assertNotEmpty($cookies);
            foreach ($cookies as $cookie) {
                $this->assertLessThan(4096, strlen((string) $cookie), 'Set-Cookie '.$cookie->getName().' is too large');
            }
        }
    }

    public function test_a_copy_of_the_site_says_so_on_every_admin_page(): void
    {
        // The official site by default (CMS_PRIMARY_URL).
        $this->assertSame('https://atelierpehouet.vercel.app', config('cms.primary_url'));
        $this->assertSame("Copie de test\u{202F}: les modifications faites ici n’apparaissent pas sur le site officiel.", __('admin.copy.warning'));

        $this->get('/admin/connexion')
            ->assertSee(__('admin.copy.warning'))
            ->assertSee('href="https://atelierpehouet.vercel.app/admin"', false);

        $this->actingAs($this->owner());
        $this->get('/admin/guide')->assertSee(__('admin.copy.warning'));

        config(['cms.primary_url' => url('/')]);
        $this->get('/admin/guide')->assertDontSee(__('admin.copy.warning'));
    }

    // --- Session expiry (§13 D20) --------------------------------------------------------------

    public function test_the_token_endpoint_gives_signed_in_admins_the_session_token(): void
    {
        if (! Route::has('admin.token')) {
            $this->markTestSkipped('The admin.token route belongs to routes/admin.php (core-h).');
        }

        $this->getJson(route('admin.token'))->assertUnauthorized();

        $this->actingAs($this->owner());
        $response = $this->getJson(route('admin.token'))->assertOk()->assertJsonStructure(['token']);
        $this->assertSame(session()->token(), $response->json('token'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // The pages tell admin.js where to refresh it.
        $this->get('/admin/guide')->assertSee('data-adm-token-url="'.route('admin.token').'"', false);
    }

    public function test_an_expired_session_on_an_admin_form_shows_the_admin_page_and_keeps_the_way_back(): void
    {
        $this->owner();
        $this->enforceCsrf();
        $back = url('/admin/textes/home');

        // The session is gone: the form's token matches nothing (old browsers send no Sec-Fetch-Site).
        $response = $this->withHeader('Referer', $back)
            ->put('/admin/textes/home', ['_token' => 'stale-token', 't' => ['fr' => ['hero.title' => 'Nouveau']]])
            ->assertStatus(419)
            ->assertSee('Session expirée')
            ->assertSee("Votre session a expiré\u{202F}: reconnectez-vous, vos modifications vous attendent.")
            ->assertSee('href="'.route('admin.login').'"', false)
            ->assertSee('<body class="admin admin--guest"', false)
            ->assertDontSee('Nouveau');
        $this->assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertCspSafe($response);

        // After signing in again, back to the form (admin.js restores the draft there).
        $this->post('/admin/connexion', $this->credentials() + ['_token' => session()->token()])->assertRedirect($back);
    }

    public function test_an_out_of_date_page_of_a_signed_in_admin_offers_to_go_back(): void
    {
        $user = $this->owner();
        $this->enforceCsrf();

        // Signed in through the session itself (the token was regenerated, e.g. in another tab).
        $this->withSession([Auth::guard('web')->getName() => $user->getAuthIdentifier(), 'locale' => 'en'])
            ->withHeader('Referer', url('/admin/reglages'))
            ->post('/admin/deconnexion', ['_token' => 'stale-token'])
            ->assertStatus(419)
            ->assertSee('<html lang="en"', false)
            ->assertSee('Page to reload')
            ->assertSee('href="'.url('/admin/reglages').'"', false);
        $this->assertAuthenticated();

        // XHR: JSON, which admin.js answers with a fresh token and one retry.
        $this->postJson('/admin/deconnexion', ['_token' => 'stale-token'])
            ->assertStatus(419)
            ->assertJson(['message' => __('admin.expired.json_reload', [], 'en')]);
    }

    public function test_an_expired_page_never_links_outside_the_admin(): void
    {
        Route::post('/admin/__expired-test', fn () => throw new TokenMismatchException('CSRF token mismatch.'))->middleware('web');

        foreach (['https://evil.example/admin', '//evil.example/admin', url('/contact')] as $referer) {
            $this->withHeader('Referer', $referer)
                ->post('/admin/__expired-test')
                ->assertStatus(419)
                ->assertDontSee('evil.example')
                ->assertSessionMissing('url.intended');
        }

        // Public URLs keep the public 419 page.
        Route::post('/__expired-test', fn () => throw new TokenMismatchException('CSRF token mismatch.'))->middleware('web');
        $this->post('/__expired-test')->assertStatus(419)->assertDontSee('admin--guest', false);
    }

    public function test_invalid_forms_show_the_field_errors_with_status_422(): void
    {
        // RendersInvalidForms::invalid() shares its error bag: <x-admin.field> (an anonymous component)
        // shows the message and the submitted value, never the password (docs/CMS.md §7.0, §13 E21).
        Route::post('/admin/__invalid-form-test', fn (Request $request) => (new class
        {
            use RendersInvalidForms;

            public function __invoke(Request $request)
            {
                return $this->invalid($request, 'admin.auth.login', [], ['email' => 'Cette adresse est refusée.']);
            }
        })($request))->middleware('web');

        $this->post('/admin/__invalid-form-test', ['email' => 'kept@pehouet.test', 'password' => 'never-shown'])
            ->assertStatus(422)
            ->assertSee('<p class="field__error" id="field-email-error">Cette adresse est refusée.</p>', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('value="kept@pehouet.test"', false)
            ->assertDontSee('never-shown');
    }

    public function test_the_admin_lang_files_hold_no_literal_escape_sequence(): void
    {
        // "\u{202F}" inside single quotes would print as is (a phase-1 bug in slots.service_cover).
        foreach (['fr', 'en'] as $locale) {
            $lines = require lang_path($locale.'/admin.php');
            array_walk_recursive($lines, function (mixed $line, string|int $key) use ($locale): void {
                $this->assertDoesNotMatchRegularExpression('/\\\\u\{|\\\\n/', (string) $line, "$locale: $key");
            });
        }

        $this->assertSame("Couverture du service «\u{202F}Sculpture\u{202F}»", __('admin.slots.service_cover', ['service' => 'Sculpture']));
    }

    // --- Shell ---------------------------------------------------------------------------------

    public function test_the_shell_shows_the_navigation_the_unread_badge_and_the_user_tools(): void
    {
        ContactMessage::factory()->count(2)->create();
        ContactMessage::factory()->read()->create();
        $this->actingAs($this->owner());

        $response = $this->get('/admin/guide')
            ->assertOk()
            ->assertSee('<body class="admin"', false)
            ->assertSee('data-adm-i18n=', false)
            ->assertSee('id="adm-sidebar"', false)
            ->assertSee('data-adm-nav-toggle', false)
            ->assertSee('Awa Pehouet')
            ->assertSee('action="'.route('admin.logout').'"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('?lang=en', false)
            ->assertSee('<h1 class="adm-page-head__title">', false)
            ->assertSee('<span class="adm-nav__badge" aria-hidden="true">2</span>', false)
            ->assertSee('2 messages non lus')
            ->assertDontSee('class="loader', false);

        foreach (['texts.index', 'services.index', 'media.index', 'gallery.index', 'pages.index', 'messages.index', 'settings.edit', 'account.edit', 'maintenance', 'dashboard'] as $route) {
            $response->assertSee('href="'.route('admin.'.$route).'"', false);
        }
        foreach (['Tableau de bord', 'Textes des pages', 'Services', 'Photos', 'Galerie', 'Pages libres', 'Messages', 'Réglages', 'Compte', 'Maintenance'] as $label) {
            $response->assertSee($label);
        }
    }

    public function test_a_database_error_never_breaks_the_shell(): void
    {
        $this->actingAs($this->owner());
        Schema::drop('contact_messages');

        $this->get('/admin/guide')->assertOk()->assertDontSee('adm-nav__badge"', false);
    }

    public function test_the_artists_item_appears_only_once_its_routes_exist(): void
    {
        $this->actingAs($this->owner());

        if (! Route::has('admin.artists.index')) {
            $this->get('/admin/guide')->assertOk()->assertDontSee('icon--palette', false);
            Route::get('/admin/artistes', fn () => 'artistes')->middleware('web')->name('admin.artists.index');
            app('router')->getRoutes()->refreshNameLookups();
        }

        $this->get('/admin/guide')->assertOk()->assertSee('href="'.route('admin.artists.index').'"', false)->assertSee('icon--palette', false);
    }

    public function test_the_flash_partial_shows_status_error_and_a_linked_error_summary(): void
    {
        $this->actingAs($this->owner());

        $this->withSession(['status' => 'admin.flash.saved', 'error' => 'Une erreur précise.'])
            ->get('/admin/guide')
            ->assertSee('adm-flash--success', false)
            ->assertSee('data-adm-flash="success"', false)
            ->assertSee(__('admin.flash.saved'))
            ->assertSee('Une erreur précise.');

        $this->get('/admin/guide?demo=errors')
            ->assertSee('adm-flash--summary', false)
            ->assertSee('data-adm-autofocus', false)
            ->assertSee('href="#field-t-en-hero-title"', false)
            ->assertSee('id="field-t-en-hero-title"', false);
    }

    // --- Components ----------------------------------------------------------------------------

    public function test_field_reads_its_error_with_the_dot_safe_name_rule(): void
    {
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['t.fr.hero.title' => ['Trop long.']])));

        $html = Blade::render('<x-admin.field name="t[fr][hero.title]" label="Titre" lang="fr" :maxlength="60" hint="Court" value="Bonjour" class="field--full" />');

        $this->assertStringContainsString('id="field-t-fr-hero-title"', $html);
        $this->assertStringContainsString('name="t[fr][hero.title]"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="field-t-fr-hero-title-hint field-t-fr-hero-title-error"', $html);
        $this->assertStringContainsString('id="field-t-fr-hero-title-error">Trop long.</p>', $html);
        $this->assertStringContainsString('maxlength="60"', $html);
        $this->assertStringContainsString('data-maxlength="60"', $html);
        $this->assertStringContainsString('value="Bonjour"', $html);
        $this->assertStringContainsString('lang="fr"', $html);
        $this->assertStringContainsString('7 / 60', $html);
        $this->assertMatchesRegularExpression('/<div class="field adm-field adm-field--text field--invalid field--full"/', $html);

        $textarea = Blade::render('<x-admin.field name="features[0][title]" label="Titre" type="textarea" :value="\'<b>\'" />');
        $this->assertStringContainsString('id="field-features-0-title"', $textarea);
        $this->assertStringContainsString('data-autosize', $textarea);
        $this->assertStringContainsString('&lt;b&gt;</textarea>', $textarea);

        $select = Blade::render('<x-admin.field name="category" label="Catégorie" type="select" value="b" placeholder="—" :options="[\'a\' => \'A\', \'Groupe\' => [\'b\' => \'B\']]" />');
        $this->assertStringContainsString('<optgroup label="Groupe">', $select);
        $this->assertMatchesRegularExpression('/<option value="b"\s+selected/', $select);

        $password = Blade::render('<x-admin.field name="password" label="Mot de passe" type="password" value="secret" />');
        $this->assertStringNotContainsString('secret', $password);
        $this->assertStringContainsString('data-adm-reveal="field-password"', $password);
    }

    public function test_the_other_components_render_their_contract(): void
    {
        $toggle = Blade::render('<x-admin.toggle name="in_gallery" label="Galerie" :checked="true" hint="Visible" />');
        $this->assertStringContainsString('<input type="hidden" name="in_gallery" value="0">', $toggle);
        $this->assertMatchesRegularExpression('/type="checkbox"[^>]*role="switch"[^>]*name="in_gallery"[^>]*value="1"[^>]*checked/', $toggle);

        $confirm = Blade::render('<x-admin.confirm action="/admin/pages/3" label="Supprimer" message="Vraiment ?" />');
        $this->assertStringContainsString('data-confirm="Vraiment ?"', $confirm);
        $this->assertStringContainsString('method="POST"', $confirm);
        $this->assertStringContainsString('name="_method" value="DELETE"', $confirm);
        $this->assertStringContainsString('name="_token"', $confirm);
        $this->assertStringContainsString('adm-btn--danger', $confirm);

        $head = Blade::render('<x-admin.page-head title="Photos" lead="La photothèque" back="/admin"><a href="/x">Action</a></x-admin.page-head>');
        $this->assertStringContainsString('<h1 class="adm-page-head__title">Photos</h1>', $head);
        $this->assertStringContainsString('class="adm-page-head__back" href="/admin"', $head);
        $this->assertStringContainsString('adm-page-head__actions', $head);

        $card = Blade::render('<x-admin.card title="Réglages" accent="blue"><x-slot:actions><a href="/y">Voir</a></x-slot:actions> Corps</x-admin.card>');
        $this->assertStringContainsString('adm-card--accent accent-blue', $card);
        $this->assertStringContainsString('<h2 class="adm-card__title">Réglages</h2>', $card);
        $this->assertStringContainsString('adm-card__actions', $card);

        $this->assertStringContainsString('adm-badge--modified', Blade::render('<x-admin.badge variant="modified">Modifié</x-admin.badge>'));
        $stat = Blade::render('<x-admin.stat :value="3" label="Photos" href="/admin/photos" accent="red" icon="image" />');
        $this->assertMatchesRegularExpression('/<a href="\/admin\/photos" class="adm-stat adm-stat--link accent-red">/', $stat);
        $this->assertStringContainsString('<span class="adm-stat__value">3</span>', $stat);
        $this->assertStringContainsString('adm-thumb--empty', Blade::render('<x-admin.thumb :media="null" />'));
        $this->assertStringContainsString('<p class="adm-empty__title">Vide</p>', Blade::render('<x-admin.empty title="Vide" text="Rien" />'));
        $this->assertStringContainsString('data-adm-savebar', Blade::render('<x-admin.savebar back="/admin" />'));
    }

    public function test_the_shared_media_partials_render_their_hooks(): void
    {
        $slot = view('admin.media.partials.slot', ['slot' => 'home.feature', 'label' => 'Accueil', 'media' => null, 'ratio' => '4/3', 'redirect' => '/admin/textes/home'])->render();
        $this->assertStringContainsString('data-slot-form', $slot);
        $this->assertStringContainsString('data-media-picker data-slot="home.feature"', $slot);
        $this->assertStringContainsString('action="'.route('admin.slots.update').'"', $slot);
        $this->assertStringContainsString('name="slot" value="home.feature"', $slot);
        $this->assertStringContainsString('name="redirect" value="/admin/textes/home"', $slot);
        $this->assertStringContainsString(e(route('admin.media.index', ['slot' => 'home.feature', 'redirect' => '/admin/textes/home'])), $slot);
        $this->assertStringContainsString('aspect-ratio: 4 / 3', $slot);

        if (class_exists(MediaItem::class)) {
            $media = new MediaItem(
                id: 7, ulid: '01j9zx4k8w0000000000000000', extension: 'webp', mime: 'image/webp', width: 1600, height: 1200,
                variants: [], alt: ['fr' => 'Fresque', 'en' => null], caption: ['fr' => null, 'en' => null], focalX: 30, focalY: 60,
                service: null, inGallery: true, position: 0, originalName: 'fresque.webp', updatedAt: null,
            );
            $filled = view('admin.media.partials.slot', ['slot' => 'home.feature', 'label' => 'Accueil', 'media' => $media, 'ratio' => '4/3', 'redirect' => '/admin'])->render();
            $this->assertStringContainsString($media->url(960), $filled);
            $this->assertStringContainsString('name="media_id" value="7"', $filled);
            $this->assertStringContainsString('name="media_id" value=""', $filled);
            $this->assertStringContainsString('href="'.route('home').'#spot-home-feature"', $filled);
            $this->assertStringContainsString('object-position: 30% 60%', $filled);

            $thumb = Blade::render('<x-admin.thumb :media="$media" :size="64" />', ['media' => $media]);
            $this->assertStringContainsString('src="'.$media->url(480).'"', $thumb);
            $this->assertStringContainsString('alt="Fresque"', $thumb);
        }

        $uploader = view('admin.media.partials.uploader', ['defaults' => ['service_slug' => 'sculpture', 'in_gallery' => true], 'multiple' => true, 'redirect' => '/admin/photos'])->render();
        $this->assertStringContainsString('data-uploader', $uploader);
        $this->assertStringContainsString('enctype="multipart/form-data"', $uploader);
        $this->assertStringContainsString('action="'.route('admin.media.store').'"', $uploader);
        $this->assertStringContainsString('type="file" name="photo" accept="image/*"', $uploader);
        $this->assertStringContainsString('name="service_slug" value="sculpture"', $uploader);
        $this->assertStringContainsString('name="in_gallery" value="1"', $uploader);
        $this->assertStringContainsString('data-widths="'.e(json_encode(array_map('intval', config('cms.media.widths')))).'"', $uploader);
        $this->assertStringContainsString('data-multiple="1"', $uploader);
        $this->assertStringNotContainsString(' multiple', $uploader);
    }

    public function test_the_pagination_partial_links_previous_and_next(): void
    {
        $page = new LengthAwarePaginator(range(1, 20), 60, 20, 2, ['path' => '/admin/messages']);

        $html = (string) $page->links('admin.partials.pagination');

        $this->assertStringContainsString('Page 2 sur 3', $html);
        $this->assertStringContainsString('rel="prev"', $html);
        $this->assertStringContainsString('rel="next"', $html);
        $this->assertStringContainsString('/admin/messages?page=3', $html);
    }
}
