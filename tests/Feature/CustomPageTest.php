<?php

namespace Tests\Feature;

use App\Artists\ArtistDirectory;
use App\Models\Artist;
use App\Models\CmsService;
use App\Models\CustomPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * Free pages on the public site (docs/CMS.md §7.4, §13 A3/F28): /{slug} with its hero, cover photo,
 * Markdown body (sanitized), CTA band; 404 for unknown or unpublished pages except an administrator's
 * preview; the footer links; the sitemap entries.
 */
class CustomPageTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    private function page(array $attributes = []): CustomPage
    {
        return CustomPage::query()->create($attributes + [
            'slug' => 'agenda',
            'title_fr' => 'Agenda 4D2',
            'title_en' => 'Diary 4D2',
            'body_fr' => "## Prochains ateliers\n\nFresque **samedi** à 14 h.",
            'body_en' => "## Next workshops\n\nMural on **Saturday** at 2 pm.",
            'meta_fr' => 'Les rendez-vous de l’atelier.',
            'is_published' => true,
        ]);
    }

    public function test_a_published_page_renders_in_the_site_layout(): void
    {
        $this->page();

        $this->get('/agenda')->assertOk()
            ->assertSee('<title>Agenda 4D2 · '.config('atelier.name').'</title>', false)
            ->assertSee('<meta name="description" content="Les rendez-vous de l’atelier.">', false)
            ->assertSee('class="page-custom"', false)
            ->assertSee('css/pages/custom.css', false)
            ->assertSee('class="prose custom-page__body"', false)
            ->assertSee('<h2>Prochains ateliers</h2>', false)
            ->assertSee('<strong>samedi</strong>', false)
            ->assertSee('class="cta-band"', false)
            ->assertSee(__('pages.cta.title'))
            ->assertSee(route('contact'), false)
            ->assertDontSee(__('pages.preview'));
    }

    public function test_the_english_version_falls_back_to_french_when_untranslated(): void
    {
        $this->page();
        $this->page(['slug' => 'charte', 'title_fr' => 'Charte 8K3', 'title_en' => null, 'body_en' => null, 'body_fr' => 'Texte de la charte.', 'meta_fr' => null]);

        $this->get('/agenda?lang=en')->assertOk()
            ->assertSee('Diary 4D2')
            ->assertSee('<h2>Next workshops</h2>', false)
            ->assertDontSee(__('pages.french_only', [], 'en'));

        $this->get('/charte?lang=en')->assertOk()
            ->assertSee('Charte 8K3')
            ->assertSee('Texte de la charte.')
            ->assertSee('lang="fr"', false)
            ->assertSee(__('pages.french_only', [], 'en'))
            // No description: the start of the body.
            ->assertSee('<meta name="description" content="Texte de la charte.">', false);
    }

    public function test_the_cover_photo_is_shown_in_the_hero_and_shared(): void
    {
        $this->admin();
        $photo = $this->uploadPhoto(['alt_fr' => 'Fresque du marché']);
        Auth::logout();
        $this->page(['cover_media_id' => $photo->id]);

        $this->get('/agenda')->assertOk()
            ->assertSee('class="custom-page__img"', false)
            ->assertSee(route('media.show', $photo->ulid.'-960.webp'), false)
            ->assertSee('alt="Fresque du marché"', false)
            ->assertSee('width="1600" height="1200"', false)
            ->assertSee('<meta property="og:image" content="'.route('media.show', $photo->ulid.'-960.webp').'">', false);
    }

    public function test_markdown_is_sanitized(): void
    {
        $this->admin();
        $photo = $this->uploadPhoto();
        Auth::logout();

        $this->page(['body_fr' => implode("\n\n", [
            '<script>alert("x")</script>',
            '<img src="x" onerror="alert(1)">',
            '[piège](javascript:alert(1))',
            '![Pisteur](https://tracker.example/pixel.png)',
            '![Notre fresque](/media/'.$photo->ulid.'.webp)',
            '[Nos services](/services)',
        ])]);

        $response = $this->get('/agenda')->assertOk();
        $html = (string) $response->getContent();
        $body = substr($html, (int) strpos($html, 'custom-page__body'));
        $body = substr($body, 0, (int) strpos($body, '</div>'));

        $this->assertStringNotContainsString('<script>alert', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('href="javascript:', $body);
        $this->assertStringNotContainsString('<img src="https://tracker.example', $body);
        $this->assertStringContainsString('<a href="https://tracker.example/pixel.png">Pisteur</a>', $body);
        $this->assertMatchesRegularExpression('#<img loading="lazy" decoding="async" src="/media/'.$photo->ulid.'\.webp" alt="Notre fresque"#', $body);
        $this->assertStringContainsString('<a href="/services">Nos services</a>', $body);
    }

    public function test_unknown_and_unpublished_pages_are_not_found_for_visitors(): void
    {
        $this->page(['slug' => 'brouillon', 'is_published' => false]);

        $this->get('/page-inconnue')->assertNotFound();
        $this->get('/brouillon')->assertNotFound();
        $this->get('/mentions-legales')->assertNotFound(); // seeded unpublished

        // Signed in without the admin right: still a 404.
        $this->admin(['is_admin' => false]);
        $this->get('/brouillon')->assertNotFound();
    }

    public function test_administrators_preview_unpublished_pages(): void
    {
        $this->page(['slug' => 'brouillon', 'title_fr' => 'Brouillon 6P1', 'is_published' => false]);
        $this->admin();

        $this->get('/brouillon')->assertOk()
            ->assertSee('Brouillon 6P1')
            ->assertSee(__('pages.preview'));

        $this->get('/mentions-legales')->assertOk()->assertSee(__('pages.preview'));
        $this->get('/page-inconnue')->assertNotFound();

        // A published page shows no preview banner.
        $this->page();
        $this->get('/agenda')->assertOk()->assertDontSee(__('pages.preview'));
    }

    public function test_a_page_only_the_cms_knows_answers_503_while_the_database_is_unreachable(): void
    {
        $this->breakDatabase();

        $this->get('/agenda')->assertStatus(503)->assertHeader('Retry-After', '300');
    }

    public function test_the_footer_partial_lists_the_published_footer_pages_in_order(): void
    {
        $this->assertSame('', trim(view('partials.footer-pages')->render()), 'nothing without footer pages');

        $this->page(['slug' => 'agenda', 'title_fr' => 'Agenda', 'title_en' => 'Diary', 'position' => 2]);
        $this->page(['slug' => 'charte', 'title_fr' => 'Charte', 'title_en' => null, 'position' => 1]);
        $this->page(['slug' => 'cache', 'title_fr' => 'Hors pied de page', 'in_footer' => false]);
        $this->page(['slug' => 'brouillon', 'title_fr' => 'Brouillon', 'is_published' => false]);

        $html = view('partials.footer-pages')->render();
        $this->assertStringContainsString(__('pages.footer.heading'), $html);
        $this->assertStringContainsString('class="site-footer__list"', $html);
        $this->assertMatchesRegularExpression('#/charte"\s*>Charte</a>.*/agenda"\s*>Agenda</a>#s', $html);
        $this->assertStringNotContainsString('Hors pied de page', $html);
        $this->assertStringNotContainsString('Brouillon', $html);

        app()->setLocale('en');
        $this->assertStringContainsString('>Diary</a>', view('partials.footer-pages')->render());
    }

    public function test_the_sitemap_lists_published_pages_and_custom_services(): void
    {
        $this->page();
        CustomPage::query()->where('slug', 'agenda')->update(['updated_at' => '2026-09-30 10:00:00']);
        $this->page(['slug' => 'brouillon', 'is_published' => false]);
        CmsService::query()->create([
            'slug' => 'vitrail-participatif',
            'is_custom' => true,
            'is_published' => true,
            'content' => ['fr' => ['title' => 'Vitrail participatif', 'short' => 'Un vitrail', 'tagline' => 'La lumière', 'intro' => 'Ensemble.']],
        ]);

        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.route('pages.custom', 'agenda').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('pages.custom', 'agenda').'?lang=en</loc>', $xml);
        $this->assertStringContainsString('<lastmod>2026-09-30T10:00:00', $xml);
        $this->assertStringContainsString('<loc>'.route('services.show', 'vitrail-participatif').'</loc>', $xml);
        $this->assertStringNotContainsString('brouillon', $xml);
        $this->assertStringNotContainsString('mentions-legales', $xml);
        $this->assertStringNotContainsString('/admin', $xml);
    }

    public function test_the_sitemap_merges_the_artist_pages_when_that_module_exists(): void
    {
        if (! class_exists(ArtistDirectory::class) || ! class_exists(Artist::class) || ! Route::has('artists.show')) {
            $this->markTestSkipped('The artist pages module is not installed.');
        }

        Artist::query()->create(['slug' => 'awa-pehouet-sitemap', 'name' => 'Awa Pehouet', 'is_published' => true]);

        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.route('artists.show', 'awa-pehouet-sitemap').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('artists.show', 'awa-pehouet-sitemap').'?lang=en</loc>', $xml);
    }
}
