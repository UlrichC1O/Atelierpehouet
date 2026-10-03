<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Activity;
use App\Cms\DatabaseMigrator;
use App\Cms\SafeUrl;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Sign-in to the admin CMS (docs/CMS.md §7.1, §13 D15–D20).
 *
 * Failed attempts are counted by a limiter of its own (config('cms.login_limiter_store'): Vercel's
 * default cache only lives for one request). While the users table is still empty, the credentials of
 * config('cms.bootstrap_admin') (ADMIN_EMAIL / ADMIN_PASSWORD) create the first administrator — and
 * every table first on a brand-new database. A database (or limiter store) that cannot be reached —
 * Supabase paused, pending migrations… — gives a friendly message on the login form, never a 500.
 */
final class AuthController extends Controller
{
    /** ADMIN_PASSWORD shorter than this never creates an account. */
    private const BOOTSTRAP_MIN_PASSWORD = 10;

    /** Failed sign-ins allowed per hour for one e-mail address, whatever the IP… */
    private const EMAIL_ATTEMPTS_PER_HOUR = 20;

    /** …and from one IP address, whatever the e-mail. */
    private const IP_ATTEMPTS_PER_HOUR = 50;

    public function show(): View
    {
        // "Rester connecté" lasts config('auth.guards.web.remember') minutes (docs/CMS.md §13 D17).
        $remember = config('auth.guards.web.remember');

        return view('admin.auth.login', [
            'rememberDays' => is_numeric($remember) && (int) $remember >= 1440 ? intdiv((int) $remember, 1440) : null,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $validator = Validator::make(
            $request->only('email', 'password'),
            ['email' => ['required', 'string', 'email', 'max:190'], 'password' => ['required', 'string', 'max:1000']],
            [],
            ['email' => __('admin.auth.attributes.email'), 'password' => __('admin.auth.attributes.password')],
        );

        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route('admin.login'));
        }

        $email = Str::lower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        $buckets = $this->buckets($email, (string) $request->ip());
        $lockedFor = null;
        $bootstrapped = false;
        $authenticated = false;

        // The limiter, the bootstrap and the credential check all touch storage: one guard for all.
        try {
            $limiter = $this->limiter();
            $lockedFor = $this->lockedFor($limiter, $buckets, $email, $password);

            if ($lockedFor === null) {
                $bootstrapped = $this->bootstrapAdmin($email, $password);

                // Case-insensitive match: e-mails may have been stored with capitals by other tools.
                // Only administrators (docs/CMS.md §13 D18) get a session at all.
                $authenticated = Auth::attemptWhen([
                    'email' => fn (Builder $query) => $query->whereRaw('LOWER(email) = ?', [$email]),
                    'password' => $password,
                ], fn (User $user): bool => $this->mayAdminister($user), $request->boolean('remember'));

                if ($authenticated) {
                    $limiter->clear($buckets['email_ip']['key']);
                } else {
                    foreach ($buckets as $bucket) {
                        $limiter->hit($bucket['key'], $bucket['decay']);
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);
            // A half-finished sign-in (e.g. the remember token could not be written) must not survive.
            rescue(fn () => Auth::guard()->logout(), report: false);

            return redirect()->route('admin.login')
                ->withInput($request->only('email', 'remember'))
                ->with('error', __('admin.auth.unavailable'));
        }

        if ($lockedFor !== null) {
            event(new Lockout($request));

            // The hourly buckets lock for longer: minutes read better than "3412 secondes".
            $lockedFor > 90
                ? $this->fail('admin.auth.throttle_minutes', ['minutes' => (int) ceil($lockedFor / 60)])
                : $this->fail('admin.auth.throttle', ['seconds' => $lockedFor]);
        }

        if (! $authenticated) {
            $this->fail('admin.auth.failed');
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $this->recordLogin($user);

        $response = redirect()->to($this->intendedUrl($request));

        return $bootstrapped ? $response->with('status', __('admin.auth.bootstrapped')) : $response;
    }

    public function logout(Request $request): RedirectResponse
    {
        $locale = $request->session()->get('locale');

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Keep the language the admin was using on the login form.
        if (is_string($locale)) {
            $request->session()->put('locale', $locale);
        }

        return redirect()->route('admin.login')->with('status', __('admin.auth.logged_out'));
    }

    /**
     * An expired session on an admin URL (HTTP 419, docs/CMS.md §13 D20), rendered from
     * bootstrap/app.php. The CSRF check failed before the locale and header middleware ran, so they
     * are applied here, without any database query. A guest goes back to the page they were on after
     * signing in again, where admin.js restores the draft it kept of the form.
     */
    public function expired(Request $request): Response
    {
        $session = $request->hasSession() ? $request->session() : null;
        $locale = $session?->get('locale');

        if (is_string($locale) && array_key_exists($locale, (array) config('atelier.locales', []))) {
            app()->setLocale($locale);
        }

        // Signed in = a user id in the session (no query: the database may be the problem).
        $signedIn = (bool) rescue(fn (): bool => $session?->has(Auth::guard('web')->getName()) ?? false, false, false);
        $back = $this->adminUrl(rescue(fn () => url()->previous(), null, false));

        if ($request->expectsJson()) {
            $response = response()->json(['message' => __($signedIn ? 'admin.expired.json_reload' : 'admin.expired.json')], 419);
        } else {
            if ($session !== null && ! $signedIn && $back !== null && ! $this->isLoginUrl($back)) {
                $session->put('url.intended', $back);
            }

            $response = response()->view('admin.errors.expired', ['signedIn' => $signedIn, 'back' => $back], 419);

            $response->headers->set(config('app.debug') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy', SecurityHeaders::CONTENT_SECURITY_POLICY);
        }

        foreach (SecurityHeaders::HEADERS + ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store, private', 'Content-Language' => app()->getLocale()] as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * The login limiter (docs/CMS.md §13 D15): a store that outlives the request — never the
     * application-wide limiter, whose store is Vercel's per-request "array" cache.
     */
    private function limiter(): RateLimiter
    {
        return new RateLimiter(Cache::store(config('cms.login_limiter_store')));
    }

    /**
     * The three buckets of the limiter, all checked before the bootstrap comparison: e-mail + IP
     * (config('cms.login_attempts') per minute), e-mail (per hour, any IP) and IP (per hour, any e-mail).
     * The normalised e-mail is hashed: short cache keys (≤ 255 characters on Postgres) and no address
     * in the cache table.
     *
     * @return array{email_ip: array{key: string, max: int, decay: int}, email: array{key: string, max: int, decay: int}, ip: array{key: string, max: int, decay: int}}
     */
    private function buckets(string $email, string $ip): array
    {
        $account = hash('sha256', $email);

        return [
            'email_ip' => ['key' => 'admin-login|'.$account.'|'.$ip, 'max' => max(1, (int) config('cms.login_attempts', 5)), 'decay' => 60],
            'email' => ['key' => 'admin-login-email|'.$account, 'max' => self::EMAIL_ATTEMPTS_PER_HOUR, 'decay' => 3600],
            'ip' => ['key' => 'admin-login-ip|'.$ip, 'max' => self::IP_ATTEMPTS_PER_HOUR, 'decay' => 3600],
        ];
    }

    /**
     * Seconds before the next sign-in is allowed (the longest lock), or null. A brand-new database
     * has no cache table either: then only the ADMIN_EMAIL / ADMIN_PASSWORD credentials go on — they
     * create every table first (docs/CMS.md §13 D16) — and the limiter is asked again.
     *
     * @param  array<string, array{key: string, max: int, decay: int}>  $buckets
     */
    private function lockedFor(RateLimiter $limiter, array $buckets, string $email, string $password): ?int
    {
        try {
            return $this->longestLock($limiter, $buckets);
        } catch (Throwable $e) {
            if (! $this->isBootstrapLogin($email, $password) || rescue(fn (): bool => Schema::hasTable('users'), true, false)) {
                throw $e;
            }

            $this->migrateFreshDatabase();

            return $this->longestLock($limiter, $buckets);
        }
    }

    /**
     * @param  array<string, array{key: string, max: int, decay: int}>  $buckets
     */
    private function longestLock(RateLimiter $limiter, array $buckets): ?int
    {
        $seconds = null;

        foreach ($buckets as $bucket) {
            if ($limiter->tooManyAttempts($bucket['key'], $bucket['max'])) {
                $seconds = max($seconds ?? 1, $limiter->availableIn($bucket['key']));
            }
        }

        return $seconds;
    }

    /**
     * @param  array<string, int|string>  $replace
     */
    private function fail(string $message, array $replace = []): never
    {
        throw ValidationException::withMessages(['email' => __($message, $replace)])
            ->redirectTo(route('admin.login'));
    }

    /**
     * ADMIN_EMAIL (lower-cased, trimmed) and ADMIN_PASSWORD when the bootstrap is enabled: both
     * non-empty strings and a password of at least 10 characters. A half or weak configuration
     * disables it with a warning in the log; no ADMIN_PASSWORD at all (removed after the first
     * sign-in, as the owner is told to) is the normal, silent state.
     *
     * @return array{email: string, password: string}|null
     */
    private function bootstrapCredentials(): ?array
    {
        $configured = (array) config('cms.bootstrap_admin', []);
        $email = is_string($configured['email'] ?? null) ? Str::lower(trim($configured['email'])) : '';
        $password = is_string($configured['password'] ?? null) ? $configured['password'] : '';

        if ($password === '') {
            return null;
        }

        if ($email === '' || mb_strlen($password) < self::BOOTSTRAP_MIN_PASSWORD) {
            Log::warning('Admin bootstrap disabled: set ADMIN_EMAIL and an ADMIN_PASSWORD of at least '.self::BOOTSTRAP_MIN_PASSWORD.' characters (or remove ADMIN_PASSWORD).');

            return null;
        }

        return ['email' => $email, 'password' => $password];
    }

    /** The typed credentials are those of an enabled bootstrap (both compared in constant time). */
    private function isBootstrapLogin(string $email, string $password): bool
    {
        $admin = $this->bootstrapCredentials();

        if ($admin === null) {
            return false;
        }

        $emailMatches = hash_equals($admin['email'], $email);
        $passwordMatches = hash_equals($admin['password'], $password);

        return $emailMatches && $passwordMatches;
    }

    /**
     * The first administrator: created from ADMIN_EMAIL / ADMIN_PASSWORD when those exact
     * credentials are typed while no user exists yet. Never touches an existing account.
     * Returns true when the account was created by this request.
     */
    private function bootstrapAdmin(string $email, string $password): bool
    {
        if (! $this->isBootstrapLogin($email, $password)) {
            return false;
        }

        if (! Schema::hasTable('users')) {
            $this->migrateFreshDatabase();
        }

        try {
            return DB::transaction(function () use ($email, $password): bool {
                // Re-checked inside the transaction; the unique e-mail index stops a parallel twin.
                if (User::query()->exists()) {
                    return false;
                }

                $attributes = [
                    'name' => (string) config('atelier.name', 'Ateliers Pehouet'),
                    'email' => $email,
                    'password' => $password,
                ];
                // The explicit admin right (docs/CMS.md §13 D18), once its column exists.
                if (Schema::hasColumn('users', 'is_admin')) {
                    $attributes['is_admin'] = true;
                }

                (new User)->forceFill($attributes)->save();

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            // Two simultaneous first logins: the other request created the account.
            return false;
        }
    }

    /**
     * A brand-new database (no users table): every migration runs — the code of the Maintenance
     * button — before the first administrator is created (docs/CMS.md §13 D16).
     */
    private function migrateFreshDatabase(): void
    {
        Log::warning('Admin bootstrap: the database has no users table yet, running the migrations.');

        $result = app(DatabaseMigrator::class)->run();

        if (! Schema::hasTable('users')) {
            throw new RuntimeException('The migrations did not create the users table: '.$result['output']);
        }
    }

    /** The admin gate (docs/CMS.md §13 D18) when it is defined. */
    private function mayAdminister(User $user): bool
    {
        return ! Gate::has('admin') || Gate::forUser($user)->allows('admin');
    }

    private function recordLogin(User $user): void
    {
        // App\Cms\Activity (core) never throws; the guard keeps the login working without it.
        if (! class_exists(Activity::class)) {
            return;
        }

        try {
            Activity::record('auth.login', __('admin.activity.login', ['name' => $user->name]), 'user:'.$user->getKey());
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The page a guest was sent away from (session "url.intended"), on this site only (docs/CMS.md
     * §13 E22) and never the login form itself; anything else ⇒ the dashboard.
     */
    private function intendedUrl(Request $request): string
    {
        $dashboard = route('admin.dashboard');
        $intended = $request->session()->pull('url.intended');
        $intended = SafeUrl::internal(is_string($intended) ? $intended : null, $dashboard);

        return $this->isLoginUrl($intended) ? $dashboard : $intended;
    }

    /** A page of the admin on this site (docs/CMS.md §13 E22), or null. */
    private function adminUrl(mixed $url): ?string
    {
        $url = SafeUrl::internal(is_string($url) ? $url : null, '');
        $path = $url === '' ? '' : (string) parse_url($url, PHP_URL_PATH);

        return $path === '/admin' || str_starts_with($path, '/admin/') ? $url : null;
    }

    private function isLoginUrl(string $url): bool
    {
        $loginPath = (string) parse_url(route('admin.login'), PHP_URL_PATH);

        return rtrim((string) parse_url($url, PHP_URL_PATH), '/') === rtrim($loginPath, '/');
    }
}
