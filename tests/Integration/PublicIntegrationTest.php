<?php

namespace Tests\Integration;

use App\Models\CustomPage;
use App\Models\Media;
use App\Models\MediaSlot;
use App\Models\Setting;
use App\Support\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * Release gate of the admin CMS (docs/CMS.md §13 F32): what the owner adds in the admin shows on the
 * public pages. It needs the integration includes of docs/CMS.md §12 (placed by the public-site
 * session) and the phase-2 partials, so it is excluded from the default run (phpunit.xml) and run
 * with `php artisan test --group=integration`. The CMS is not announced as finished until it passes.
 */
#[Group('integration')]
class PublicIntegrationTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    public function test_what_the_owner_adds_in_the_cms_appears_on_the_public_pages(): void
    {
        $slug = app(ServiceCatalog::class)->slugs()[0];

        // A photo in every fixed spot of the pages, and the cover of a service.
        $spots = [];

        foreach (array_keys((array) config('cms.slots')) as $slot) {
            $spots[$slot] = $this->photoIn($slot);
        }

        $cover = $this->photoIn('service.'.$slug.'.cover');
        $servicePhoto = $this->uploadPhoto(['service_slug' => $slug, 'alt_fr' => 'Réalisation du service']);
        $galleryPhoto = $this->uploadPhoto(['in_gallery' => true, 'alt_fr' => 'Photo de la galerie']);

        CustomPage::query()->create(['slug' => 'agenda-atelier', 'title_fr' => 'Agenda de l’atelier 7F3A', 'title_en' => 'Atelier diary 7F3A',
            'body_fr' => 'Les prochains rendez-vous.', 'is_published' => true, 'in_footer' => true]);

        foreach (['announcement.enabled' => '1', 'announcement.fr' => 'Portes ouvertes samedi 7F3A', 'announcement.en' => 'Open day on Saturday 7F3A'] as $key => $value) {
            Setting::query()->create(['key' => $key, 'value' => $value]);
        }

        $pages = [
            '/' => [$spots['home.feature'], $galleryPhoto],
            '/a-propos' => [$spots['about.portrait'], $spots['about.atelier']],
            '/communaute' => [$spots['community.feature']],
            '/galerie' => [$galleryPhoto],
            '/services/'.$slug => [$cover, $servicePhoto],
        ];

        foreach ($pages as $uri => $photos) {
            $response = $this->get($uri.'?lang=fr')->assertOk();

            foreach ($photos as $photo) {
                $response->assertSee('/media/'.$photo->ulid, false);
            }

            $response->assertSee('Portes ouvertes samedi 7F3A')
                ->assertSee('Agenda de l’atelier 7F3A')
                ->assertSee(route('pages.custom', ['slug' => 'agenda-atelier']), false);

            // Social previews: the service's cover on its page, the site-wide share photo elsewhere.
            $shared = str_starts_with($uri, '/services/') ? $cover : $spots['site.share'];
            $this->assertStringContainsString('/media/'.$shared->ulid, $this->ogImage($response), $uri.' og:image');
        }

        $this->get('/?lang=en')->assertOk()->assertSee('Open day on Saturday 7F3A')->assertSee('Atelier diary 7F3A');
        $this->get('/agenda-atelier?lang=fr')->assertOk()->assertSee('Les prochains rendez-vous.');
    }

    private function photoIn(string $slot): Media
    {
        $photo = $this->uploadPhoto(['alt_fr' => 'Photo '.$slot]);
        MediaSlot::query()->create(['slot' => $slot, 'media_id' => $photo->id]);

        return $photo;
    }

    private function ogImage(TestResponse $response): string
    {
        preg_match('/<meta\s+property="og:image"\s+content="([^"]*)"/', (string) $response->getContent(), $match);

        return html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5);
    }
}
