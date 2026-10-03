<?php

namespace Tests\Feature\Cms;

use App\Http\Middleware\AdminHeaders;
use App\Http\Middleware\ApplyCms;
use App\Http\Middleware\EnsureCmsReady;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/** Routing around the CMS (docs/CMS.md §5, §13 A5/D17/D18/E23): admin wiring, robots, health check, 404 page. */
class RoutesTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    /**
     * The middleware a request to $uri runs through, in order (classes, parameters stripped).
     *
     * @return list<string>
     */
    private function middlewareOf(string $uri, string $method = 'GET'): array
    {
        app(HttpKernel::class); // syncs the middleware groups, aliases and priorities to the router
        $route = app('router')->getRoutes()->match(request()->create($uri, $method));

        return array_map(fn (mixed $middleware): string => is_string($middleware) ? explode(':', $middleware)[0] : '', app('router')->gatherRouteMiddleware($route));
    }

    public function test_machine_endpoints_skip_the_session_and_the_cms_and_photos_are_not_throttled(): void
    {
        foreach (['/media/'.str_repeat('a', 26).'.webp', '/atelier-numerique/oeuvre.svg', '/sitemap.xml', '/robots.txt'] as $uri) {
            $middleware = $this->middlewareOf($uri);

            $this->assertNotContains(StartSession::class, $middleware, $uri);
            $this->assertNotContains(ApplyCms::class, $middleware, $uri);
        }

        $this->assertNotContains(ThrottleRequests::class, $this->middlewareOf('/media/'.str_repeat('a', 26).'.webp'));
        $this->assertContains(ApplyCms::class, $this->middlewareOf('/a-propos'));
    }

    public function test_admin_routes_run_their_middleware_in_a_safe_order(): void
    {
        $middleware = $this->middlewareOf('/admin/photos/1');
        $position = fn (string $class): int|false => array_search($class, $middleware, true);

        foreach ([AdminHeaders::class, Authenticate::class, AuthenticateSession::class, Authorize::class, EnsureCmsReady::class, SubstituteBindings::class] as $class) {
            $this->assertNotFalse($position($class), $class);
        }

        $this->assertLessThan($position(Authenticate::class), $position(AdminHeaders::class), 'admin headers wrap the guest redirect');
        $this->assertLessThan($position(SubstituteBindings::class), $position(EnsureCmsReady::class), 'the schema check before model binding');
        $this->assertLessThan($position(EnsureCmsReady::class), $position(Authenticate::class));

        $login = $this->middlewareOf('/admin/connexion');
        $this->assertNotContains(Authenticate::class, $login);
        $this->assertNotContains(Authorize::class, $login);
        $this->assertNotContains(Authorize::class, $this->middlewareOf('/admin/deconnexion', 'POST'), 'a non-admin can still sign out');
        $this->assertNotContains(EnsureCmsReady::class, $this->middlewareOf('/admin/maintenance'));
    }

    public function test_a_signed_in_visitor_is_recognised_without_any_query(): void
    {
        $user = User::factory()->create();
        $this->get('/a-propos?lang=fr')->assertOk();
        DB::table('translation_overrides')->insert(['locale' => 'fr', 'group' => 'ui', 'key' => 'nav.about', 'value' => 'Version fraîche 7F3A']);

        $this->get('/a-propos?lang=fr')->assertDontSee('Version fraîche 7F3A');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->withSession([Auth::guard('web')->getName() => $user->getKey()])->get('/a-propos?lang=fr')->assertSee('Version fraîche 7F3A');

        $users = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], '"users"'));
        $this->assertSame([], array_values($users), 'the public page never reads the users table');
    }

    public function test_robots_keeps_crawlers_out_of_the_admin(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("Disallow: /admin\n", false)
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }

    public function test_the_sitemap_never_lists_admin_urls(): void
    {
        $this->assertStringNotContainsString('/admin', $this->get('/sitemap.xml')->assertOk()->getContent());
    }

    public function test_the_health_check_still_answers(): void
    {
        $this->get('/up')->assertOk();

        $this->dropCmsTables();
        $this->get('/up')->assertOk();
    }

    public function test_unknown_single_segment_urls_render_the_404_page(): void
    {
        foreach (['/une-page-qui-n-existe-pas', '/mentions-legales', '/a', '/Majuscules', '/deux/segments'] as $uri) {
            $this->get($uri)->assertNotFound()->assertSee('<html', false);
        }

        $this->get('/une-page-qui-n-existe-pas?lang=fr')->assertSee('Œuvre introuvable');
    }

    public function test_admin_routes_are_registered_before_the_free_page_catch_all(): void
    {
        $this->assertSame(url('/admin'), route('admin.dashboard'));
        $this->assertSame(url('/admin/connexion'), route('admin.login'));
        $this->assertSame('admin.dashboard', app('router')->getRoutes()->match(request()->create('/admin'))->getName());
        $this->assertSame('pages.custom', app('router')->getRoutes()->match(request()->create('/mentions-legales'))->getName());
        $this->assertSame('media.show', app('router')->getRoutes()->match(request()->create('/media/'.str_repeat('a', 26).'-480.webp'))->getName());
    }

    public function test_guests_are_sent_to_the_login_and_admins_away_from_it(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/photos')->assertRedirect(route('admin.login'));

        $this->admin();
        $this->get('/admin/connexion')->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_responses_are_never_indexed_nor_cached(): void
    {
        $response = $this->get('/admin/connexion');

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertTrue($response->headers->getCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->getCacheControlDirective('private'));
    }

    public function test_admin_screens_wait_for_the_cms_tables(): void
    {
        $this->admin();
        $this->assertNotSame(route('admin.maintenance'), $this->get('/admin')->headers->get('Location'));

        $this->dropCmsTables();

        $this->get('/admin')->assertRedirect(route('admin.maintenance'))->assertSessionHas('error', 'admin.maintenance.tables_missing');
        $this->get('/admin/photos')->assertRedirect(route('admin.maintenance'));
        $this->getJson('/admin/photos')->assertStatus(503)->assertJsonStructure(['message', 'missing']);
        $this->assertNotSame(route('admin.maintenance'), $this->get('/admin/maintenance')->headers->get('Location'));
    }

    public function test_media_urls_are_stateless(): void
    {
        $this->withCookie(config('session.cookie'), 'whatever')->get('/media/'.str_repeat('a', 26).'.webp')->assertNotFound();

        $this->assertSame(0, User::query()->count());
    }
}
