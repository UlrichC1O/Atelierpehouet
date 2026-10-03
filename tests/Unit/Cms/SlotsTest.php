<?php

namespace Tests\Unit\Cms;

use App\Cms\Slots;
use App\Models\CmsService;
use App\Support\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Photo spots: the fixed ones of config('cms.slots') + a cover per service (docs/CMS.md §4.6). */
class SlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_spots_and_one_cover_per_service(): void
    {
        $slots = Slots::all();

        foreach (array_keys(config('cms.slots')) as $key) {
            $this->assertArrayHasKey($key, $slots);
        }
        foreach (app(ServiceCatalog::class)->slugs() as $slug) {
            $this->assertSame(['page' => 'service.'.$slug, 'ratio' => config('cms.service_cover_ratio'), 'service' => $slug], $slots['service.'.$slug.'.cover']);
        }
        $this->assertSame('service.sculpture.cover', Slots::cover('sculpture'));
        $this->assertSame(['about.portrait', 'about.atelier'], array_keys(Slots::forPage('about')));
        $this->assertSame('1200/630', Slots::ratio('site.share'));
        $this->assertSame(config('cms.service_cover_ratio'), Slots::ratio('service.sculpture.cover'));
    }

    public function test_hidden_and_cms_created_services_have_a_cover_spot(): void
    {
        CmsService::query()->create(['slug' => 'sculpture', 'is_published' => false]);
        CmsService::query()->create(['slug' => 'fresque-cms', 'is_custom' => true, 'content' => ['fr' => ['title' => 'Fresque CMS']]]);

        $this->assertTrue(Slots::isValid('service.sculpture.cover'));
        $this->assertTrue(Slots::isValid('service.fresque-cms.cover'));
        $this->assertTrue(Slots::isValid('home.feature'));
        $this->assertFalse(Slots::isValid('service.inconnu.cover'));
        $this->assertFalse(Slots::isValid('home'));
    }

    public function test_labels_come_from_the_admin_translations(): void
    {
        $this->assertSame(__('admin.slots.home.feature'), Slots::label('home.feature'));

        $service = app(ServiceCatalog::class)->find('sculpture');
        $this->assertSame(__('admin.slots.service_cover', ['service' => $service['title']]), Slots::label('service.sculpture.cover'));
    }
}
