<?php

namespace Tests\Feature;

use App\Support\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every public page renders, in French by default and in English on request,
 * with the view data of docs/ARCHITECTURE.md §3.
 */
class PagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, list<string>}> */
    public static function pages(): array
    {
        return [
            'home' => ['/', ['services', 'categories', 'galleryPreview', 'animationCount', 'artStyles']],
            'services' => ['/services', ['services', 'categories']],
            'about' => ['/a-propos', ['services', 'categories', 'animationCount']],
            'community' => ['/communaute', ['services', 'categories']],
            'gallery' => ['/galerie', ['artworks', 'styles']],
            'generator' => ['/atelier-numerique', ['styles', 'defaultStyle', 'sizes', 'artUrl']],
            'motion' => ['/mouvement', ['animations', 'groups', 'total', 'scenes']],
            'contact' => ['/contact', ['services', 'selected', 'budgets']],
        ];
    }

    /** @param list<string> $variables */
    #[DataProvider('pages')]
    public function test_page_renders_in_french_by_default(string $uri, array $variables): void
    {
        $response = $this->get($uri)->assertOk()->assertSee('<html lang="fr"', false);

        foreach ($variables as $variable) {
            $response->assertViewHas($variable);
        }
    }

    /** @param list<string> $variables */
    #[DataProvider('pages')]
    public function test_page_renders_in_english(string $uri, array $variables): void
    {
        $this->get($uri.'?lang=en')->assertOk()->assertSee('<html lang="en"', false);
    }

    public function test_french_stays_the_default_whatever_the_browser_language(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/')
            ->assertOk()
            ->assertSee('<html lang="fr"', false)
            ->assertSee('Découvrir nos services');
    }

    public function test_home_shows_key_french_and_english_copy(): void
    {
        $this->get('/')->assertSee('L’art au service de la communauté')->assertSee('Manifeste');
        $this->get('/?lang=en')->assertSee('Art in the service of the community')->assertSee('Manifesto');
    }

    public function test_layout_declares_french_as_canonical_and_english_as_alternate(): void
    {
        $this->get('/a-propos')
            ->assertSee('<link rel="canonical" href="'.url('/a-propos').'">', false)
            ->assertSee('hreflang="fr" href="'.url('/a-propos').'"', false)
            ->assertSee('hreflang="en" href="'.url('/a-propos').'?lang=en"', false)
            ->assertSee('hreflang="x-default" href="'.url('/a-propos').'"', false);

        $this->get('/a-propos?lang=en')->assertSee('<link rel="canonical" href="'.url('/a-propos').'?lang=en">', false);
    }

    public function test_header_and_footer_list_every_service(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (app(ServiceCatalog::class)->all() as $service) {
            $response->assertSee($service['url'], false);
        }
    }

    public function test_unknown_page_uses_the_branded_404(): void
    {
        $this->get('/une-page-qui-n-existe-pas')
            ->assertNotFound()
            ->assertSee('Œuvre introuvable');

        $this->get('/une-page-qui-n-existe-pas?lang=en')->assertNotFound()->assertSee('Artwork not found');
    }

    public function test_no_inline_scripts_except_json_ld(): void
    {
        foreach (['/', '/services/peinture-murale', '/atelier-numerique', '/mouvement', '/contact'] as $uri) {
            $html = $this->get($uri)->getContent();
            preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/i', $html, $matches);
            foreach ($matches[1] as $attributes) {
                $this->assertStringContainsString('application/ld+json', $attributes, "Inline script on {$uri}");
            }
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $html, "Inline event handler on {$uri}");
        }
    }
}
