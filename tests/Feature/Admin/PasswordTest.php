<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Notifications\AdminResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * "Mot de passe oublié ?" of the admin (docs/CMS.md §13 D19): Laravel's password broker when this
 * site really sends e-mails, else the page explains to ask another administrator.
 */
class PasswordTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'owner@pehouet.test';

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('admin.password.request')) {
            $this->markTestSkipped('The password routes belong to routes/admin.php (core-h).');
        }
    }

    private function owner(string $email = self::EMAIL): User
    {
        $attributes = ['name' => 'Awa Pehouet', 'email' => $email, 'password' => 'old-password-123'];

        if (Schema::hasColumn('users', 'is_admin')) {
            $attributes['is_admin'] = true;
        }

        return User::factory()->create($attributes);
    }

    /** A real mailer and from-address (the tests' "array" mailer only records messages). */
    private function withMailer(): void
    {
        config(['mail.default' => 'smtp', 'mail.from.address' => 'atelier@ateliers-pehouet.fr']);
    }

    // --- Without a mailer ---------------------------------------------------------------------

    public function test_without_a_real_mailer_the_page_says_to_ask_another_administrator(): void
    {
        $this->assertSame('array', config('mail.default'));

        $this->get(route('admin.password.request'))
            ->assertOk()
            ->assertSee('Mot de passe oublié')
            ->assertSee(__('admin.password.no_mail_help'))
            ->assertSee(__('admin.password.no_mail_alone'))
            ->assertSee('href="'.route('admin.login').'"', false)
            ->assertDontSee('action="'.route('admin.password.email').'"', false);

        // A placeholder from-address is no better (mail from example.com never arrives).
        config(['mail.default' => 'smtp', 'mail.from.address' => 'hello@example.com']);
        $this->get(route('admin.password.request'))->assertSee(__('admin.password.no_mail_help'));

        // Posting anyway sends nothing.
        Notification::fake();
        $this->owner();
        $this->post(route('admin.password.email'), ['email' => self::EMAIL])->assertRedirect(route('admin.password.request'));
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    // --- The link -------------------------------------------------------------------------------

    public function test_a_reset_link_is_e_mailed_in_french_to_the_admin_reset_page(): void
    {
        Notification::fake();
        $this->withMailer();
        $user = $this->owner();

        $this->get(route('admin.password.request'))
            ->assertOk()
            ->assertSee('action="'.route('admin.password.email').'"', false)
            ->assertSee('autocomplete="username"', false);

        // Whatever the case of the address.
        $this->post(route('admin.password.email'), ['email' => 'Owner@Pehouet.TEST'])
            ->assertRedirect(route('admin.password.request'))
            ->assertSessionHas('status', __('admin.password.sent', ['email' => 'owner@pehouet.test', 'minutes' => 60]));

        $this->assertDatabaseHas('password_reset_tokens', ['email' => self::EMAIL]);

        Notification::assertSentTo($user, AdminResetPassword::class, function (AdminResetPassword $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $url = $notification->url($user);

            $this->assertSame('fr', $notification->locale);
            $this->assertSame(route('admin.password.reset', ['token' => $notification->token, 'email' => self::EMAIL]), $url);
            $this->assertSame('Nouveau mot de passe — administration Ateliers Pehouet', $mail->subject);
            $this->assertSame($url, $mail->viewData['url']);

            $html = (string) $mail->render();
            $this->assertStringContainsString('href="'.e($url).'"', $html);
            $this->assertStringContainsString('Choisir un nouveau mot de passe', $html);
            $this->assertStringContainsString('valable 60 minutes', $html);
            $this->assertTrue(Password::broker()->tokenExists($user, $notification->token));

            return true;
        });
    }

    public function test_the_mail_follows_the_language_of_the_admin(): void
    {
        Notification::fake();
        $this->withMailer();
        $user = $this->owner();

        $this->get(route('admin.password.request').'?lang=en')->assertSee('Send me the link');
        $this->post(route('admin.password.email'), ['email' => self::EMAIL]);

        Notification::assertSentTo($user, AdminResetPassword::class, fn (AdminResetPassword $notification): bool => $notification->locale === 'en');
    }

    public function test_the_answer_never_tells_whether_an_address_has_an_account(): void
    {
        Notification::fake();
        $this->withMailer();
        $this->owner();

        $this->post(route('admin.password.email'), ['email' => 'nobody@pehouet.test'])
            ->assertRedirect(route('admin.password.request'))
            ->assertSessionHas('status', __('admin.password.sent', ['email' => 'nobody@pehouet.test', 'minutes' => 60]));
        Notification::assertNothingSent();

        // A second request within the minute (broker throttle): the same answer, no second e-mail.
        $this->post(route('admin.password.email'), ['email' => self::EMAIL])->assertSessionHas('status');
        $this->post(route('admin.password.email'), ['email' => self::EMAIL])
            ->assertSessionHas('status', __('admin.password.sent', ['email' => self::EMAIL, 'minutes' => 60]));
        Notification::assertSentTimes(AdminResetPassword::class, 1);
    }

    public function test_links_are_limited_per_ip(): void
    {
        Notification::fake();
        $this->withMailer();
        $users = collect(range(1, 6))->map(fn (int $n): User => $this->owner('admin'.$n.'@pehouet.test'));

        foreach ($users as $user) {
            $this->post(route('admin.password.email'), ['email' => $user->email])->assertSessionHas('status');
        }

        Notification::assertSentTimes(AdminResetPassword::class, 5);
        Notification::assertNotSentTo($users->last(), AdminResetPassword::class);

        // Another IP is not concerned.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.3.1'])
            ->post(route('admin.password.email'), ['email' => $users->last()->email]);
        Notification::assertSentTo($users->last(), AdminResetPassword::class);
    }

    public function test_a_forged_host_never_receives_the_token(): void
    {
        // "Password reset poisoning": the link must point to the site, not to the Host header.
        Notification::fake();
        $this->withMailer();
        config(['app.url' => 'http://localhost']);
        $user = $this->owner();

        $this->post('http://evil.example/admin/mot-de-passe-oublie', ['email' => self::EMAIL])->assertRedirect();

        Notification::assertSentTo($user, AdminResetPassword::class, function (AdminResetPassword $notification) use ($user): bool {
            $this->assertStringStartsWith('http://localhost/admin/', $notification->url($user));

            return true;
        });
    }

    public function test_an_invalid_address_re_renders_the_form_with_422(): void
    {
        $this->withMailer();

        $this->post(route('admin.password.email'), ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('value="not-an-email"', false);
    }

    public function test_a_mail_failure_says_so_kindly(): void
    {
        // An SMTP server that refuses the connection.
        config(['mail.default' => 'smtp', 'mail.from.address' => 'atelier@ateliers-pehouet.fr', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        config(['logging.default' => 'null']);
        $this->owner();

        $this->post(route('admin.password.email'), ['email' => self::EMAIL])
            ->assertRedirect(route('admin.password.request'))
            ->assertSessionHas('error', __('admin.password.unavailable'))
            ->assertSessionHasInput('email', self::EMAIL);

        // No token left behind: trying again really tries to send again.
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->post(route('admin.password.email'), ['email' => self::EMAIL])->assertSessionHas('error', __('admin.password.unavailable'));
    }

    // --- The new password ---------------------------------------------------------------------

    public function test_the_link_lets_the_admin_choose_a_new_password(): void
    {
        $user = $this->owner();
        $rememberToken = $user->remember_token;
        $token = Password::broker()->createToken($user);
        $url = route('admin.password.reset', ['token' => $token, 'email' => self::EMAIL]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Nouveau mot de passe')
            ->assertSee('value="'.self::EMAIL.'"', false)
            ->assertSee('action="'.route('admin.password.update', ['token' => $token]).'"', false)
            ->assertSee('autocomplete="new-password"', false);

        $this->post(route('admin.password.update', ['token' => $token]), [
            'email' => 'OWNER@pehouet.test',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('admin.login'))->assertSessionHas('status', __('admin.password.done'));

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-password', $user->password));
        $this->assertNotSame($rememberToken, $user->remember_token);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertGuest();

        if (Schema::hasTable('cms_activity')) {
            $this->assertDatabaseHas('cms_activity', ['action' => 'auth.password_reset', 'subject' => 'user:'.$user->id]);
        }

        // The link works once.
        $this->post(route('admin.password.update', ['token' => $token]), [
            'email' => self::EMAIL,
            'password' => 'another-password-1',
            'password_confirmation' => 'another-password-1',
        ])->assertStatus(422)->assertSee(__('admin.password.invalid_link'));

        // And the new password opens the admin.
        $this->post(route('admin.login.attempt'), ['email' => self::EMAIL, 'password' => 'brand-new-password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_wrong_or_expired_link_is_refused_and_a_new_one_offered(): void
    {
        $user = $this->owner();
        $token = Password::broker()->createToken($user);
        $form = ['email' => self::EMAIL, 'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password'];

        $this->post(route('admin.password.update', ['token' => 'notTheRealToken123']), $form)
            ->assertStatus(422)
            ->assertSee(__('admin.password.invalid_link'))
            ->assertSee('href="'.route('admin.password.request').'"', false);

        // An hour later the real token has expired too.
        $this->travel(61)->minutes();
        $this->post(route('admin.password.update', ['token' => $token]), $form)->assertStatus(422);

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }

    public function test_the_new_password_is_validated_with_422(): void
    {
        $user = $this->owner();
        $token = Password::broker()->createToken($user);

        $this->post(route('admin.password.update', ['token' => $token]), [
            'email' => self::EMAIL,
            'password' => 'short',
            'password_confirmation' => 'other',
        ])
            ->assertStatus(422)
            ->assertSee('id="field-password-error"', false)
            ->assertSee('value="'.self::EMAIL.'"', false)
            ->assertDontSee('value="short"', false);

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
        $this->assertTrue(Password::broker()->tokenExists($user, $token));

        // A forged array field is invalid input too, never a 500.
        $this->post(route('admin.password.update', ['token' => $token]), [
            'email' => [self::EMAIL],
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertStatus(422);
    }

    // --- Shared rules ---------------------------------------------------------------------------

    public function test_signed_in_admins_are_sent_to_the_dashboard(): void
    {
        $this->actingAs($this->owner());

        $this->get(route('admin.password.request'))->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.password.reset', ['token' => 'x']))->assertRedirect(route('admin.dashboard'));
    }

    public function test_the_pages_are_never_indexed_and_cookie_sessions_stay_small(): void
    {
        config(['session.driver' => 'cookie']);
        $this->withMailer();
        Notification::fake();
        $user = $this->owner();
        $token = Password::broker()->createToken($user);

        $responses = [
            $this->get(route('admin.password.request')),
            $this->post(route('admin.password.email'), ['email' => str_repeat('a', 170).'@pehouet.test']),
            $this->post(route('admin.password.email'), ['email' => 'not-an-email']),
            $this->get(route('admin.password.reset', ['token' => $token, 'email' => self::EMAIL])),
            $this->post(route('admin.password.update', ['token' => $token]), ['email' => self::EMAIL, 'password' => str_repeat('x', 900), 'password_confirmation' => 'y']),
        ];

        foreach ($responses as $response) {
            $this->assertStringContainsString('noindex', (string) $response->headers->get('X-Robots-Tag'));
            foreach ($response->headers->getCookies() as $cookie) {
                $this->assertLessThan(4096, strlen((string) $cookie), 'Set-Cookie '.$cookie->getName().' is too large');
            }
        }
    }

    public function test_the_reset_table_is_the_one_of_the_base_migrations(): void
    {
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
        $this->assertSame('password_reset_tokens', config('auth.passwords.users.table'));
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }
}
