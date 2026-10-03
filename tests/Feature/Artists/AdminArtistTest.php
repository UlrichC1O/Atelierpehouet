<?php

namespace Tests\Feature\Artists;

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Artists\Concerns\BuildsArtists;
use Tests\TestCase;

/**
 * The artist pages in the admin (docs/ARTISTS.md §6): list and order, creation, profile, publication,
 * deletion (with or without the photos) and the example artist — through the real routes and views.
 */
class AdminArtistTest extends TestCase
{
    use BuildsArtists, RefreshDatabase;

    /** An administrator (with the explicit admin right when that column exists), signed in. */
    protected function admin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + (Schema::hasColumn('users', 'is_admin') ? ['is_admin' => true] : []));
        $this->actingAs($user);

        return $user;
    }

    /** The CSP allows no inline script and no on* attribute (docs/ARCHITECTURE.md). */
    private function assertCspSafe(TestResponse $response): void
    {
        $html = (string) $response->getContent();

        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $html, 'an inline <script> is present');
        $this->assertDoesNotMatchRegularExpression('/<[^>]+\son[a-z]+\s*=/i', $html, 'an on* event attribute is present');
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $artist = $this->artist();
        $artwork = $this->artwork($artist);
        $exhibition = $this->exhibition($artist);

        foreach ([
            route('admin.artists.index'),
            route('admin.artists.create'),
            route('admin.artists.edit', $artist),
            route('admin.artists.artworks.index', $artist),
            route('admin.artists.artworks.edit', [$artist, $artwork]),
            route('admin.artists.exhibitions.index', $artist),
            route('admin.artists.exhibitions.create', $artist),
            route('admin.artists.exhibitions.edit', [$artist, $exhibition]),
        ] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }

        $this->post(route('admin.artists.store'), ['name' => 'Intrus'])->assertRedirect(route('admin.login'));
        $this->put(route('admin.artists.update', $artist), ['name' => 'Intrus'])->assertRedirect(route('admin.login'));
        $this->delete(route('admin.artists.destroy', $artist))->assertRedirect(route('admin.login'));
        $this->assertSame(1, Artist::query()->count());
        $this->assertSame('Artiste 1', $artist->fresh()->name);
    }

    public function test_every_screen_renders_for_an_admin(): void
    {
        $this->admin();
        $artist = $this->artist(['name' => 'Ariane Roux', 'slug' => 'ariane-roux', 'discipline_fr' => 'Peintre', 'location' => 'Marseille']);
        $artwork = $this->artwork($artist, ['title_fr' => 'Nocturne bleu', 'year' => '2025', 'availability' => 'available']);
        $exhibition = $this->exhibition($artist, ['title_fr' => 'Lumières du Sud', 'kind' => 'solo', 'venue' => 'Galerie du Port']);

        $screens = [
            route('admin.artists.index') => ['Ariane Roux', __('admin_artists.actions.add'), 'Peintre'],
            route('admin.artists.create') => [__('admin_artists.create.title'), 'data-artist-slug-source'],
            route('admin.artists.edit', $artist) => ['ariane-roux', __('admin_artists.edit.sections.portrait'), 'data-media-field'],
            route('admin.artists.artworks.index', $artist) => ['Nocturne bleu', 'data-uploader', __('admin_artists.availability.available')],
            route('admin.artists.artworks.edit', [$artist, $artwork]) => ['Nocturne bleu', route('admin.artists.artworks.image', [$artist, $artwork])],
            route('admin.artists.exhibitions.index', $artist) => ['Lumières du Sud', __('admin_artists.kinds.solo'), 'Galerie du Port'],
            route('admin.artists.exhibitions.create', $artist) => [__('admin_artists.exhibitions.create.title'), 'data-exhibition-start'],
            route('admin.artists.exhibitions.edit', [$artist, $exhibition]) => ['Lumières du Sud', route('admin.artists.exhibitions.destroy', [$artist, $exhibition])],
        ];

        foreach ($screens as $url => $texts) {
            $response = $this->get($url)->assertOk();
            $response->assertSee('css/admin/artists.css', false);
            $response->assertSee('js/admin/artists.js', false);
            $this->assertSame(1, substr_count((string) $response->getContent(), '<h1'), $url.' has one <h1>');
            foreach ($texts as $text) {
                $response->assertSee($text, false);
            }
            $this->assertCspSafe($response);
        }

        // English admin: the same screens, English labels.
        $this->get(route('admin.artists.index', ['lang' => 'en']))->assertOk()->assertSee('Add an artist');
    }

    public function test_the_list_shows_the_artists_in_their_order_with_their_status(): void
    {
        $this->admin();
        $this->artist(['name' => 'Bruno Morel', 'slug' => 'bruno-morel', 'position' => 2, 'is_published' => true]);
        $this->artist(['name' => 'Ariane Roux', 'slug' => 'ariane-roux', 'position' => 1, 'is_published' => false]);
        $this->artist(['name' => 'Camille Durand', 'slug' => 'camille-durand', 'position' => 3, 'is_published' => false, 'is_example' => true]);

        $response = $this->get(route('admin.artists.index'))->assertOk();

        $response->assertSeeInOrder(['Ariane Roux', 'Bruno Morel', 'Camille Durand']);
        $response->assertSee(__('admin_artists.status.published'));
        $response->assertSee(__('admin_artists.status.draft'));
        $response->assertSee(__('admin_artists.status.example'));
        $response->assertSee('data-sortable-autosubmit', false);
        $response->assertSee('name="order[]"', false);
        $response->assertSee('data-move="down"', false);
        // An example exists: no second "create the example" button.
        $response->assertDontSee(route('admin.artists.example'), false);
    }

    public function test_an_empty_list_invites_to_create_the_first_artist(): void
    {
        $this->admin();

        $response = $this->get(route('admin.artists.index'))->assertOk();

        $response->assertSee(__('admin_artists.index.empty.title'));
        $response->assertSee(route('admin.artists.create'), false);
        $response->assertDontSee('data-sortable', false);
    }

    public function test_create_an_artist(): void
    {
        $this->admin();
        $this->artist(['position' => 4]);

        $response = $this->post(route('admin.artists.store'), [
            'name' => '  Ariane Roux-Lefèvre ',
            'slug' => '',
            'discipline_fr' => 'Peintre',
            'discipline_en' => '',
            'location' => 'Marseille, France',
            'accent' => 'blue',
        ]);

        $artist = Artist::query()->where('name', 'Ariane Roux-Lefèvre')->firstOrFail();
        $response->assertRedirect(route('admin.artists.edit', $artist));
        $response->assertSessionHas('status', __('admin_artists.flash.created', ['name' => 'Ariane Roux-Lefèvre']));

        $this->assertSame('ariane-roux-lefevre', $artist->slug);
        $this->assertSame('Peintre', $artist->discipline_fr);
        $this->assertNull($artist->discipline_en);
        $this->assertSame('blue', $artist->accent);
        $this->assertFalse($artist->is_published);
        $this->assertFalse($artist->is_example);
        $this->assertSame(5, $artist->position);

        if (Schema::hasTable('cms_activity')) {
            $this->assertDatabaseHas('cms_activity', ['action' => 'artists.created']);
        }
    }

    public function test_a_taken_name_gets_a_numbered_address(): void
    {
        $this->admin();
        $this->artist(['name' => 'Ariane Roux', 'slug' => 'ariane-roux']);

        $this->post(route('admin.artists.store'), ['name' => 'Ariane Roux'])->assertRedirect();

        $this->assertTrue(Artist::query()->where('slug', 'ariane-roux-2')->exists());
    }

    public function test_an_invalid_creation_form_comes_back_with_422_and_the_typed_values(): void
    {
        $this->admin();
        $this->artist(['slug' => 'pris']);

        $response = $this->post(route('admin.artists.store'), [
            'name' => '',
            'slug' => 'Pas Une Adresse !',
            'discipline_fr' => 'Graveuse obstinée',
            'accent' => 'fuchsia',
        ]);

        $response->assertStatus(422);
        $response->assertViewIs('admin.artists.create');
        $response->assertSee('Graveuse obstinée', false);
        $response->assertSee('value="Pas Une Adresse !"', false);
        $response->assertSee(__('admin_artists.validation.slug_regex'), false);
        $this->assertSame(['name', 'slug', 'accent'], array_keys($response->viewData('errors')->getBag('default')->messages()));
        $this->assertFalse(session()->has('_old_input'), 'the input must not be flashed to the next request');

        $this->post(route('admin.artists.store'), ['name' => 'Autre', 'slug' => 'pris'])
            ->assertStatus(422)
            ->assertSee(__('admin_artists.validation.slug_unique'), false);
        $this->assertSame(1, Artist::query()->count());
    }

    public function test_update_the_profile(): void
    {
        $user = $this->admin();
        $artist = $this->artist(['name' => 'Ariane', 'slug' => 'ariane', 'is_published' => false]);

        $response = $this->put(route('admin.artists.update', $artist), [
            'name' => 'Ariane Roux',
            'slug' => 'Ariane-Roux',
            'discipline_fr' => 'Peintre',
            'discipline_en' => 'Painter',
            'location' => 'Marseille',
            'accent' => 'red',
            'website' => 'https://ariane-roux.example',
            'instagram' => '',
            'statement_fr' => 'Graver la lumière.',
            'statement_en' => '',
            'bio_fr' => "Premier paragraphe.\r\n\r\nSecond **paragraphe**.",
            'bio_en' => '   ',
            'meta_fr' => 'Ariane Roux, peintre à Marseille.',
            'meta_en' => '',
            'portrait_media_id' => '',
            'is_published' => '1',
        ]);

        $response->assertRedirect(route('admin.artists.edit', $artist));
        $response->assertSessionHas('status', __('admin_artists.flash.updated', ['name' => 'Ariane Roux']));

        $artist->refresh();
        $this->assertSame('ariane-roux', $artist->slug);
        $this->assertSame('red', $artist->accent);
        $this->assertSame("Premier paragraphe.\n\nSecond **paragraphe**.", $artist->bio_fr);
        $this->assertNull($artist->bio_en);
        $this->assertNull($artist->instagram);
        $this->assertNull($artist->portrait_media_id);
        $this->assertTrue($artist->is_published);
        $this->assertSame($user->id, $artist->updated_by);

        // Unticking the switch posts only the hidden "0".
        $this->put(route('admin.artists.update', $artist), ['name' => 'Ariane Roux', 'slug' => 'ariane-roux', 'is_published' => '0'])->assertRedirect();
        $this->assertFalse($artist->fresh()->is_published);
    }

    public function test_an_invalid_profile_comes_back_with_422_and_plain_messages(): void
    {
        $this->admin();
        $this->artist(['slug' => 'deja-prise']);
        $artist = $this->artist(['name' => 'Ariane', 'slug' => 'ariane', 'is_published' => false]);

        $response = $this->put(route('admin.artists.update', $artist), [
            'name' => 'Ariane',
            'slug' => 'deja-prise',
            'website' => 'http://ariane.example',
            'instagram' => 'https://insta gram.example/ariane',
            'statement_fr' => str_repeat('a', 401),
            'bio_fr' => 'Une biographie à garder',
            'meta_fr' => str_repeat('m', 171),
            'portrait_media_id' => '987654',
            'is_published' => '1',
        ]);

        $response->assertStatus(422);
        $response->assertSee('Une biographie à garder', false);
        $response->assertSee(__('admin_artists.validation.slug_unique'), false);
        $response->assertSee(__('admin_artists.validation.https'), false);
        $errors = $response->viewData('errors')->getBag('default');
        foreach (['slug', 'website', 'instagram', 'statement_fr', 'meta_fr', 'portrait_media_id'] as $field) {
            $this->assertTrue($errors->has($field), $field.' should be refused');
        }
        $this->assertSame('ariane', $artist->fresh()->slug);
        $this->assertFalse($artist->fresh()->is_published);
    }

    public function test_texts_are_saved_as_valid_utf8_without_nul_bytes(): void
    {
        $this->admin();
        $artist = $this->artist(['name' => 'Ariane', 'slug' => 'ariane']);

        $this->put(route('admin.artists.update', $artist), [
            'name' => "Ari\0ane",
            'slug' => 'ariane',
            'statement_fr' => "Lumi\xC3\xA8re \xFF\xFE du Sud\0",
        ])->assertRedirect(route('admin.artists.edit', $artist));

        $artist->refresh();
        $this->assertSame('Ariane', $artist->name);
        $this->assertTrue(mb_check_encoding((string) $artist->statement_fr, 'UTF-8'));
        $this->assertStringStartsWith('Lumière', (string) $artist->statement_fr);
        $this->assertStringNotContainsString("\0", (string) $artist->statement_fr);
    }

    public function test_a_long_invalid_form_keeps_the_cookie_session_under_four_kilobytes(): void
    {
        config(['session.driver' => 'cookie']);
        $this->admin();
        $artist = $this->artist(['name' => 'Ariane', 'slug' => 'ariane']);

        $response = $this->put(route('admin.artists.update', $artist), [
            'name' => 'Ariane',
            'slug' => 'ariane',
            'statement_fr' => str_repeat('Une phrase d’intention bien trop longue. ', 20),
            'bio_fr' => str_repeat('Une biographie très longue, paragraphe après paragraphe. ', 220),
            'bio_en' => str_repeat('A very long biography, paragraph after paragraph. ', 220),
            'meta_fr' => str_repeat('m', 400),
            'website' => 'http://'.str_repeat('w', 300).'.example',
        ]);

        $response->assertStatus(422);
        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies);
        foreach ($cookies as $cookie) {
            $this->assertLessThan(4096, strlen((string) $cookie), 'Set-Cookie '.$cookie->getName().' is too large');
        }
    }

    public function test_publish_and_hide(): void
    {
        $this->admin();
        $artist = $this->artist(['name' => 'Ariane', 'slug' => 'ariane', 'is_published' => false]);
        $back = route('admin.artists.artworks.index', $artist, false);

        $this->post(route('admin.artists.toggle', $artist), ['redirect' => $back])
            ->assertRedirect(url($back))
            ->assertSessionHas('status', __('admin_artists.flash.published', ['name' => 'Ariane']));
        $this->assertTrue($artist->fresh()->is_published);

        // Another site is never a destination: back to the previous page instead.
        $this->from(route('admin.artists.index'))
            ->post(route('admin.artists.toggle', $artist), ['redirect' => 'https://evil.example/admin'])
            ->assertRedirect(route('admin.artists.index'))
            ->assertSessionHas('status', __('admin_artists.flash.hidden', ['name' => 'Ariane']));
        $this->assertFalse($artist->fresh()->is_published);

        foreach (['//evil.example/x', '/\\evil.example', "/\tadmin"] as $unsafe) {
            $this->from(route('admin.artists.index'))
                ->post(route('admin.artists.toggle', $artist), ['redirect' => $unsafe])
                ->assertRedirect(route('admin.artists.index'));
        }

        $this->postJson(route('admin.artists.toggle', $artist))
            ->assertOk()
            ->assertJson(['published' => false, 'message' => __('admin_artists.flash.hidden', ['name' => 'Ariane'])]);
    }

    public function test_reorder_the_artists(): void
    {
        $this->admin();
        $a = $this->artist(['name' => 'A', 'position' => 1]);
        $b = $this->artist(['name' => 'B', 'position' => 2]);
        $c = $this->artist(['name' => 'C', 'position' => 3]);

        $this->postJson(route('admin.artists.reorder'), ['order' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->assertJson(['message' => trans_choice('admin_artists.flash.reordered', 3, ['count' => 3])]);
        $this->assertSame([$c->id, $a->id, $b->id], Artist::query()->ordered()->pluck('id')->all());

        // Without JavaScript the arrows submit the form with "move" = "{id}:up|down".
        $this->from(route('admin.artists.index'))
            ->post(route('admin.artists.reorder'), ['order' => [$c->id, $a->id, $b->id], 'move' => $c->id.':down'])
            ->assertRedirect(route('admin.artists.index'))
            ->assertSessionHas('status');
        $this->assertSame([$a->id, $c->id, $b->id], Artist::query()->ordered()->pluck('id')->all());

        $this->postJson(route('admin.artists.reorder'), ['order' => [$a->id, 999999]])
            ->assertStatus(422)
            ->assertJsonPath('message', __('admin_artists.errors.order'));
        $this->from(route('admin.artists.index'))
            ->post(route('admin.artists.reorder'), ['order' => 'nope'])
            ->assertRedirect(route('admin.artists.index'))
            ->assertSessionHas('error', __('admin_artists.errors.order'));
        $this->assertSame([$a->id, $c->id, $b->id], Artist::query()->ordered()->pluck('id')->all());
    }

    public function test_delete_an_artist_and_keep_the_photos(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $portrait = $this->photo();
        $artist = $this->artist(['name' => 'Ariane', 'slug' => 'ariane', 'portrait_media_id' => $portrait->id]);
        $this->artwork($artist, ['media_id' => $this->photo()->id]);
        $this->exhibition($artist);

        $this->delete(route('admin.artists.destroy', $artist))
            ->assertRedirect(route('admin.artists.index'))
            ->assertSessionHas('status', __('admin_artists.flash.deleted', ['name' => 'Ariane']));

        $this->assertNull(Artist::query()->find($artist->id));
        $this->assertSame(0, Artwork::query()->count());
        $this->assertSame(0, Exhibition::query()->count());
        $this->assertSame(2, DB::table('media')->count());
    }

    public function test_delete_an_artist_with_the_photos_used_nowhere_else(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $portrait = $this->photo();
        $workPhoto = $this->photo();
        $galleryPhoto = $this->photo(['in_gallery' => true]);
        $sharedPhoto = $this->photo();

        $artist = $this->artist(['name' => 'Ariane', 'slug' => 'ariane', 'portrait_media_id' => $portrait->id]);
        $this->artwork($artist, ['media_id' => $workPhoto->id]);
        $this->artwork($artist, ['media_id' => $galleryPhoto->id]);
        $this->exhibition($artist, ['media_id' => $sharedPhoto->id]);
        $other = $this->artist(['name' => 'Bruno', 'slug' => 'bruno']);
        $this->artwork($other, ['media_id' => $sharedPhoto->id]);

        $this->delete(route('admin.artists.destroy', $artist), ['delete_photos' => '1'])
            ->assertRedirect(route('admin.artists.index'));

        $this->assertDatabaseMissing('media', ['id' => $portrait->id]);
        $this->assertDatabaseMissing('media', ['id' => $workPhoto->id]);
        $this->assertDatabaseHas('media', ['id' => $galleryPhoto->id]);
        $this->assertDatabaseHas('media', ['id' => $sharedPhoto->id]);
        $this->assertNotNull($other->fresh());
    }

    public function test_the_example_button_creates_one_example_page(): void
    {
        if (! is_file(resource_path('content/artists/example.php'))) {
            $this->markTestSkipped('The example artist content (resources/content/artists/example.php) does not exist yet.');
        }

        $this->admin();

        $this->get(route('admin.artists.index'))->assertOk()->assertSee(route('admin.artists.example'), false);

        $response = $this->post(route('admin.artists.example'));

        $example = Artist::query()->where('is_example', true)->firstOrFail();
        $response->assertRedirect(route('admin.artists.edit', $example));
        $this->assertFalse($example->is_published);
        $flash = (string) session('status');
        $this->assertStringContainsString($example->name, $flash);
        $this->assertStringNotContainsString('|', $flash, 'the photo count picks one plural form');

        $this->get(route('admin.artists.edit', $example))->assertOk()->assertSee(__('admin_artists.edit.example.title'));

        $this->post(route('admin.artists.example'))
            ->assertRedirect(route('admin.artists.edit', $example))
            ->assertSessionHas('status', __('admin_artists.flash.example_exists', ['name' => $example->name]));
        $this->assertSame(1, Artist::query()->where('is_example', true)->count());
        $this->get(route('admin.artists.index'))->assertOk()->assertDontSee(route('admin.artists.example'), false);
    }

    public function test_missing_artist_tables_send_the_owner_to_maintenance(): void
    {
        $this->admin();
        $this->dropArtistTables();

        $response = $this->get(route('admin.artists.index'));

        $response->assertRedirect(route('admin.maintenance'));
        $response->assertSessionHas('error', __('admin_artists.errors.migrate'));
    }
}
