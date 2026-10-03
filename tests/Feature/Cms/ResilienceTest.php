<?php

namespace Tests\Feature\Cms;

use App\Cms\Activity;
use App\Cms\Cms;
use App\Cms\DatabaseHealth;
use App\Models\CustomPage;
use App\Models\Setting;
use App\Models\TranslationOverride;
use App\Support\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * The public site never breaks because of the CMS (docs/CMS.md §1, §13 A): without the CMS tables
 * or without a database, every page renders with the defaults of the files; a failed load is
 * remembered for cms.cache.retry seconds instead of being retried on every request; the last-good
 * copy is served while the database is down; URLs only the CMS knows answer 503 (not 404) when no
 * copy exists.
 */
class ResilienceTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    /** @return list<string> */
    private function publicUris(): array
    {
        $slug = app(ServiceCatalog::class)->slugs()[0];

        return ['/', '/services', '/services/'.$slug, '/a-propos', '/communaute', '/galerie', '/atelier-numerique', '/mouvement',
            '/contact', '/?lang=en', '/services/'.$slug.'?lang=en', '/robots.txt'];
    }

    private function defaultAboutLabel(string $locale = 'fr'): string
    {
        return app('translator')->getLoader()->files()->load($locale, 'ui')['nav']['about'];
    }

    /** A default connection that throws on connect, counting the attempts. */
    private function countingUnreachableDatabase(int &$attempts): void
    {
        DB::extend('cms_counting', function () use (&$attempts) {
            $attempts++;

            throw new RuntimeException('could not connect to server: Connection refused');
        });
        $this->useDefaultConnection('cms_counting', ['driver' => 'cms_counting']);
    }

    public function test_every_public_page_renders_the_file_defaults_when_the_cms_tables_are_missing(): void
    {
        Log::spy();
        TranslationOverride::query()->create(['locale' => 'fr', 'group' => 'ui', 'key' => 'nav.about', 'value' => 'Texte du CMS']);
        Setting::query()->create(['key' => 'contact.email', 'value' => 'cms@pehouet.test']);

        $this->dropCmsTables();

        foreach ([...$this->publicUris(), '/sitemap.xml'] as $uri) {
            $this->get($uri)->assertOk()->assertDontSee('Texte du CMS');
        }

        $this->get('/a-propos?lang=fr')->assertSee($this->defaultAboutLabel())->assertDontSee('cms@pehouet.test');
        $this->assertFalse(app(Cms::class)->available());
        $this->assertFalse(app(Cms::class)->blind(), 'the database answered: the CMS is known to be empty');
        $this->assertTrue(app(DatabaseHealth::class)->available(), 'a missing table is not an outage');
        $this->assertSame(count(glob(resource_path('content/services/*.php'))), app(ServiceCatalog::class)->count());
        $this->get('/une-page-inconnue')->assertNotFound()->assertSee('<html', false);
        $this->get('/services/service-inconnu')->assertNotFound();
    }

    public function test_every_public_page_renders_when_the_database_cannot_be_reached(): void
    {
        Log::spy();
        $this->breakDatabase();

        foreach ($this->publicUris() as $uri) {
            $this->get($uri)->assertOk();
        }

        $this->get('/communaute?lang=fr')->assertSee($this->defaultAboutLabel());
        $this->get('/communaute?lang=en')->assertSee($this->defaultAboutLabel('en'));
        $this->get('/media/'.str_repeat('a', 26).'.webp')->assertStatus(503)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertFalse(cms()->available());
        $this->assertTrue(cms()->blind());
        $this->assertNull(cms()->media(1));
        $this->assertSame([], cms()->gallery());
        $this->assertNull(ap_share_image());
    }

    public function test_urls_only_the_cms_knows_answer_503_while_it_is_blind(): void
    {
        Log::spy();
        $this->breakDatabase();

        foreach (['/une-page-inconnue', '/services/service-cree-dans-le-cms', '/sitemap.xml'] as $uri) {
            $response = $this->get($uri)->assertStatus(503)->assertHeader('Retry-After', '300');
            $this->assertTrue($response->headers->getCacheControlDirective('no-store'), $uri);
        }

        $this->get('/une-page-inconnue')->assertSee('<html', false);
        $this->get('/services/'.app(ServiceCatalog::class)->slugs()[0])->assertOk();
        $this->get('/deux/segments')->assertNotFound();
    }

    public function test_the_last_good_copy_is_served_while_the_database_is_down(): void
    {
        Log::spy();
        TranslationOverride::query()->create(['locale' => 'fr', 'group' => 'ui', 'key' => 'nav.about', 'value' => 'Récit gardé 7F3A']);
        CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda 7F3A', 'body_fr' => 'Prochain atelier jeudi.', 'is_published' => true]);

        $this->get('/a-propos?lang=fr')->assertOk()->assertSee('Récit gardé 7F3A');
        $this->assertSame('Prochain atelier jeudi.', cms()->page('agenda')['body']['fr']);

        // The cached snapshot expires while the database is unreachable.
        $this->travel(config('cms.cache.ttl') + 1)->seconds();
        $this->breakDatabase();
        cache()->forget(config('cms.cache.key'));

        $this->get('/a-propos?lang=fr')->assertOk()->assertSee('Récit gardé 7F3A');
        $this->assertFalse(cms()->available());
        $this->assertTrue(cms()->stale());
        $this->assertFalse(cms()->blind());
        $this->assertSame('Prochain atelier jeudi.', cms()->page('agenda')['body']['fr'], 'the body has its own last-good copy');
        $this->assertNull(cms()->page('inconnue'));
        $this->get('/une-page-inconnue')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_a_free_page_whose_body_cannot_be_read_answers_503(): void
    {
        Log::spy();
        CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda', 'body_fr' => 'Corps', 'is_published' => true]);
        $this->assertTrue(cms()->available()); // the snapshot and its last-good copy are stored; the body was never read

        $this->breakDatabase();
        cms()->reset();
        app(DatabaseHealth::class)->reset();

        $this->assertNull(cms()->page('agenda'));
        $this->assertTrue(cms()->blind());
    }

    public function test_a_failed_load_is_cached_for_the_retry_window_and_logged_once(): void
    {
        Log::spy();
        $attempts = 0;
        $this->countingUnreachableDatabase($attempts);

        $this->get('/')->assertOk();
        $this->get('/services')->assertOk();
        $this->get('/contact?lang=en')->assertOk();

        $this->assertSame(1, $attempts, 'the database is not retried within the retry window');
        $this->assertSame(['version' => Cms::VERSION, 'failed' => true, 'outage' => true], cache()->get(config('cms.cache.key')));
        $this->assertGreaterThan(now()->getTimestamp(), cache()->get(DatabaseHealth::KEY), 'the circuit breaker is open');
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'CMS data unavailable'))->once();
        Log::shouldHaveReceived('notice')->withArgs(fn (string $message): bool => str_contains($message, 'circuit breaker'))->once();

        $this->travel(config('cms.cache.retry') + 1)->seconds();
        $this->get('/')->assertOk();

        $this->assertSame(2, $attempts);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'CMS data unavailable'))->twice();
    }

    public function test_the_data_comes_back_after_the_retry_window(): void
    {
        Log::spy();
        TranslationOverride::query()->create(['locale' => 'fr', 'group' => 'ui', 'key' => 'nav.about', 'value' => 'Récit CMS 7F3A']);
        cache()->put(config('cms.cache.key'), ['version' => Cms::VERSION, 'failed' => true, 'outage' => true], config('cms.cache.retry'));

        $this->get('/a-propos?lang=fr')->assertOk()->assertDontSee('Récit CMS 7F3A');

        $this->travel(config('cms.cache.retry') + 1)->seconds();

        $this->get('/a-propos?lang=fr')->assertOk()->assertSee('Récit CMS 7F3A');
        $this->assertTrue(cms()->available());
    }

    public function test_an_open_breaker_keeps_every_public_touchpoint_off_the_database(): void
    {
        Log::spy();
        config(['atelier.contact.notify' => 'atelier@pehouet.test']);
        $attempts = 0;
        $this->countingUnreachableDatabase($attempts);

        $this->get('/')->assertOk();
        $this->post('/contact', [
            'name' => 'Awa', 'email' => 'awa@pehouet.test', 'message' => 'Une fresque pour notre école, possible ?', 'consent' => '1',
        ])->assertRedirect();
        $this->get('/une-page-libre')->assertStatus(503);
        Activity::record('test.action', 'Rien');

        $this->assertSame(1, $attempts, 'one connection attempt opened the breaker; nothing else tried');
    }
}
