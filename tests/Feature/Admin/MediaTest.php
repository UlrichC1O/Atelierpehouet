<?php

namespace Tests\Feature\Admin;

use App\Artists\MediaUsage;
use App\Cms\Media\MediaManager;
use App\Http\Controllers\Admin\MediaController;
use App\Models\CmsActivity;
use App\Models\CustomPage;
use App\Models\Media;
use App\Models\MediaFile;
use App\Models\MediaSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * The photo library (docs/CMS.md §7.6, §13 B7, C10–C14, E21): screens, uploads as JSON (the
 * uploader) and as a plain form, refusals, filters and search, the picker's partial, the no-JS slot
 * chooser, the photo page (focal point, texts, placement), replace and delete.
 */
class MediaTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    /* --- Screens ------------------------------------------------------------------------------ */

    public function test_every_photo_screen_opens_for_an_admin(): void
    {
        $this->admin();
        $media = $this->uploadPhoto(['in_gallery' => true, 'alt_fr' => 'Fresque de l’école']);

        $this->get('/admin/photos')->assertOk()
            ->assertSee('<title>'.__('admin_media.library.title'), false)
            ->assertSee('data-uploader', false)
            ->assertSee('data-media-id="'.$media->id.'"', false)
            ->assertSee('css/admin/media.css', false)
            ->assertSee('js/admin/uploader.js', false);
        $this->get('/admin/photos?picker=1')->assertOk()->assertSee('data-picker-root', false);
        $this->get('/admin/photos?slot=home.feature&redirect=/admin/textes/home')->assertOk()->assertSee(__('admin_media.card.use_here'));
        $this->get('/admin/photos/'.$media->id)->assertOk()
            ->assertSee('data-focal', false)
            ->assertSee('js/admin/focal-point.js', false)
            ->assertSee('Fresque de l’école');
        $this->get('/admin/photos?lang=en')->assertOk()->assertSee(__('admin_media.library.lead', [], 'en'));
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $media = $this->uploadPhoto();

        foreach (['/admin/photos', '/admin/photos?picker=1', '/admin/photos/'.$media->id, '/admin/galerie'] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }

        $this->post('/admin/photos', ['photo' => $this->pngFile()])->assertRedirect(route('admin.login'));
        $this->put('/admin/photos/'.$media->id, ['focal_x' => 10, 'focal_y' => 10])->assertRedirect(route('admin.login'));
        $this->post('/admin/photos/'.$media->id.'/remplacer', ['photo' => $this->pngFile()])->assertRedirect(route('admin.login'));
        $this->delete('/admin/photos/'.$media->id)->assertRedirect(route('admin.login'));
        $this->assertSame(1, Media::query()->count());
    }

    public function test_an_unknown_photo_is_a_404(): void
    {
        $this->admin();

        $this->get('/admin/photos/999')->assertNotFound();
    }

    /* --- Uploads ---------------------------------------------------------------------------------- */

    /** @return array<string, array{string, string}> */
    public static function formats(): array
    {
        return ['png' => ['pngFile', 'image/png'], 'jpeg' => ['jpegFile', 'image/jpeg'], 'webp' => ['webpFile', 'image/webp']];
    }

    #[DataProvider('formats')]
    public function test_the_uploader_sends_a_photo_as_json(string $factory, string $mime): void
    {
        $user = $this->admin();
        $file = $this->{$factory}('atelier', 40, 30);

        $response = $this->postJson('/admin/photos', [
            'photo' => $file,
            'original_name' => 'IMG_2041.HEIC.jpg',
            'in_gallery' => '1',
            'alt_fr' => 'Atelier peinture',
        ])->assertCreated()
            ->assertJsonPath('media.mime', $mime)
            ->assertJsonPath('media.original_name', 'IMG_2041.HEIC.jpg')
            ->assertJsonPath('media.in_gallery', true)
            ->assertJsonPath('where', 'gallery')
            ->assertJsonPath('message', __('admin_media.uploaded.gallery'))
            ->assertJsonPath('view_url', route('gallery'));

        $media = Media::query()->sole();
        $response->assertJsonPath('media.id', $media->id)
            ->assertJsonPath('media.edit_url', route('admin.media.edit', $media))
            ->assertJsonPath('media.url', route('media.show', $media->key()));
        $this->assertTrue($media->in_gallery);
        $this->assertSame('Atelier peinture', $media->alt_fr);
        $this->assertSame($user->id, $media->uploaded_by);
        $this->assertSame('media.upload', CmsActivity::query()->latest('id')->value('action'));
        $this->assertCount(1, cms()->gallery());
    }

    public function test_the_variants_built_by_the_browser_are_kept(): void
    {
        $this->admin();

        $this->postJson('/admin/photos', [
            'photo' => $this->webpFile('atelier.webp', 1600, 1200),
            'variants' => [480 => $this->webpFile('atelier-480.webp', 480, 360), 960 => $this->webpFile('atelier-960.webp', 960, 720)],
        ])->assertCreated()->assertJsonCount(2, 'media.variants');

        $media = Media::query()->sole();
        $this->assertSame([480, 960], array_column($media->variants, 'width'));
        $this->assertSame(3, MediaFile::query()->count());
    }

    public function test_a_plain_form_upload_goes_back_with_where_the_photo_appears(): void
    {
        $this->admin();

        $this->post('/admin/photos', ['photo' => $this->pngFile('fresque.png'), 'destination' => 'gallery', 'redirect' => '/admin/photos'])
            ->assertRedirect(url('/admin/photos'))
            ->assertSessionHas('status', __('admin_media.uploaded.gallery'))
            ->assertSessionHas('media_view_url', route('gallery'));

        $this->assertSame('fresque.png', Media::query()->value('original_name'));

        // The library then offers "Voir sur le site".
        $this->withSession(['media_view_url' => route('gallery')])->get('/admin/photos')
            ->assertOk()->assertSee(__('admin_media.uploaded.view'))->assertSee('href="'.route('gallery').'"', false);
    }

    public function test_the_destination_choice_decides_where_the_photo_appears(): void
    {
        $this->admin();

        $this->postJson('/admin/photos', ['photo' => $this->pngFile(), 'destination' => 'service', 'service_slug' => 'peinture-murale'])
            ->assertCreated()
            ->assertJsonPath('where', 'service')
            ->assertJsonPath('view_url', route('services.show', 'peinture-murale'))
            ->assertJsonPath('media.service', 'peinture-murale')
            ->assertJsonPath('media.in_gallery', true);

        $this->postJson('/admin/photos', ['photo' => $this->pngFile(), 'destination' => 'library', 'in_gallery' => '1', 'service_slug' => 'peinture-murale'])
            ->assertCreated()
            ->assertJsonPath('where', 'library')
            ->assertJsonPath('view_url', null)
            ->assertJsonPath('media.service', null)
            ->assertJsonPath('media.in_gallery', false)
            ->assertJsonPath('message', __('admin_media.uploaded.library'));

        $this->postJson('/admin/photos', ['photo' => $this->pngFile(), 'destination' => 'service'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('admin_media.errors.service'));

        $this->postJson('/admin/photos', ['photo' => $this->pngFile(), 'service_slug' => 'not-a-service'])->assertStatus(422)->assertJsonValidationErrors('service_slug');
        $this->assertSame(2, Media::query()->count());
    }

    public function test_the_library_uploader_offers_the_three_destinations_and_the_upload_limit(): void
    {
        $this->admin();
        config(['cms.media.host_max_bytes' => 1000]); // the smallest limit wins (Vercel: 4 000 000)

        $this->get('/admin/photos')->assertOk()
            ->assertSee('name="destination" value="gallery"', false)
            ->assertSee('name="destination" value="service"', false)
            ->assertSee('name="destination" value="library"', false)
            ->assertSee(__('admin_media.destination.library'))
            ->assertSee('data-max-request="'.app(MediaManager::class)->maxUploadBytes().'"', false)
            ->assertSee('data-max-request="1000"', false)
            ->assertSee('data-max-file="'.MediaManager::MAX_STORED_BYTES.'"', false)
            ->assertSee('data-uploader-i18n=', false);
    }

    /** @return array<string, array{string, string, string}> */
    public static function refusedFiles(): array
    {
        return [
            'svg' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="10" height="10"/></svg>', 'logo.svg', 'type'],
            'html' => ['<!DOCTYPE html><html><body><script>alert(1)</script></body></html>', 'page.jpg', 'not_image'],
            'text' => ['Juste du texte, pas une image.', 'notes.png', 'not_image'],
            'avif' => [pack('N', 32).'ftypavif'.pack('N', 0).'avifmif1miafMA1A'.pack('N', 8).'meta'.str_repeat("\x00", 64), 'photo.avif', 'type'],
            'heic' => [pack('N', 28).'ftypheic'.pack('N', 0).'mif1heicmiaf'.str_repeat("\x00", 64), 'IMG_0001.HEIC', 'heic'],
        ];
    }

    #[DataProvider('refusedFiles')]
    public function test_files_that_are_not_raster_photos_are_refused(string $bytes, string $name, string $reason): void
    {
        $this->admin();

        $this->postJson('/admin/photos', ['photo' => $this->fileWith($bytes, $name)])
            ->assertStatus(422)
            ->assertJsonPath('reason', $reason)
            ->assertJsonPath('message', __('admin_media.errors.'.$reason))
            ->assertJsonPath('errors.photo.0', __('admin_media.errors.'.$reason));

        // Without JavaScript: back to the page with the same message, nothing stored.
        $this->post('/admin/photos', ['photo' => $this->fileWith($bytes, $name), 'redirect' => '/admin/galerie'])
            ->assertRedirect(url('/admin/galerie'))
            ->assertSessionHas('error', __('admin_media.errors.'.$reason));

        $this->assertSame(0, Media::query()->count());
        $this->assertSame(0, MediaFile::query()->count());
    }

    public function test_size_pixel_and_quota_limits_have_their_own_messages(): void
    {
        $this->admin();

        config(['cms.media.max_kb' => 1]);
        $max = MediaController::megabytes(app(MediaManager::class)->maxUploadBytes());
        $this->postJson('/admin/photos', ['photo' => $this->jpegFile('big.jpg', 1600, 1200)])
            ->assertStatus(422)->assertJsonPath('reason', 'too_big')->assertJsonPath('message', __('admin_media.errors.too_big', ['max' => $max]));

        config(['cms.media.max_kb' => 8192, 'cms.media.max_pixels' => 100]);
        $this->postJson('/admin/photos', ['photo' => $this->pngFile('wide.png', 20, 20)])
            ->assertStatus(422)->assertJsonPath('reason', 'too_many_pixels');

        config(['cms.media.max_pixels' => 40000000, 'cms.media.quota_mb' => 1, 'cms.media.driver' => 'database']);
        DB::table('media_files')->insert(['key' => str_repeat('a', 26).'.png', 'mime' => 'image/png', 'size' => 1_048_576, 'contents' => '', 'created_at' => now()]);
        $this->postJson('/admin/photos', ['photo' => $this->pngFile('one-more.png', 40, 30)])
            ->assertStatus(422)->assertJsonPath('reason', 'quota')->assertJsonPath('message', __('admin_media.errors.quota'));

        $this->assertSame(0, Media::query()->count());
    }

    public function test_a_missing_or_interrupted_upload_is_explained(): void
    {
        $this->admin();

        $this->postJson('/admin/photos', ['alt_fr' => 'Sans photo'])->assertStatus(422)->assertJsonPath('message', __('admin_media.errors.missing'));

        $partial = new UploadedFile($this->pngFile()->getRealPath(), 'photo.png', 'image/png', UPLOAD_ERR_PARTIAL, true);
        $this->postJson('/admin/photos', ['photo' => $partial])->assertStatus(422)->assertJsonPath('reason', 'interrupted')
            ->assertJsonPath('message', __('admin_media.errors.interrupted'));

        $this->assertSame(0, Media::query()->count());
    }

    public function test_a_photo_uploaded_for_a_spot_fills_it(): void
    {
        $this->admin();

        $this->post('/admin/photos', ['photo' => $this->pngFile(), 'in_gallery' => '0', 'slot' => 'home.feature', 'redirect' => '/admin/textes/home'])
            ->assertRedirect(url('/admin/textes/home'))
            ->assertSessionHas('status', __('admin_media.uploaded.slot', ['slot' => __('admin.slots.home.feature')]));

        $media = Media::query()->sole();
        $this->assertSame($media->id, MediaSlot::query()->where('slot', 'home.feature')->value('media_id'));
        $this->assertSame($media->id, cms()->slot('home.feature')?->id);

        $this->postJson('/admin/photos', ['photo' => $this->pngFile(), 'slot' => 'nowhere.at.all'])
            ->assertStatus(422)->assertJsonPath('message', __('admin_media.slots.invalid'));
        $this->assertSame(1, Media::query()->count());
    }

    public function test_redirects_after_an_upload_stay_on_this_site(): void
    {
        $this->admin();

        foreach (['https://evil.example/admin', '//evil.example', '/\\evil.example', 'javascript:alert(1)'] as $redirect) {
            $this->post('/admin/photos', ['photo' => $this->pngFile(), 'redirect' => $redirect])->assertRedirect(route('admin.media.index'));
        }
    }

    /* --- Library: filters, search, pages, picker, slot chooser --------------------------------- */

    public function test_filters_and_search(): void
    {
        $this->admin();
        $gallery = $this->uploadPhoto(['in_gallery' => true, 'alt_fr' => 'Fresque du quartier', 'original_name' => 'fresque.webp']);
        $service = $this->uploadPhoto(['service_slug' => 'peinture-murale', 'caption_en' => 'Mural painting at school', 'original_name' => 'mur.webp']);
        $spot = $this->uploadPhoto(['original_name' => 'portrait.webp']);
        MediaSlot::query()->create(['slot' => 'about.portrait', 'media_id' => $spot->id]);
        $cover = $this->uploadPhoto(['original_name' => 'couverture.webp']);
        CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda', 'cover_media_id' => $cover->id]);
        $unused = $this->uploadPhoto(['original_name' => 'IMG_2041.webp']);

        $card = fn (Media $media): string => 'data-media-id="'.$media->id.'"';

        $all = $this->get('/admin/photos')->assertOk();
        foreach ([$gallery, $service, $spot, $cover, $unused] as $media) {
            $all->assertSee($card($media), false);
        }
        $all->assertSee(__('admin_media.card.badges.nowhere'))
            ->assertSee(trans_choice('admin_media.card.badges.spots', 1, ['count' => 1]))
            ->assertSee(trans_choice('admin_media.card.badges.covers', 1, ['count' => 1]));

        $this->get('/admin/photos?filter=gallery')->assertOk()->assertSee($card($gallery), false)->assertDontSee($card($unused), false)->assertDontSee($card($service), false);
        $this->get('/admin/photos?service=peinture-murale')->assertOk()->assertSee($card($service), false)->assertDontSee($card($gallery), false);
        $this->get('/admin/photos?filter=unused')->assertOk()
            ->assertSee($card($unused), false)
            ->assertDontSee($card($gallery), false)->assertDontSee($card($service), false)
            ->assertDontSee($card($spot), false)->assertDontSee($card($cover), false)
            ->assertSee(__('admin_media.library.unused_hint'));

        // Name, alternative text and caption; case-insensitive; "_" is not a wildcard.
        $this->get('/admin/photos?q=FRESQUE')->assertOk()->assertSee($card($gallery), false)->assertDontSee($card($service), false);
        $this->get('/admin/photos?q=mural')->assertOk()->assertSee($card($service), false)->assertDontSee($card($gallery), false);
        $this->get('/admin/photos?q=IMG_2041')->assertOk()->assertSee($card($unused), false)->assertDontSee($card($cover), false);
        $this->get('/admin/photos?q=introuvable')->assertOk()->assertSee(__('admin_media.library.empty_filtered_title'));

        // An unknown service or filter shows everything.
        $this->get('/admin/photos?service=nope&filter=nope')->assertOk()->assertSee($card($unused), false)->assertSee($card($gallery), false);
    }

    public function test_photos_used_by_the_artist_pages_are_not_unused(): void
    {
        if (! class_exists(MediaUsage::class) || ! Schema::hasTable('artists')) {
            $this->markTestSkipped('The artists module is not installed.');
        }

        $this->admin();
        $portrait = $this->uploadPhoto(['original_name' => 'portrait-artiste.webp']);
        DB::table('artists')->insert(['slug' => 'awa', 'name' => 'Awa', 'portrait_media_id' => $portrait->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/admin/photos?filter=unused')->assertOk()->assertDontSee('data-media-id="'.$portrait->id.'"', false);
        $this->get('/admin/photos')->assertOk()->assertSee(__('admin_media.card.badges.artists'));
        $this->get('/admin/photos/'.$portrait->id)->assertOk()->assertSee('Awa');
    }

    public function test_the_library_shows_48_photos_per_page(): void
    {
        $this->admin();
        $manager = app(MediaManager::class);
        for ($i = 0; $i < 49; $i++) {
            $manager->store($this->pngFile('photo-'.$i.'.png', 4, 3));
        }

        $first = $this->get('/admin/photos')->assertOk();
        $this->assertSame(48, substr_count((string) $first->getContent(), 'data-media-card'));
        $first->assertSee(__('admin.common.page_of', ['page' => 1, 'total' => 2]));

        $second = $this->get('/admin/photos?page=2')->assertOk();
        $this->assertSame(1, substr_count((string) $second->getContent(), 'data-media-card'));

        $picker = $this->get('/admin/photos?picker=1')->assertOk();
        $this->assertSame(48, substr_count((string) $picker->getContent(), 'data-media-card'));
        $picker->assertSee('picker=1&amp;page=2', false);
    }

    public function test_the_picker_partial_has_no_layout_and_a_choose_button_per_photo(): void
    {
        $this->admin();
        $media = $this->uploadPhoto(['alt_fr' => 'Vitrail']);

        $response = $this->get('/admin/photos?picker=1&selected='.$media->id)->assertOk()
            ->assertDontSee('<html', false)
            ->assertDontSee('adm-sidebar', false)
            ->assertSee('data-picker-root', false)
            ->assertSee('data-picker-form', false)
            ->assertSee(__('admin_media.card.choose'))
            ->assertSee('data-media-choose', false)
            ->assertSee('is-selected', false)
            ->assertSee('name="in_gallery" value="0"', false);

        preg_match('/data-media="([^"]+)"/', (string) $response->getContent(), $match);
        $data = json_decode(html_entity_decode($match[1] ?? '', ENT_QUOTES), true);
        $this->assertSame($media->id, $data['id'] ?? null);
        $this->assertSame(route('media.show', $media->key()), $data['url'] ?? null);
        $this->assertSame('Vitrail', $data['alt']['fr'] ?? null);
    }

    public function test_without_javascript_a_spot_is_chosen_from_the_library(): void
    {
        $this->admin();
        $media = $this->uploadPhoto();

        $this->get('/admin/photos?slot=about.portrait&redirect=/admin/textes/about')->assertOk()
            ->assertSee(__('admin_media.library.slot_mode.title', ['slot' => __('admin.slots.about.portrait')]))
            ->assertSee('action="'.route('admin.slots.update').'"', false)
            ->assertSee('name="slot" value="about.portrait"', false)
            ->assertSee('name="media_id" value="'.$media->id.'"', false)
            ->assertSee('name="redirect" value="'.url('/admin/textes/about').'"', false)
            ->assertSee(__('admin_media.card.use_here'));

        // An unknown spot or a foreign redirect: the plain library.
        $this->get('/admin/photos?slot=nope&redirect=https://evil.example')->assertOk()
            ->assertDontSee(__('admin_media.card.use_here'))
            ->assertDontSee('value="https://evil.example"', false);
    }

    /* --- Photo page ------------------------------------------------------------------------------- */

    public function test_the_photo_page_says_where_the_photo_appears(): void
    {
        $this->admin();
        $media = $this->uploadPhoto(['in_gallery' => true, 'service_slug' => 'peinture-murale']);
        MediaSlot::query()->create(['slot' => 'home.feature', 'media_id' => $media->id]);
        $page = CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda', 'cover_media_id' => $media->id, 'is_published' => true]);

        $this->get('/admin/photos/'.$media->id)->assertOk()
            ->assertSee(__('admin_media.usage.gallery'))
            ->assertSee(route('gallery'), false)
            ->assertSee(route('services.show', 'peinture-murale'), false)
            ->assertSee(__('admin_media.usage.slot', ['slot' => __('admin.slots.home.feature')]))
            ->assertSee(route('home').'#spot-home-feature', false)
            ->assertSee(__('admin_media.usage.cover', ['page' => 'Agenda']))
            ->assertSee(route('admin.pages.edit', $page), false)
            ->assertSee(__('admin_media.edit.replace_note'))
            ->assertSee('action="'.route('admin.media.replace', $media).'"', false)
            ->assertSee('data-uploader-mode="replace"', false);

        $unused = $this->uploadPhoto();
        $this->get('/admin/photos/'.$unused->id)->assertOk()->assertSee(__('admin_media.card.badges.nowhere'))->assertSee(__('admin_media.edit.usage_none'));
    }

    public function test_the_photo_texts_focal_point_and_placement_are_saved(): void
    {
        $this->admin();
        $media = $this->uploadPhoto(['alt_fr' => 'Ancien texte']);

        $this->put('/admin/photos/'.$media->id, [
            'alt_fr' => "  Fresque colorée\r\nd’une école  ",
            'alt_en' => 'Colourful mural',
            'caption_fr' => 'Quartier Nord, 2026',
            'caption_en' => '',
            'service_slug' => 'peinture-murale',
            'in_gallery' => '1',
            'position' => '3',
            'focal_x' => '20',
            'focal_y' => '75',
            'original_name' => 'fresque/nord.webp',
        ])->assertRedirect(route('admin.media.edit', $media))->assertSessionHas('status', __('admin_media.flash.updated'));

        $media->refresh();
        $this->assertSame("Fresque colorée\nd’une école", $media->alt_fr);
        $this->assertSame('Colourful mural', $media->alt_en);
        $this->assertNull($media->caption_en);
        $this->assertSame('peinture-murale', $media->service_slug);
        $this->assertTrue($media->in_gallery);
        $this->assertSame([3, 20, 75], [$media->position, $media->focal_x, $media->focal_y]);
        $this->assertSame('fresque-nord.webp', $media->original_name);

        $activity = CmsActivity::query()->where('action', 'media.update')->sole();
        $this->assertSame('media:'.$media->id, $activity->subject);

        // The public read model sees it at once.
        $this->assertSame('20% 75%', cms()->media($media->id)?->objectPosition());
        $this->assertSame([$media->id], array_map(fn ($item) => $item->id, cms()->forService('peinture-murale')));

        // Nothing changed: no new version.
        $this->put('/admin/photos/'.$media->id, [
            'alt_fr' => $media->alt_fr, 'alt_en' => $media->alt_en, 'caption_fr' => $media->caption_fr, 'caption_en' => '',
            'service_slug' => 'peinture-murale', 'in_gallery' => '1', 'position' => '3', 'focal_x' => '20', 'focal_y' => '75', 'original_name' => 'fresque-nord.webp',
        ])->assertRedirect()->assertSessionHas('info', __('admin_media.flash.unchanged'));
        $this->assertSame(1, CmsActivity::query()->where('action', 'media.update')->count());
    }

    public function test_an_invalid_photo_form_is_shown_again_with_422(): void
    {
        $this->admin();
        $media = $this->uploadPhoto(['alt_fr' => 'Texte enregistré']);

        $this->put('/admin/photos/'.$media->id, [
            'alt_fr' => str_repeat('a', 301),
            'service_slug' => 'pas-un-service',
            'focal_x' => '150',
            'focal_y' => 'centre',
        ])->assertStatus(422)
            ->assertSee(str_repeat('a', 301))
            ->assertSee('texte alternatif (français)')
            ->assertSee('point d’intérêt (horizontal)')
            ->assertSee('field--invalid', false);

        $this->assertSame('Texte enregistré', $media->refresh()->alt_fr);
        $this->assertSame(50, $media->focal_x);

        $this->put('/admin/photos/'.$media->id, ['focal_x' => '', 'focal_y' => '10'])->assertStatus(422);
    }

    public function test_cookie_sessions_stay_small_after_an_invalid_submit(): void
    {
        config(['session.driver' => 'cookie']);
        $this->admin();
        $media = $this->uploadPhoto();
        $long = str_repeat('Une très longue description. ', 200);

        $responses = [
            $this->put('/admin/photos/'.$media->id, ['alt_fr' => $long, 'alt_en' => $long, 'caption_fr' => $long, 'caption_en' => $long, 'focal_x' => '500', 'focal_y' => '50']),
            $this->post('/admin/photos', ['photo' => $this->fileWith('pas une image', 'note.png'), 'alt_fr' => $long, 'redirect' => '/admin/photos']),
        ];

        $responses[0]->assertStatus(422);
        $responses[1]->assertRedirect();

        foreach ($responses as $response) {
            $cookies = $response->headers->getCookies();
            $this->assertNotEmpty($cookies);
            foreach ($cookies as $cookie) {
                $this->assertLessThan(4096, strlen((string) $cookie), 'Set-Cookie '.$cookie->getName().' is too large');
            }
        }
    }

    /* --- Replace & delete ---------------------------------------------------------------------- */

    public function test_replacing_keeps_the_id_texts_and_placements_and_changes_the_urls(): void
    {
        $this->admin();
        $media = $this->uploadPhoto(['alt_fr' => 'Fresque', 'in_gallery' => true]);
        MediaSlot::query()->create(['slot' => 'home.feature', 'media_id' => $media->id]);
        $oldUrl = $media->item()->url();

        $this->postJson('/admin/photos/'.$media->id.'/remplacer', [
            'photo' => $this->webpFile('nouvelle.webp', 960, 720),
            'variants' => [480 => $this->webpFile('nouvelle-480.webp', 480, 360)],
            'original_name' => 'nouvelle-fresque.jpg',
        ])->assertOk()
            ->assertJsonPath('media.id', $media->id)
            ->assertJsonPath('media.original_name', 'nouvelle-fresque.jpg')
            ->assertJsonPath('message', __('admin_media.flash.replaced'));

        $media->refresh();
        $this->assertSame('Fresque', $media->alt_fr);
        $this->assertTrue($media->in_gallery);
        $this->assertSame(960, $media->width);
        $this->assertNotSame($oldUrl, $media->item()->url());
        $this->assertSame($media->id, cms()->slot('home.feature')?->id);
        $this->assertSame($media->item()->url(), cms()->slot('home.feature')?->url());
        $this->assertSame(1, Media::query()->count());

        // Without JavaScript: back to the photo page.
        $this->post('/admin/photos/'.$media->id.'/remplacer', ['photo' => $this->pngFile('plain.png', 30, 20)])
            ->assertRedirect(route('admin.media.edit', $media))
            ->assertSessionHas('status', __('admin_media.flash.replaced'));
        $this->assertSame('plain.png', $media->refresh()->original_name);
    }

    public function test_a_refused_replacement_changes_nothing(): void
    {
        $this->admin();
        $media = $this->uploadPhoto();
        $ulid = $media->ulid;

        $this->postJson('/admin/photos/'.$media->id.'/remplacer', ['photo' => $this->fileWith('<svg xmlns="http://www.w3.org/2000/svg"/>', 'x.svg')])
            ->assertStatus(422)->assertJsonPath('message', __('admin_media.errors.type'));
        $this->post('/admin/photos/'.$media->id.'/remplacer', [])
            ->assertRedirect(route('admin.media.edit', $media))->assertSessionHas('error', __('admin_media.errors.missing'));

        $this->assertSame($ulid, $media->refresh()->ulid);
    }

    public function test_deleting_a_photo_empties_its_spots_and_page_covers(): void
    {
        $this->admin();
        $media = $this->uploadPhoto(['original_name' => 'a-supprimer.webp', 'in_gallery' => true]);
        MediaSlot::query()->create(['slot' => 'about.atelier', 'media_id' => $media->id]);
        $page = CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda', 'cover_media_id' => $media->id]);

        // The confirmation lists what the photo leaves.
        $this->get('/admin/photos/'.$media->id)->assertOk()
            ->assertSee('data-confirm="', false)
            ->assertSee(__('admin_media.edit.delete_confirm_list'))
            ->assertSee(__('admin_media.usage.slot', ['slot' => __('admin.slots.about.atelier')]))
            ->assertSee(__('admin_media.edit.delete_note'));

        $this->delete('/admin/photos/'.$media->id)
            ->assertRedirect(route('admin.media.index'))
            ->assertSessionHas('status', __('admin_media.flash.deleted', ['name' => 'a-supprimer.webp']));

        $this->assertSame(0, Media::query()->count());
        $this->assertSame(0, MediaSlot::query()->count());
        $this->assertNull($page->refresh()->cover_media_id);
        $this->assertNull(cms()->slot('about.atelier'));
        $this->assertSame([], cms()->gallery());
        $this->assertSame('media.delete', CmsActivity::query()->latest('id')->value('action'));

        $other = $this->uploadPhoto();
        $this->deleteJson('/admin/photos/'.$other->id)->assertOk()->assertJsonStructure(['message']);
        $this->assertSame(0, Media::query()->count());
    }
}
