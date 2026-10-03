<?php

namespace Tests\Feature\Cms;

use App\Cms\DatabaseHealth;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * Error answers of the admin (docs/CMS.md §13 A3, C10, E23): a database error gives a friendly
 * French 503 page — never a 500 —, a request over the size limit a French 413, and the admin
 * headers come with every answer, the guest redirect included.
 */
class AdminErrorPagesTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    private function assertAdminHeaders(TestResponse $response): void
    {
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertTrue($response->headers->getCacheControlDirective('no-store'));
    }

    /** A session that holds the login of $user (as after signing in), without loading the user. */
    private function signedInSession(User $user): array
    {
        return [Auth::guard('web')->getName() => $user->getKey()];
    }

    public function test_the_guest_redirect_carries_the_admin_headers(): void
    {
        $response = $this->get('/admin')->assertRedirect(route('admin.login'));

        $this->assertAdminHeaders($response);
    }

    public function test_an_unreachable_database_gives_the_friendly_page_not_a_500(): void
    {
        Log::spy();
        $user = User::factory()->create(['is_admin' => true]);
        $this->breakDatabase();

        $response = $this->withSession($this->signedInSession($user))->get('/admin/photos?lang=fr');

        $response->assertStatus(503)
            ->assertSee(__('admin.unavailable.heading', [], 'fr'))
            ->assertSee('supabase.com')
            ->assertSee('Restore project');
        $this->assertAdminHeaders($response);
        $this->assertNotNull($response->headers->get('Content-Security-Policy') ?? $response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertFalse(app(DatabaseHealth::class)->available(), 'the outage opened the breaker');

        $this->withSession($this->signedInSession($user))->getJson('/admin/photos')
            ->assertStatus(503)
            ->assertExactJson(['message' => __('admin.unavailable.json_outage', [], 'fr')]);
    }

    public function test_a_remember_cookie_with_an_unreachable_database_keeps_the_login_page_friendly(): void
    {
        Log::spy();
        $user = User::factory()->create(['is_admin' => true, 'remember_token' => 'jeton-memoire']);
        $cookie = Auth::guard('web')->getRecallerName();
        $this->breakDatabase();

        $response = $this->withCookie($cookie, $user->getKey().'|jeton-memoire|'.$user->getAuthPassword())->get('/admin/connexion');

        $response->assertStatus(503)->assertSee(__('admin.unavailable.heading', [], 'fr'));
        $this->assertAdminHeaders($response);
    }

    public function test_an_error_the_database_answered_points_to_the_maintenance_page(): void
    {
        Log::spy();
        Route::middleware('web')->get('/admin/essai-base', fn () => DB::table('media')->count());
        $this->admin();
        Schema::dropIfExists('media_slots');
        Schema::drop('media'); // the migration ran, the table is gone: the server answers with an error

        $response = $this->get('/admin/essai-base')->assertStatus(503);

        $response->assertSee(__('admin.unavailable.error_heading', [], 'fr'))->assertSee(route('admin.maintenance'), false);
        $this->assertTrue(app(DatabaseHealth::class)->available(), 'not an outage: the breaker stays closed');
    }

    public function test_pending_cms_migrations_are_detected_before_route_model_binding(): void
    {
        $this->admin();
        DB::table('migrations')->where('migration', '2026_10_03_000003_create_media_table')->delete();

        $this->get('/admin/photos/999')->assertRedirect(route('admin.maintenance'));
        $this->getJson('/admin/photos/999')->assertStatus(503)->assertJsonPath('missing', ['2026_10_03_000003_create_media_table']);
    }

    public function test_a_request_over_the_size_limit_gets_a_french_413(): void
    {
        $this->admin();
        $server = ['CONTENT_LENGTH' => (string) (64 * 1024 * 1024), 'CONTENT_TYPE' => 'multipart/form-data; boundary=x'];

        $json = $this->call('POST', '/admin/photos', [], [], [], $server + ['HTTP_ACCEPT' => 'application/json']);
        $json->assertStatus(413);
        $this->assertStringContainsString('trop lourds', $json->json('message'));
        $this->assertAdminHeaders($json);

        $page = $this->call('POST', '/admin/photos', [], [], [], $server + ['HTTP_REFERER' => url('/admin/galerie')]);
        $page->assertStatus(413)->assertSee(__('admin.too_large.heading', [], 'fr'))->assertSee(url('/admin/galerie'), false);

        $outside = $this->call('POST', '/admin/photos', [], [], [], $server + ['HTTP_REFERER' => 'https://evil.example/admin']);
        $outside->assertSee(route('admin.dashboard'), false)->assertDontSee('evil.example');

        // Outside the admin, Laravel's own answer.
        $this->call('POST', '/contact', [], [], [], $server)->assertStatus(413)->assertDontSee(__('admin.too_large.heading', [], 'fr'));
    }
}
