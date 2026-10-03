<?php

namespace Tests\Feature;

use App\Support\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every art service has its own page (at least 20 services, each with its scene). */
class ServicePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_atelier_offers_at_least_twenty_services(): void
    {
        $this->assertGreaterThanOrEqual(20, app(ServiceCatalog::class)->count());
    }

    public function test_every_service_has_its_own_page_in_both_languages(): void
    {
        $catalog = app(ServiceCatalog::class);

        foreach ($catalog->slugs() as $slug) {
            $fr = $catalog->find($slug, 'fr');
            $en = $catalog->find($slug, 'en');

            // ?lang=fr: the session remembers the language chosen on the previous iteration.
            $this->get('/services/'.$slug.'?lang=fr')
                ->assertOk()
                ->assertViewHas('service', fn (array $service) => $service['slug'] === $slug)
                ->assertViewHas(['prev', 'next', 'related', 'inspirations'])
                ->assertSee(e($fr['title']), false)
                ->assertSee('scene--'.$slug, false)
                ->assertSee('css/scenes/'.$slug.'.css', false)
                ->assertSee('"@type":"Service"', false);

            $this->get('/services/'.$slug.'?lang=en')->assertOk()->assertSee(e($en['title']), false);
        }
    }

    public function test_the_index_lists_every_service(): void
    {
        $response = $this->get('/services')->assertOk();

        foreach (app(ServiceCatalog::class)->all() as $service) {
            $response->assertSee(e($service['title']), false)->assertSee($service['url'], false);
        }
    }

    public function test_unknown_service_is_a_404(): void
    {
        $this->get('/services/service-imaginaire')->assertNotFound();
        $this->get('/services/Peinture_Murale')->assertNotFound();
    }

    public function test_service_page_links_to_a_prefilled_quote_and_its_neighbours(): void
    {
        $catalog = app(ServiceCatalog::class);
        $first = $catalog->all()->first();
        $neighbors = $catalog->neighbors($first['slug']);

        $this->get($first['url'])
            ->assertSee(route('contact', ['service' => $first['slug']]), false)
            ->assertSee($neighbors['prev']['url'], false)
            ->assertSee($neighbors['next']['url'], false);
    }

    public function test_contact_form_preselects_the_requested_service(): void
    {
        $this->get('/contact?service=sculpture')
            ->assertOk()
            ->assertViewHas('selected', 'sculpture')
            ->assertSee('value="sculpture" selected', false);

        $this->get('/contact?service=n-importe-quoi')->assertViewHas('selected', null);
    }
}
