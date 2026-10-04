<?php

namespace Tests\Feature\Admin;

use App\Models\CmsActivity;
use App\Models\Media;
use App\Models\MediaSlot;
use App\Support\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * Photo spots of the pages (docs/CMS.md §7.6, §13 E22/F31): assign, change, remove, refuse unknown
 * spots and photos, and never redirect to another site.
 */
class SlotTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    public function test_a_photo_fills_a_spot_and_the_page_shows_it(): void
    {
        $this->admin();
        $media = $this->uploadPhoto();

        $this->post('/admin/emplacements', ['slot' => 'home.feature', 'media_id' => $media->id, 'redirect' => '/admin/textes/home'])
            ->assertRedirect(url('/admin/textes/home'))
            ->assertSessionHas('status', __('admin_media.slots.assigned', ['slot' => __('admin.slots.home.feature')]));

        $this->assertSame($media->id, MediaSlot::query()->where('slot', 'home.feature')->value('media_id'));
        $this->assertSame($media->id, cms()->slot('home.feature')?->id);
        $this->assertSame('slots.update', CmsActivity::query()->latest('id')->value('action'));
    }

    public function test_a_spot_changes_photo_and_is_emptied(): void
    {
        $this->admin();
        $first = $this->uploadPhoto();
        $second = $this->uploadPhoto();
        $slot = 'service.peinture-murale.cover';

        $this->postJson('/admin/emplacements', ['slot' => $slot, 'media_id' => $first->id])->assertOk()
            ->assertJsonPath('slot', $slot)
            ->assertJsonPath('media.id', $first->id)
            ->assertJsonPath('view_url', route('services.show', 'peinture-murale').'#spot-service-peinture-murale-cover');
        $this->postJson('/admin/emplacements', ['slot' => $slot, 'media_id' => $second->id])->assertOk();

        $this->assertSame(1, MediaSlot::query()->count());
        $this->assertSame($second->id, cms()->slot($slot)?->id);
        $this->assertSame($second->id, ap_service_cover('peinture-murale')?->id);

        $this->post('/admin/emplacements', ['slot' => $slot, 'media_id' => '', 'redirect' => '/admin/services/peinture-murale'])
            ->assertRedirect(url('/admin/services/peinture-murale'))
            ->assertSessionHas('status', __('admin_media.slots.removed', ['slot' => __('admin.slots.service_cover', ['service' => app(ServiceCatalog::class)->find('peinture-murale')['title']])]));

        $this->assertSame(0, MediaSlot::query()->count());
        $this->assertNull(cms()->slot($slot));
        $this->assertSame(2, Media::query()->count()); // the photos stay in the library

        $this->postJson('/admin/emplacements', ['slot' => $slot, 'media_id' => ''])->assertOk()
            ->assertJsonPath('message', __('admin_media.slots.unchanged'))
            ->assertJsonPath('media', null);
    }

    public function test_unknown_spots_and_photos_are_refused(): void
    {
        $this->admin();
        $media = $this->uploadPhoto();

        $this->from('/admin/textes/home')->post('/admin/emplacements', ['slot' => 'home.nowhere', 'media_id' => $media->id])
            ->assertRedirect(url('/admin/textes/home'))
            ->assertSessionHas('error', __('admin_media.slots.invalid'));
        $this->postJson('/admin/emplacements', ['slot' => 'service.not-a-service.cover', 'media_id' => $media->id])
            ->assertStatus(422)->assertJsonValidationErrors('slot');
        $this->postJson('/admin/emplacements', ['slot' => 'home.feature', 'media_id' => 999])
            ->assertStatus(422)->assertJsonPath('message', __('admin_media.slots.missing'));
        $this->postJson('/admin/emplacements', ['slot' => 'home.feature', 'media_id' => 'abc'])->assertStatus(422);
        $this->postJson('/admin/emplacements', ['media_id' => $media->id])->assertStatus(422);

        $this->assertSame(0, MediaSlot::query()->count());
    }

    public function test_a_foreign_redirect_is_ignored(): void
    {
        $this->admin();
        $media = $this->uploadPhoto();

        foreach (['https://evil.example/admin', '//evil.example/x', '/\\evil.example', "\t//evil.example", 'javascript://host/%0aalert(1)'] as $redirect) {
            $this->post('/admin/emplacements', ['slot' => 'about.portrait', 'media_id' => $media->id, 'redirect' => $redirect])
                ->assertRedirect(route('admin.media.index'));
        }

        // Without a redirect: the admin page the form was on.
        $this->from('/admin/textes/about')->post('/admin/emplacements', ['slot' => 'about.portrait', 'media_id' => $media->id])
            ->assertRedirect(url('/admin/textes/about'));
        $this->from('https://evil.example/')->post('/admin/emplacements', ['slot' => 'about.portrait', 'media_id' => $media->id])
            ->assertRedirect(route('admin.media.index'));
    }

    public function test_guests_cannot_change_a_spot(): void
    {
        $media = $this->uploadPhoto();

        $this->post('/admin/emplacements', ['slot' => 'home.feature', 'media_id' => $media->id])->assertRedirect(route('admin.login'));
        $this->assertSame(0, MediaSlot::query()->count());
    }

    public function test_the_spot_card_links_to_the_picker_the_library_and_the_page(): void
    {
        $this->admin();
        $media = $this->uploadPhoto(['original_name' => 'portrait.webp']);
        MediaSlot::query()->create(['slot' => 'about.portrait', 'media_id' => $media->id]);

        $html = view('admin.media.partials.slot', [
            'slot' => 'about.portrait',
            'label' => __('admin.slots.about.portrait'),
            'media' => cms()->slot('about.portrait'),
            'ratio' => '4/5',
            'redirect' => url('/admin/textes/about'),
        ])->render();

        $this->assertStringContainsString('data-slot-form', $html);
        $this->assertStringContainsString('data-media-picker data-slot="about.portrait"', $html);
        $this->assertStringContainsString('data-picker-i18n=', $html);
        $this->assertStringContainsString(e(route('admin.media.index', ['slot' => 'about.portrait', 'redirect' => url('/admin/textes/about')])), $html);
        $this->assertStringContainsString('href="'.route('about').'#spot-about-portrait"', $html);
        $this->assertStringContainsString('href="'.route('admin.media.edit', $media->id).'"', $html);
        $this->assertStringContainsString(__('admin.slot.remove'), $html);

        // The share image is shown on no page: no "Voir sur la page".
        MediaSlot::query()->create(['slot' => 'site.share', 'media_id' => $media->id]);
        $share = view('admin.media.partials.slot', ['slot' => 'site.share', 'label' => 'Partage', 'media' => cms()->slot('site.share'), 'ratio' => '1200/630'])->render();
        $this->assertStringNotContainsString(__('admin.slot.view'), $share);
    }
}
