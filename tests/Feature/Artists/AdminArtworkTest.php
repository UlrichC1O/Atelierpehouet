<?php

namespace Tests\Feature\Artists;

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Artists\Concerns\BuildsArtists;
use Tests\TestCase;

/**
 * The artworks of an artist page in the admin (docs/ARTISTS.md §6): uploads (JSON for the CMS uploader,
 * plain form without JavaScript, a photo of the library), the record form, replacing the photo file,
 * order, deletion with or without the photo.
 */
class AdminArtworkTest extends TestCase
{
    use BuildsArtists, RefreshDatabase;

    /** An administrator (with the explicit admin right when that column exists), signed in. */
    protected function admin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + (Schema::hasColumn('users', 'is_admin') ? ['is_admin' => true] : []));
        $this->actingAs($user);

        return $user;
    }

    private function ariane(): Artist
    {
        return $this->artist(['name' => 'Ariane Roux', 'slug' => 'ariane-roux']);
    }

    public function test_upload_an_artwork_as_the_cms_uploader_does(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $artist = $this->ariane();
        $this->artwork($artist, ['position' => 3]);

        $response = $this->post(route('admin.artists.artworks.store', $artist), [
            'photo' => $this->pngFile('upload.png', 64, 48),
            'original_name' => 'mon_tableau-02.jpg',
            'redirect' => route('admin.artists.artworks.index', $artist),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $artwork = Artwork::query()->where('artist_id', $artist->id)->latest('id')->firstOrFail();
        $response->assertJsonPath('artwork.id', $artwork->id);
        $response->assertJsonPath('artwork.title', 'Mon tableau 02');
        $response->assertJsonPath('artwork.edit_url', route('admin.artists.artworks.edit', [$artist, $artwork]));
        $response->assertJsonPath('media.id', $artwork->media_id);
        $response->assertJsonPath('media.width', 64);
        $response->assertJsonPath('message', __('admin_artists.flash.artwork_created', ['title' => 'Mon tableau 02', 'name' => 'Ariane Roux']));
        $this->assertStringContainsString('/media/', (string) $response->json('media.url'));

        $this->assertSame('Mon tableau 02', $artwork->title_fr);
        $this->assertSame('none', $artwork->availability);
        $this->assertTrue($artwork->is_published);
        $this->assertSame(4, $artwork->position);
        $this->assertSame('Mon tableau 02', Media::query()->find($artwork->media_id)?->alt_fr);
        $this->assertSame('mon_tableau-02.jpg', Media::query()->find($artwork->media_id)?->original_name);

        // The list shows it, with its photo from the library.
        $this->get(route('admin.artists.artworks.index', $artist))
            ->assertOk()
            ->assertSee('Mon tableau 02')
            ->assertSee('/media/', false);
    }

    public function test_upload_without_javascript_leads_to_the_new_record(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $artist = $this->ariane();

        $response = $this->post(route('admin.artists.artworks.store', $artist), ['photo' => $this->pngFile('Nocturne bleu.png')]);

        $artwork = Artwork::query()->where('artist_id', $artist->id)->firstOrFail();
        $response->assertRedirect(route('admin.artists.artworks.edit', [$artist, $artwork]));
        $response->assertSessionHas('status', __('admin_artists.flash.artwork_created', ['title' => 'Nocturne bleu', 'name' => 'Ariane Roux']));
        $this->assertNotNull($artwork->media_id);
    }

    public function test_a_photo_of_the_library_becomes_an_artwork(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $artist = $this->ariane();
        $photo = $this->photo(['original_name' => 'champ_de_ble.webp']);

        $this->post(route('admin.artists.artworks.store', $artist), ['media_id' => $photo->id, 'title_fr' => '', 'source' => 'library'])
            ->assertRedirect();
        $this->post(route('admin.artists.artworks.store', $artist), ['media_id' => $photo->id, 'title_fr' => 'Champ de blé, juillet'])
            ->assertRedirect();

        $this->assertSame(['Champ de ble', 'Champ de blé, juillet'], Artwork::query()->where('artist_id', $artist->id)->orderBy('position')->pluck('title_fr')->all());
        $this->assertSame(1, DB::table('media')->count(), 'no second copy of the photo');
    }

    public function test_refused_uploads_answer_422(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $artist = $this->ariane();

        // A script asked (the CMS uploader): JSON {message, errors}.
        $this->post(route('admin.artists.artworks.store', $artist), ['photo' => $this->fileWith('pas une image', 'notes.jpg', 'image/jpeg')], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['photo']]);
        $this->postJson(route('admin.artists.artworks.store', $artist), [])
            ->assertStatus(422)
            ->assertJsonPath('errors.photo.0', __('admin_artists.errors.photo_required'));
        $this->postJson(route('admin.artists.artworks.store', $artist), ['media_id' => 987654])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['media_id']]);

        // A plain form: the list again with the error (HTTP 422, never a redirect).
        $response = $this->post(route('admin.artists.artworks.store', $artist), ['photo' => $this->fileWith('<svg xmlns="http://www.w3.org/2000/svg"/>', 'dessin.svg', 'image/svg+xml')]);
        $response->assertStatus(422);
        $response->assertViewIs('admin.artists.artworks.index');
        $response->assertSee('id="field-photo"', false);

        $this->assertSame(0, Artwork::query()->count());
        $this->assertSame(0, DB::table('media')->count());

        // The "library photo" form comes back open, with its own error.
        $this->photo();
        $response = $this->post(route('admin.artists.artworks.store', $artist), ['media_id' => '', 'source' => 'library']);
        $response->assertStatus(422);
        $this->assertMatchesRegularExpression('/<details class="adm-artist-library"\s+open/', (string) $response->getContent());
        $response->assertSee(__('admin_artists.errors.photo_required'), false);
        $this->assertSame(0, Artwork::query()->count());
    }

    public function test_update_an_artwork(): void
    {
        $this->admin();
        $artist = $this->ariane();
        $artwork = $this->artwork($artist, ['title_fr' => 'Sans titre']);

        $response = $this->put(route('admin.artists.artworks.update', [$artist, $artwork]), [
            'title_fr' => 'Nocturne bleu',
            'title_en' => 'Blue nocturne',
            'year' => '2025',
            'medium_fr' => 'Huile sur toile',
            'medium_en' => '',
            'dimensions' => '100 × 80 cm',
            'availability' => 'sold',
            'description_fr' => "Une nuit de mistral.\r\nPeinte de mémoire.",
            'description_en' => '',
            'media_id' => '',
            'is_published' => '0',
        ]);

        $response->assertRedirect(route('admin.artists.artworks.edit', [$artist, $artwork]));
        $response->assertSessionHas('status', __('admin_artists.flash.artwork_updated', ['title' => 'Nocturne bleu', 'name' => 'Ariane Roux']));

        $artwork->refresh();
        $this->assertSame('Blue nocturne', $artwork->title_en);
        $this->assertSame('sold', $artwork->availability);
        $this->assertNull($artwork->medium_en);
        $this->assertSame("Une nuit de mistral.\nPeinte de mémoire.", $artwork->description_fr);
        $this->assertFalse($artwork->is_published);
        $this->assertNull($artwork->media_id);
    }

    public function test_an_invalid_record_comes_back_with_422_and_the_typed_values(): void
    {
        $this->admin();
        $artist = $this->ariane();
        $artwork = $this->artwork($artist, ['title_fr' => 'Nocturne bleu']);

        $response = $this->put(route('admin.artists.artworks.update', [$artist, $artwork]), [
            'title_fr' => '',
            'year' => str_repeat('9', 21),
            'dimensions' => '100 × 80 cm, encadré',
            'availability' => 'stolen',
            'description_fr' => str_repeat('d', 1501),
            'media_id' => '424242',
            'is_published' => '1',
        ]);

        $response->assertStatus(422);
        $response->assertSee('100 × 80 cm, encadré', false);
        $errors = $response->viewData('errors')->getBag('default');
        foreach (['title_fr', 'year', 'availability', 'description_fr', 'media_id'] as $field) {
            $this->assertTrue($errors->has($field), $field.' should be refused');
        }
        $this->assertSame('Nocturne bleu', $artwork->fresh()->title_fr);
    }

    public function test_replace_the_photo_file_keeps_the_same_photo(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $artist = $this->ariane();
        $photo = $this->photo(['alt_fr' => 'Nocturne bleu']);
        $artwork = $this->artwork($artist, ['title_fr' => 'Nocturne bleu', 'media_id' => $photo->id]);
        $ulid = $photo->ulid;

        $response = $this->post(route('admin.artists.artworks.image', [$artist, $artwork]), ['photo' => $this->pngFile('nouveau.png', 40, 30)], ['Accept' => 'application/json']);

        $response->assertCreated();
        $response->assertJsonPath('media.id', $photo->id);
        $response->assertJsonPath('message', __('admin_artists.flash.image_replaced', ['title' => 'Nocturne bleu', 'name' => 'Ariane Roux']));
        $photo->refresh();
        $this->assertNotSame($ulid, $photo->ulid);
        $this->assertSame(40, (int) $photo->width);
        $this->assertSame('Nocturne bleu', $photo->alt_fr);
        $this->assertSame($photo->id, $artwork->fresh()->media_id);

        // Without JavaScript: back to the record; an artwork without photo gets a new one.
        $bare = $this->artwork($artist, ['title_fr' => 'Étude']);
        $this->post(route('admin.artists.artworks.image', [$artist, $bare]), ['photo' => $this->pngFile('etude.png')])
            ->assertRedirect(route('admin.artists.artworks.edit', [$artist, $bare]));
        $this->assertNotNull($bare->fresh()->media_id);

        $this->post(route('admin.artists.artworks.image', [$artist, $bare]), [])
            ->assertStatus(422)
            ->assertSee(__('admin_artists.errors.photo_required'), false);
    }

    public function test_reorder_the_artworks(): void
    {
        $this->admin();
        $artist = $this->ariane();
        $a = $this->artwork($artist, ['position' => 1]);
        $b = $this->artwork($artist, ['position' => 2]);
        $c = $this->artwork($artist, ['position' => 3]);
        $foreign = $this->artwork($this->artist(), ['position' => 1]);

        $this->postJson(route('admin.artists.artworks.reorder', $artist), ['order' => [$b->id, $c->id, $a->id]])
            ->assertOk()
            ->assertJson(['message' => trans_choice('admin_artists.flash.artworks_reordered', 3, ['count' => 3, 'name' => 'Ariane Roux'])]);
        $this->assertSame([$b->id, $c->id, $a->id], $artist->artworks()->pluck('id')->all());

        $this->from(route('admin.artists.artworks.index', $artist))
            ->post(route('admin.artists.artworks.reorder', $artist), ['order' => [$b->id, $c->id, $a->id], 'move' => $a->id.':up'])
            ->assertRedirect(route('admin.artists.artworks.index', $artist));
        $this->assertSame([$b->id, $a->id, $c->id], $artist->artworks()->pluck('id')->all());

        // Another artist's work is not part of this list.
        $this->postJson(route('admin.artists.artworks.reorder', $artist), ['order' => [$foreign->id, $a->id]])->assertStatus(422);
        $this->assertSame([$b->id, $a->id, $c->id], $artist->artworks()->pluck('id')->all());
    }

    public function test_works_of_another_artist_are_not_found_here(): void
    {
        $this->admin();
        $artist = $this->ariane();
        $foreign = $this->artwork($this->artist());

        $this->get(route('admin.artists.artworks.edit', [$artist, $foreign]))->assertNotFound();
        $this->put(route('admin.artists.artworks.update', [$artist, $foreign]), ['title_fr' => 'Volé'])->assertNotFound();
        $this->delete(route('admin.artists.artworks.destroy', [$artist, $foreign]))->assertNotFound();
        $this->assertNotNull($foreign->fresh());
    }

    public function test_delete_an_artwork_with_or_without_its_photo(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $artist = $this->ariane();
        $kept = $this->artwork($artist, ['title_fr' => 'Gardée', 'media_id' => $this->photo()->id]);
        $alone = $this->artwork($artist, ['title_fr' => 'Seule', 'media_id' => $this->photo()->id]);
        $gallery = $this->artwork($artist, ['title_fr' => 'Galerie', 'media_id' => $this->photo(['in_gallery' => true])->id]);

        $this->delete(route('admin.artists.artworks.destroy', [$artist, $kept]))
            ->assertRedirect(route('admin.artists.artworks.index', $artist))
            ->assertSessionHas('status', __('admin_artists.flash.artwork_deleted', ['title' => 'Gardée', 'name' => 'Ariane Roux']));
        $this->assertDatabaseHas('media', ['id' => $kept->media_id]);

        $this->delete(route('admin.artists.artworks.destroy', [$artist, $alone]), ['delete_photo' => '1'])->assertRedirect();
        $this->assertDatabaseMissing('media', ['id' => $alone->media_id]);

        // A photo shown elsewhere on the site (the gallery) is never deleted with the work.
        $this->delete(route('admin.artists.artworks.destroy', [$artist, $gallery]), ['delete_photo' => '1'])->assertRedirect();
        $this->assertDatabaseHas('media', ['id' => $gallery->media_id]);

        $this->assertSame(0, Artwork::query()->count());
    }

    public function test_the_record_screen_shows_the_photo_at_its_natural_ratio(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $artist = $this->ariane();
        $photo = $this->photo([], 600, 900);
        $artwork = $this->artwork($artist, ['title_fr' => 'Portrait vertical', 'media_id' => $photo->id]);

        $response = $this->get(route('admin.artists.artworks.edit', [$artist, $artwork]))->assertOk();

        $response->assertSee('width="600" height="900"', false);
        $response->assertSee(route('admin.artists.artworks.image', [$artist, $artwork]), false);
        $response->assertSee('data-media-select', false);
        $response->assertSee('value="'.$photo->id.'"', false);
        $response->assertSee('data-src="', false);
        $response->assertSee(__('admin_artists.availability.none'));
    }
}
