<?php

namespace Tests\Feature\Artists;

use App\Models\Artist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Artists\Concerns\BuildsArtists;
use Tests\TestCase;

/** The public artist pages (docs/ARTISTS.md §5): /artistes and /artistes/{slug}. */
class PublicPagesTest extends TestCase
{
    use BuildsArtists, RefreshDatabase;

    private function camille(array $attributes = []): Artist
    {
        $artist = $this->artist($attributes + [
            'name' => 'Camille Durand', 'slug' => 'camille-durand', 'discipline_fr' => 'Peintre & artiste générative',
            'discipline_en' => 'Painter & generative artist', 'location' => 'Lyon, France',
            'statement_fr' => 'Peindre la lumière qui reste.', 'statement_en' => 'Painting the light that stays.',
            'bio_fr' => "Camille Durand travaille entre **peinture** et code.\n\nSon atelier est à Lyon.",
            'website' => 'https://camille-durand.test', 'position' => 1,
        ]);

        $this->artwork($artist, ['title_fr' => 'Nocturne bleu', 'title_en' => 'Blue nocturne', 'year' => '2025',
            'medium_fr' => 'Huile sur toile', 'medium_en' => 'Oil on canvas', 'dimensions' => '100 × 80 cm', 'availability' => 'available', 'position' => 1]);
        $this->artwork($artist, ['title_fr' => 'Champ de blé', 'year' => '2024', 'availability' => 'sold', 'position' => 2]);
        $this->artwork($artist, ['title_fr' => 'Étude masquée', 'is_published' => false, 'position' => 3]);
        $this->exhibition($artist, ['title_fr' => 'Lumières du soir', 'title_en' => 'Evening lights', 'kind' => 'solo', 'venue' => 'Galerie du Pont',
            'city' => 'Lyon', 'year' => 2025, 'starts_on' => '2025-03-01', 'ends_on' => '2025-04-30']);
        $this->exhibition($artist, ['title_fr' => 'Résidence d’été', 'kind' => 'residency', 'year' => 2025, 'starts_on' => '2025-07-01']);
        $this->exhibition($artist, ['title_fr' => 'Premiers pas', 'kind' => 'group', 'year' => 2019, 'venue' => 'Ateliers Pehouet']);

        return $artist;
    }

    public function test_the_index_lists_the_published_artists_in_order(): void
    {
        $this->requireView('artists.index');

        $this->artist(['name' => 'Bruno Morel', 'slug' => 'bruno-morel', 'position' => 3]);
        $this->artist(['name' => 'Ariane Roux', 'slug' => 'ariane-roux', 'position' => 2]);
        $this->camille();
        $this->artist(['name' => 'Zoé Cachée', 'slug' => 'zoe-cachee', 'position' => 0, 'is_published' => false]);

        $response = $this->get('/artistes')->assertOk();

        $response->assertViewHas('available', true);
        $this->assertSame(['camille-durand', 'ariane-roux', 'bruno-morel'], array_column($response->viewData('artists'), 'slug'));
        $response->assertSeeInOrder(['Camille Durand', 'Ariane Roux', 'Bruno Morel']);
        $response->assertSee(route('artists.show', 'camille-durand'), false);
        $response->assertDontSee('Zoé Cachée');
    }

    public function test_the_index_without_artists_is_a_calm_empty_page(): void
    {
        $this->requireView('artists.index');

        $response = $this->get('/artistes')->assertOk();

        $response->assertViewHas('artists', []);
        $response->assertViewHas('available', true);
        $this->get('/artistes?lang=en')->assertOk();
    }

    public function test_an_artist_page_in_french(): void
    {
        $this->requireView('artists.show');
        $this->travelTo(Carbon::create(2025, 3, 15, 10));
        $this->camille();

        $response = $this->get('/artistes/camille-durand')->assertOk();

        $response->assertViewHas('preview', false);
        $response->assertSee('<h1', false);
        $response->assertSee('Camille Durand');
        $response->assertSee('Peintre &amp; artiste générative', false);
        $response->assertSee('Peindre la lumière qui reste.');
        $response->assertSee('<strong>peinture</strong>', false);
        $response->assertSee('Nocturne bleu');
        $response->assertSee('Champ de blé');
        $response->assertDontSee('Étude masquée');
        $response->assertSee('Lumières du soir');
        $response->assertSee('1er mars – 30 avril 2025');
        $response->assertSee('Premiers pas');
        $response->assertSee('2019');
        $response->assertSee('id="oeuvres"', false);
        $response->assertSee('id="expositions"', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('"@type":"Person"', false);
        $response->assertSee('"@type":"ExhibitionEvent"', false);
        $response->assertSee('https://camille-durand.test', false);

        $artist = $response->viewData('artist');
        $this->assertSame(['available', 'sold'], $artist['availability_filters']);
        $this->assertCount(1, $artist['exhibitions']['current']);
        $this->assertCount(1, $artist['exhibitions']['upcoming']);
        $this->assertCount(1, $artist['exhibitions']['past']);
    }

    public function test_an_artist_page_in_english_falls_back_to_french(): void
    {
        $this->requireView('artists.show');
        $this->travelTo(Carbon::create(2025, 3, 15, 10));
        $this->camille();

        $response = $this->get('/artistes/camille-durand?lang=en')->assertOk();

        $response->assertSee('Painting the light that stays.');
        $response->assertSee('Painter &amp; generative artist', false);
        $response->assertSee('Blue nocturne');
        $response->assertSee('Evening lights');
        $response->assertSee('1 March – 30 April 2025');
        $response->assertSee('Champ de blé', false); // no English title: the French one
        $response->assertSee('<strong>peinture</strong>', false); // no English biography: the French one
        $response->assertDontSee('Peindre la lumière qui reste.');
    }

    public function test_unpublished_artists_are_hidden_from_guests_and_previewed_by_admins(): void
    {
        $this->requireView('artists.show');
        $this->camille(['is_published' => false]);

        $this->get('/artistes/camille-durand')->assertNotFound();

        $this->admin();

        $response = $this->get('/artistes/camille-durand')->assertOk();
        $response->assertViewHas('preview', true);
        $response->assertSee('Camille Durand');

        if (view()->exists('partials.preview-banner')) {
            $response->assertSee(e(__('artists.preview')), false);
        }
    }

    public function test_unknown_or_malformed_slugs_are_not_found(): void
    {
        $this->camille();

        $this->get('/artistes/inconnue')->assertNotFound();
        $this->get('/artistes/Camille-Durand')->assertNotFound();
        $this->get('/artistes/camille--durand')->assertNotFound();
    }

    public function test_more_artists_and_neighbours(): void
    {
        $this->requireView('artists.show');

        $this->camille();
        $this->artist(['name' => 'Ariane Roux', 'slug' => 'ariane-roux', 'position' => 2]);
        $this->artist(['name' => 'Bruno Morel', 'slug' => 'bruno-morel', 'position' => 3]);
        $this->artist(['name' => 'Chloé Marin', 'slug' => 'chloe-marin', 'position' => 4]);
        $this->artist(['name' => 'Denis Vidal', 'slug' => 'denis-vidal', 'position' => 5]);

        $response = $this->get('/artistes/bruno-morel')->assertOk();

        $this->assertSame('ariane-roux', $response->viewData('prev')['slug']);
        $this->assertSame('chloe-marin', $response->viewData('next')['slug']);
        $this->assertSame(['chloe-marin', 'denis-vidal', 'camille-durand'], array_column($response->viewData('others'), 'slug'));
        $response->assertSee('rel="prev"', false);
        $response->assertSee('rel="next"', false);
    }

    public function test_a_lone_artist_has_no_neighbours(): void
    {
        $this->requireView('artists.show');
        $this->camille();

        $response = $this->get('/artistes/camille-durand')->assertOk();

        $response->assertViewHas('prev', null);
        $response->assertViewHas('next', null);
        $response->assertViewHas('others', []);
    }

    public function test_photos_render_media_urls_with_a_srcset(): void
    {
        $this->requireView('artists.show', 'artists.index');
        $this->requireCmsMedia();

        $portrait = $this->photo(['alt_fr' => 'Portrait de Camille'], 480, 600);
        $work = $this->photo(['alt_fr' => 'Nocturne bleu'], 600, 450);
        $artist = $this->camille(['portrait_media_id' => $portrait->id]);
        $artist->artworks()->where('title_fr', 'Nocturne bleu')->first()?->update(['media_id' => $work->id]);

        $page = $this->get('/artistes/camille-durand')->assertOk();

        $page->assertSee('/media/'.$portrait->ulid.'.png', false);
        $page->assertSee('/media/'.$work->ulid.'-480.png 480w', false);
        $page->assertSee('srcset=', false);
        $page->assertSee('alt="Nocturne bleu — Camille Durand"', false);
        $this->assertTrue($page->viewData('artist')['artworks'][0]['landscape']);
        $this->assertSame($portrait->id, $page->viewData('artist')['portrait']?->id);
        $this->assertSame($work->id, $page->viewData('artist')['cover']?->id);

        $this->get('/artistes')->assertOk()->assertSee('/media/'.$portrait->ulid, false);
    }
}
