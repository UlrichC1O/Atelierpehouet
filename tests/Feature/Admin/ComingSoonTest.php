<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ComingSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * Admin sections still being built (docs/CMS.md phase 2) never answer 501: a GET shows the "coming
 * soon" page (HTTP 200, admin layout, a way back), any other method goes back with an error flash.
 *
 * The routes are found through the controllers that still use the ComingSoon trait, so this test
 * shrinks by itself as the phase-2 agents build the real screens.
 */
class ComingSoonTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    /** Values for the route parameters (the stubs never read them). */
    private const PARAMETERS = ['group' => 'home', 'slug' => 'peinture-murale', 'media' => 1, 'page' => 1, 'message' => 1, 'user' => 1];

    public function test_every_section_being_built_opens_a_coming_soon_page(): void
    {
        $this->admin();
        $routes = $this->comingSoonRoutes('GET');

        foreach ($routes as $name => $url) {
            $this->get($url)->assertOk()
                ->assertSee(__('admin.coming_soon.heading'))
                ->assertSee(__('admin.coming_soon.back'))
                ->assertSee('href="'.route('admin.dashboard').'"', false)
                ->assertSee('class="admin', false);
        }

        if ($routes === []) {
            $this->markTestSkipped('Every admin section is built: the ComingSoon trait can go.');
        }
    }

    public function test_the_page_names_the_section_in_french_and_in_english(): void
    {
        $this->admin();
        $routes = $this->comingSoonRoutes('GET');

        if (! isset($routes['admin.settings.edit'])) {
            $this->markTestSkipped('The settings screen is built.');
        }

        $this->get($routes['admin.settings.edit'])->assertOk()
            ->assertSee('<title>'.__('admin.nav.settings'), false)
            ->assertSee('Cette section arrive très bientôt');

        $this->get($routes['admin.settings.edit'].'?lang=en')->assertOk()
            ->assertSee(__('admin.coming_soon.heading', [], 'en'));
    }

    public function test_every_form_of_a_section_being_built_goes_back_with_an_error(): void
    {
        $this->admin();
        $routes = $this->comingSoonRoutes('POST', 'PUT', 'DELETE');
        $from = route('admin.dashboard').'?from=form';

        foreach ($routes as $name => [$method, $url]) {
            $this->from($from)->call($method, $url, ['title' => 'Bonjour'])
                ->assertRedirect($from)
                ->assertSessionHas('error', __('admin.coming_soon.unavailable'));
        }

        if ($routes === []) {
            $this->markTestSkipped('Every admin section is built: the ComingSoon trait can go.');
        }
    }

    public function test_a_form_never_sends_the_owner_to_another_site(): void
    {
        $this->admin();
        $routes = $this->comingSoonRoutes('POST', 'PUT', 'DELETE');

        if ($routes === []) {
            $this->markTestSkipped('Every admin section is built: the ComingSoon trait can go.');
        }

        [$method, $url] = reset($routes);

        foreach (['https://evil.example/admin', 'http://localhost/admin-evil', 'http://localhost/contact'] as $referer) {
            $this->from($referer)->call($method, $url)
                ->assertRedirect(route('admin.dashboard'))
                ->assertSessionHas('error');
        }
    }

    public function test_json_requests_get_a_503_with_a_message(): void
    {
        $this->admin();
        $routes = $this->comingSoonRoutes('POST', 'PUT', 'DELETE');

        if ($routes === []) {
            $this->markTestSkipped('Every admin section is built: the ComingSoon trait can go.');
        }

        [$method, $url] = reset($routes);

        $this->json($method, $url)->assertStatus(503)->assertExactJson(['message' => __('admin.coming_soon.unavailable')]);
    }

    public function test_guests_are_still_sent_to_the_login(): void
    {
        foreach ($this->comingSoonRoutes('GET') as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }

        $this->assertGuest();
    }

    /**
     * Admin routes answered by a controller that still uses the ComingSoon trait.
     *
     * @return array<string, mixed> GET: name ⇒ URL; other methods: name ⇒ [method, URL]
     */
    private function comingSoonRoutes(string ...$methods): array
    {
        $routes = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            $controller = $route->getControllerClass();
            $method = array_values(array_intersect($methods, $route->methods()))[0] ?? null;

            if (! str_starts_with($name, 'admin.') || $method === null || $controller === null
                || ! class_exists($controller) || ! in_array(ComingSoon::class, class_uses_recursive($controller), true)) {
                continue;
            }

            $parameters = array_intersect_key(self::PARAMETERS, array_flip($route->parameterNames()));
            $url = route($name, $parameters);
            $routes[$name] = $methods === ['GET'] ? $url : [$method, $url];
        }

        ksort($routes);

        return $routes;
    }
}
