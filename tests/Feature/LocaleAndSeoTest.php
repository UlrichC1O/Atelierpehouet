<?php

namespace Tests\Feature;

use App\Support\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Language switch (French main, English secondary), sitemap, robots and security headers. */
class LocaleAndSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_to_english_is_remembered(): void
    {
        $this->from('/a-propos')->get('/langue/en')->assertRedirect();
        $this->get('/communaute')->assertSee('<html lang="en"', false)->assertSee('Art is made together');
        $this->get('/langue/fr');
        $this->get('/communaute')->assertSee('<html lang="fr"', false)->assertSee('L’art se fait ensemble');
    }

    public function test_unknown_language_is_a_404(): void
    {
        $this->get('/langue/de')->assertNotFound();
        $this->get('/?lang=de')->assertOk()->assertSee('<html lang="fr"', false);
    }

    public function test_sitemap_lists_every_page_and_service_in_both_languages(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('xml', (string) $response->headers->get('Content-Type'));
        $xml = $response->getContent();

        foreach (app(ServiceCatalog::class)->all() as $service) {
            $this->assertStringContainsString($service['url'], $xml);
        }
        $this->assertStringContainsString('hreflang="en"', $xml);
        simplexml_load_string($xml) ?: $this->fail('sitemap.xml is not valid XML');
    }

    public function test_robots_points_to_the_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
            ->assertSee('Disallow: /atelier-numerique/oeuvre.svg', false);
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotEmpty($response->headers->get('Referrer-Policy'));
        $csp = $response->headers->get('Content-Security-Policy') ?? $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
    }
}
