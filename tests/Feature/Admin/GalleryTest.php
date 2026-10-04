<?php

namespace Tests\Feature\Admin;

use App\Models\CmsActivity;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * The gallery's order (docs/CMS.md §7.6): the sortable grid, positions saved as JSON (sortable.js)
 * or by the plain form (move up / down buttons), "remove from the gallery" and its uploader.
 */
class GalleryTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    /** @return list<int> ids of the public gallery, in its order */
    private function publicOrder(): array
    {
        return array_map(fn ($item): int => $item->id, cms()->gallery());
    }

    public function test_the_gallery_screen_lists_the_gallery_photos_in_their_order(): void
    {
        $this->admin();
        $first = $this->uploadPhoto(['in_gallery' => true, 'position' => 1, 'original_name' => 'premiere.webp']);
        $second = $this->uploadPhoto(['in_gallery' => true, 'position' => 2, 'original_name' => 'deuxieme.webp']);
        $outside = $this->uploadPhoto(['original_name' => 'hors-galerie.webp']);

        $response = $this->get('/admin/galerie')->assertOk()
            ->assertSee('<title>'.__('admin_media.gallery.title'), false)
            ->assertSee('data-sortable-autosubmit', false)
            ->assertSee('js/admin/sortable.js', false)
            ->assertSee('name="in_gallery" value="1"', false) // the uploader's preset
            ->assertSee('action="'.route('admin.gallery.toggle', $first->id).'"', false)
            ->assertSee(trans_choice('admin_media.gallery.count', 2, ['count' => 2]))
            ->assertSee(__('admin_media.gallery.remove_note'))
            ->assertDontSee('hors-galerie.webp');

        $content = (string) $response->getContent();
        $this->assertLessThan(strpos($content, 'deuxieme.webp'), strpos($content, 'premiere.webp'));
        $this->assertNotNull($outside->id);
    }

    public function test_an_empty_gallery_says_how_to_fill_it(): void
    {
        $this->admin();

        $this->get('/admin/galerie')->assertOk()
            ->assertSee(__('admin_media.gallery.empty_title'))
            ->assertSee('data-uploader', false)
            ->assertDontSee('data-sortable-autosubmit', false);
    }

    public function test_the_order_is_saved_as_json(): void
    {
        $this->admin();
        $a = $this->uploadPhoto(['in_gallery' => true]);
        $b = $this->uploadPhoto(['in_gallery' => true]);
        $c = $this->uploadPhoto(['in_gallery' => true]);
        $outside = $this->uploadPhoto();
        $this->assertSame([$c->id, $b->id, $a->id], $this->publicOrder()); // newest first at first

        // A photo outside the gallery and unknown ids are ignored; an unlisted gallery photo goes last.
        $this->postJson('/admin/galerie/ordre', ['order' => [$a->id, $outside->id, 999, $c->id]])
            ->assertOk()
            ->assertJsonPath('message', __('admin_media.gallery.reordered'))
            ->assertJsonPath('order', [$a->id, $c->id, $b->id]);

        $this->assertSame([1, 2, 3], [$a->refresh()->position, $c->refresh()->position, $b->refresh()->position]);
        $this->assertSame(0, $outside->refresh()->position);
        $this->assertSame([$a->id, $c->id, $b->id], $this->publicOrder());
        $this->assertSame('gallery.reorder', CmsActivity::query()->latest('id')->value('action'));

        // A new photo shows first until the next reorder.
        $d = $this->uploadPhoto(['in_gallery' => true]);
        $this->assertSame($d->id, $this->publicOrder()[0]);
    }

    public function test_without_javascript_the_move_buttons_and_the_save_button_work(): void
    {
        $this->admin();
        $a = $this->uploadPhoto(['in_gallery' => true, 'position' => 1]);
        $b = $this->uploadPhoto(['in_gallery' => true, 'position' => 2]);
        $c = $this->uploadPhoto(['in_gallery' => true, 'position' => 3]);

        $this->post('/admin/galerie/ordre', ['order' => [$a->id, $b->id, $c->id], 'move' => $c->id.':up'])
            ->assertRedirect(route('admin.gallery.index'))
            ->assertSessionHas('status', __('admin_media.gallery.reordered'));
        $this->assertSame([$a->id, $c->id, $b->id], $this->publicOrder());

        $this->post('/admin/galerie/ordre', ['order' => [$a->id, $c->id, $b->id], 'move' => $a->id.':down'])->assertRedirect();
        $this->assertSame([$c->id, $a->id, $b->id], $this->publicOrder());

        // Moving the first one up (or a bogus move) changes nothing.
        $this->post('/admin/galerie/ordre', ['order' => [$c->id, $a->id, $b->id], 'move' => $c->id.':up'])->assertRedirect();
        $this->post('/admin/galerie/ordre', ['order' => [$c->id, $a->id, $b->id], 'move' => 'drop table'])->assertRedirect();
        $this->assertSame([$c->id, $a->id, $b->id], $this->publicOrder());
    }

    public function test_a_photo_leaves_the_gallery_but_stays_in_the_library(): void
    {
        $this->admin();
        $media = $this->uploadPhoto(['in_gallery' => true, 'original_name' => 'fresque.webp']);

        $this->post('/admin/galerie/'.$media->id, ['in_gallery' => '0', 'redirect' => '/admin/galerie'])
            ->assertRedirect(url('/admin/galerie'))
            ->assertSessionHas('status', __('admin_media.gallery.removed'));

        $this->assertFalse($media->refresh()->in_gallery);
        $this->assertSame(1, Media::query()->count());
        $this->assertSame([], cms()->gallery());
        $this->assertSame('gallery.remove', CmsActivity::query()->latest('id')->value('action'));

        $this->postJson('/admin/galerie/'.$media->id, ['in_gallery' => true])
            ->assertOk()->assertJsonPath('in_gallery', true)->assertJsonPath('message', __('admin_media.gallery.added'));
        $this->assertTrue($media->refresh()->in_gallery);

        // A foreign redirect is ignored.
        $this->post('/admin/galerie/'.$media->id, ['in_gallery' => '0', 'redirect' => 'https://evil.example/'])
            ->assertRedirect(route('admin.gallery.index'));
    }

    public function test_guests_cannot_change_the_gallery(): void
    {
        $media = $this->uploadPhoto(['in_gallery' => true]);

        $this->get('/admin/galerie')->assertRedirect(route('admin.login'));
        $this->post('/admin/galerie/ordre', ['order' => [$media->id]])->assertRedirect(route('admin.login'));
        $this->post('/admin/galerie/'.$media->id, ['in_gallery' => '0'])->assertRedirect(route('admin.login'));
        $this->assertTrue($media->refresh()->in_gallery);
    }
}
