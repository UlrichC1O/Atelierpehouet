<?php

namespace Tests\Feature\Artists;

use App\Models\Artist;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Artists\Concerns\BuildsArtists;
use Tests\TestCase;

/**
 * Adding images straight from the artist screens (docs/ARTISTS.md §6.1): the portrait (upload or a photo
 * of the library, applied at once) and an exhibition's visual. A new file replaces the current photo's
 * file only when nothing else on the site uses that photo; otherwise it becomes a new library photo.
 */
class AdminPhotoTest extends TestCase
{
    use BuildsArtists, RefreshDatabase;

    private function ariane(array $attributes = []): Artist
    {
        return $this->artist($attributes + ['name' => 'Ariane Roux', 'slug' => 'ariane-roux']);
    }

    public function test_upload_a_portrait_as_the_cms_uploader_does(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $artist = $this->ariane();

        $response = $this->post(route('admin.artists.portrait', $artist), [
            'photo' => $this->pngFile('portrait.png', 40, 50),
            'original_name' => 'ariane.jpg',
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $artist->refresh();
        $this->assertNotNull($artist->portrait_media_id);
        $response->assertJsonPath('media.id', $artist->portrait_media_id);
        $response->assertJsonPath('message', __('admin_artists.flash.portrait_updated', ['name' => 'Ariane Roux']));

        $media = Media::query()->findOrFail($artist->portrait_media_id);
        $this->assertSame('ariane.jpg', $media->original_name);
        $this->assertSame(__('artists.show.portrait_alt', ['name' => 'Ariane Roux'], 'fr'), $media->alt_fr);
    }

    public function test_upload_a_portrait_without_javascript(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $artist = $this->ariane();

        $this->post(route('admin.artists.portrait', $artist), ['photo' => $this->pngFile('portrait.png')])
            ->assertRedirect(route('admin.artists.edit', $artist))
            ->assertSessionHas('status', __('admin_artists.flash.portrait_updated', ['name' => 'Ariane Roux']));

        $this->assertNotNull($artist->refresh()->portrait_media_id);
    }

    public function test_a_new_portrait_replaces_the_file_of_a_portrait_used_nowhere_else(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $old = $this->photo();
        $oldKey = $old->ulid;
        $artist = $this->ariane(['portrait_media_id' => $old->id]);

        $this->post(route('admin.artists.portrait', $artist), ['photo' => $this->pngFile('new.png', 30, 40)], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame($old->id, $artist->refresh()->portrait_media_id, 'same photo row');
        $this->assertNotSame($oldKey, $old->refresh()->ulid, 'with a new file');
        $this->assertSame(1, Media::query()->count(), 'no orphan photo left in the library');
    }

    public function test_a_portrait_shared_with_the_gallery_is_kept_and_a_new_photo_is_made(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $shared = $this->photo(['in_gallery' => true]);
        $sharedKey = $shared->ulid;
        $artist = $this->ariane(['portrait_media_id' => $shared->id]);

        $this->post(route('admin.artists.portrait', $artist), ['photo' => $this->pngFile('new.png')], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertNotSame($shared->id, $artist->refresh()->portrait_media_id);
        $this->assertSame($sharedKey, $shared->refresh()->ulid, 'the gallery photo is untouched');
        $this->assertSame(2, Media::query()->count());
    }

    public function test_choose_or_remove_the_portrait_from_the_library(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $photo = $this->photo();
        $artist = $this->ariane();

        $this->post(route('admin.artists.portrait', $artist), ['media_id' => (string) $photo->id])
            ->assertRedirect(route('admin.artists.edit', $artist))
            ->assertSessionHas('status', __('admin_artists.flash.portrait_updated', ['name' => 'Ariane Roux']));
        $this->assertSame($photo->id, $artist->refresh()->portrait_media_id);

        $this->post(route('admin.artists.portrait', $artist), ['media_id' => ''])
            ->assertSessionHas('status', __('admin_artists.flash.portrait_removed', ['name' => 'Ariane Roux']));
        $this->assertNull($artist->refresh()->portrait_media_id);
        $this->assertNotNull(Media::query()->find($photo->id), 'removing the portrait keeps the photo in the library');

        $this->post(route('admin.artists.portrait', $artist), ['media_id' => '999999'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('media_id');
    }

    public function test_a_refused_portrait_file_answers_422(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $artist = $this->ariane();

        $this->post(route('admin.artists.portrait', $artist), [
            'photo' => $this->fileWith('<svg xmlns="http://www.w3.org/2000/svg"/>', 'logo.svg', 'image/svg+xml'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('photo');

        $this->post(route('admin.artists.portrait', $artist), ['photo' => $this->fileWith('not an image', 'notes.png', 'image/png')])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertNull($artist->refresh()->portrait_media_id);
        $this->assertSame(0, Media::query()->count());
    }

    public function test_upload_an_exhibition_visual(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $artist = $this->ariane();
        $exhibition = $this->exhibition($artist, ['title_fr' => 'Lignes claires']);

        $response = $this->post(route('admin.artists.exhibitions.image', [$artist, $exhibition]), [
            'photo' => $this->pngFile('vue.png', 64, 40),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $exhibition->refresh();
        $response->assertJsonPath('media.id', $exhibition->media_id);
        $response->assertJsonPath('message', __('admin_artists.flash.exhibition_image', ['title' => 'Lignes claires', 'name' => 'Ariane Roux']));
        $this->assertSame('Lignes claires', Media::query()->findOrFail($exhibition->media_id)->alt_fr);

        // Without JavaScript, and without a file.
        $this->post(route('admin.artists.exhibitions.image', [$artist, $exhibition]), ['photo' => $this->pngFile('vue-2.png')])
            ->assertRedirect(route('admin.artists.exhibitions.edit', [$artist, $exhibition]))->assertSessionHas('status');
        $this->post(route('admin.artists.exhibitions.image', [$artist, $exhibition]), [], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('photo');
    }

    public function test_the_upload_cards_are_on_the_screens(): void
    {
        $this->admin();
        $this->requireView('admin.layouts.app');
        $artist = $this->ariane();
        $exhibition = $this->exhibition($artist);

        $this->get(route('admin.artists.edit', $artist))->assertOk()
            ->assertSee('action="'.route('admin.artists.portrait', $artist).'"', false)
            ->assertSee(__('admin_artists.fields.portrait.upload'));

        $this->get(route('admin.artists.exhibitions.edit', [$artist, $exhibition]))->assertOk()
            ->assertSee('action="'.route('admin.artists.exhibitions.image', [$artist, $exhibition]).'"', false);

        $this->get(route('admin.artists.exhibitions.create', $artist))->assertOk()
            ->assertSee(__('admin_artists.exhibitions.fields.visual.upload_after'));
    }

    public function test_guests_cannot_send_photos(): void
    {
        $artist = $this->ariane();

        $this->post(route('admin.artists.portrait', $artist), ['media_id' => ''])->assertRedirect(route('admin.login'));
        $this->assertNull($artist->refresh()->portrait_media_id);
    }
}
