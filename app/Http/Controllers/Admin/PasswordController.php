<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Activity;
use App\Http\Controllers\Admin\Concerns\RendersInvalidForms;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * "Mot de passe oublié ?" (docs/CMS.md §13 D19): a link by e-mail through Laravel's password broker
 * (table password_reset_tokens) when this site really sends e-mails — a mailer other than log/array
 * and a real from-address —, otherwise the page explains to ask another administrator.
 *
 * The answer never tells whether an address has an account. Links are limited per IP and per
 * address on the login limiter's store (on top of the broker's one link per minute and account),
 * and always point to a host the site is configured for (no "password reset poisoning").
 */
final class PasswordController extends Controller
{
    use RendersInvalidForms;

    /** The admin's password rule (also docs/CMS.md §7.2 "Compte"). */
    public const MIN_PASSWORD = 10;

    /** Mailers that only record messages: nothing would reach an inbox. */
    private const RECORDING_MAILERS = ['log', 'array'];

    /** Reserved placeholder domains (RFC 2606, RFC 6761): mail sent from or to them is lost. */
    private const PLACEHOLDER_DOMAINS = '/(^|\.)(example\.(com|net|org)|example|invalid|localhost|test)$/i';

    /** Links sent per hour to one address, and asked per hour from one IP. */
    private const LINKS_PER_HOUR = 5;

    /**
     * Whether this site can e-mail a reset link: a real mailer and a from-address that is not a
     * placeholder (docs/CMS.md §13 D19).
     */
    public static function canSendMail(): bool
    {
        $mailer = config('mail.default');
        $from = config('mail.from.address');

        return is_string($mailer) && $mailer !== '' && ! in_array($mailer, self::RECORDING_MAILERS, true)
            && is_string($from) && filter_var($from, FILTER_VALIDATE_EMAIL) !== false
            && preg_match(self::PLACEHOLDER_DOMAINS, Str::afterLast($from, '@')) !== 1;
    }

    /** GET /admin/mot-de-passe-oublie (admin.password.request). */
    public function request(): View
    {
        return view('admin.auth.forgot', $this->forgotData());
    }

    /** POST /admin/mot-de-passe-oublie (admin.password.email). */
    public function email(Request $request): RedirectResponse|Response
    {
        if (! self::canSendMail()) {
            return redirect()->route('admin.password.request');
        }

        $validator = Validator::make(
            $request->only('email'),
            ['email' => ['required', 'string', 'email', 'max:190']],
            [],
            ['email' => __('admin.password.attributes.email')],
        );

        if ($validator->fails()) {
            return $this->invalid($request, 'admin.auth.forgot', $this->forgotData(), $validator);
        }

        $email = Str::lower(trim((string) $request->input('email')));
        $root = $this->trustedRoot($request);
        $locale = app()->getLocale();

        try {
            $limiter = new RateLimiter(Cache::store(config('cms.login_limiter_store')));
            $keys = ['admin-reset-ip|'.$request->ip(), 'admin-reset-email|'.hash('sha256', $email)];
            $allowed = true;

            foreach ($keys as $key) {
                $allowed = $allowed && ! $limiter->tooManyAttempts($key, self::LINKS_PER_HOUR);
            }

            if ($allowed) {
                foreach ($keys as $key) {
                    $limiter->hit($key, 3600);
                }

                // Unknown address, or a link sent less than a minute ago: the same answer below.
                $this->broker()->sendResetLink(
                    ['email' => fn (Builder $query) => $query->whereRaw('LOWER(email) = ?', [$email])],
                    function (User $user, string $token) use ($root, $locale): string {
                        try {
                            $user->notify(new AdminResetPassword($token, $root, $locale));
                        } catch (Throwable $e) {
                            // Not sent: no token left behind, so trying again sends one at once.
                            rescue(fn () => Password::broker()->deleteToken($user), report: false);

                            throw $e;
                        }

                        return PasswordBroker::RESET_LINK_SENT;
                    },
                );
            }
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.password.request')
                ->withInput(['email' => $email])
                ->with('error', __('admin.password.unavailable'));
        }

        return redirect()->route('admin.password.request')
            ->with('status', __('admin.password.sent', ['email' => $email, 'minutes' => AdminResetPassword::expireMinutes()]));
    }

    /** GET /admin/reinitialiser/{token}?email=… (admin.password.reset). */
    public function reset(Request $request, string $token): View
    {
        $email = $request->query('email');

        return view('admin.auth.reset', [
            'token' => $token,
            'email' => is_string($email) && mb_strlen($email) <= 190 ? $email : '',
            'linkFailed' => false,
        ]);
    }

    /** POST /admin/reinitialiser/{token} (admin.password.update). */
    public function update(Request $request, string $token): RedirectResponse|Response
    {
        $data = ['token' => $token, 'email' => (string) $request->input('email', ''), 'linkFailed' => false];

        $validator = Validator::make(
            $request->only('email', 'password', 'password_confirmation'),
            [
                'email' => ['required', 'string', 'email', 'max:190'],
                'password' => ['required', 'string', 'min:'.self::MIN_PASSWORD, 'max:1000', 'confirmed'],
            ],
            [],
            ['email' => __('admin.password.attributes.email'), 'password' => __('admin.password.attributes.password')],
        );

        if ($validator->fails()) {
            return $this->invalid($request, 'admin.auth.reset', $data, $validator);
        }

        $email = Str::lower(trim((string) $request->input('email')));

        try {
            $status = $this->broker()->reset(
                [
                    'email' => fn (Builder $query) => $query->whereRaw('LOWER(email) = ?', [$email]),
                    'password' => (string) $request->input('password'),
                    'token' => $token,
                ],
                function (User $user, string $password): void {
                    // A new remember token signs out the devices that ticked "Rester connecté".
                    $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

                    event(new PasswordReset($user));
                    Activity::record('auth.password_reset', __('admin.activity.password_reset', ['name' => $user->name]), 'user:'.$user->getKey());
                },
            );
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.password.reset', ['token' => $token, 'email' => $email])
                ->with('error', __('admin.password.unavailable'));
        }

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return $this->invalid($request, 'admin.auth.reset', ['linkFailed' => true] + $data, ['email' => __('admin.password.invalid_link')]);
        }

        return redirect()->route('admin.login')->with('status', __('admin.password.done'));
    }

    /**
     * @return array{mailable: bool, minutes: int}
     */
    private function forgotData(): array
    {
        return ['mailable' => self::canSendMail(), 'minutes' => AdminResetPassword::expireMinutes()];
    }

    private function broker(): PasswordBroker
    {
        return Password::broker();
    }

    /**
     * The site address put in the e-mailed link: this request's own only when its host is one the
     * site is configured for (APP_URL, CMS_PRIMARY_URL) — a forged Host or X-Forwarded-Host header
     * must never receive a token — else the configured address.
     */
    private function trustedRoot(Request $request): string
    {
        $configured = array_values(array_filter(
            [config('app.url'), config('cms.primary_url')],
            fn (mixed $url): bool => is_string($url) && preg_match('#^https?://[^/\s?\#]+#i', $url) === 1,
        ));

        foreach ($configured as $url) {
            if (strcasecmp((string) parse_url($url, PHP_URL_HOST), $request->getHost()) === 0) {
                return $request->root();
            }
        }

        return rtrim($configured[0] ?? $request->root(), '/');
    }
}
