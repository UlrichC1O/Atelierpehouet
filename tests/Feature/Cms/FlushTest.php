<?php

namespace Tests\Feature\Cms;

use App\Cms\Activity;
use App\Cms\Cms;
use App\Models\CmsActivity;
use App\Models\CmsService;
use App\Models\CustomPage;
use App\Models\MediaFile;
use App\Models\MediaSlot;
use App\Models\Setting;
use App\Models\TranslationOverride;
use App\Support\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * Every CMS write shows on the very next public page (docs/CMS.md §13 A4): the models of the
 * snapshot flush it (after the commit too), query-builder writes call cms()->flush(), and a flush
 * also drops the in-process memos (loaded translations, the services catalog). The cache store
 * serializes like the persistent stores of production.
 */
class FlushTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSerializingCmsCache();
        config(['atelier.contact.phone' => '']);
    }

    private function cachedSnapshot(): mixed
    {
        return Cache::store('cms_serializing')->get(config('cms.cache.key'));
    }

    public function test_a_guest_sees_each_model_write_at_once(): void
    {
        $slug = app(ServiceCatalog::class)->slugs()[0];
        $this->get('/services?lang=fr')->assertOk();
        $this->assertIsArray($this->cachedSnapshot(), 'the guest page cached the snapshot');

        $override = TranslationOverride::query()->create(['locale' => 'fr', 'group' => 'ui', 'key' => 'nav.about', 'value' => 'Notre histoire 7F3A']);
        $this->get('/services?lang=fr')->assertSee('Notre histoire 7F3A');

        $override->update(['value' => 'Qui sommes-nous 7F3A']);
        $this->get('/services?lang=fr')->assertSee('Qui sommes-nous 7F3A')->assertDontSee('Notre histoire 7F3A');

        $override->delete();
        $this->get('/services?lang=fr')->assertDontSee('Qui sommes-nous 7F3A');

        Setting::query()->create(['key' => 'contact.phone', 'value' => '+33 6 99 88 77 66']);
        $this->get('/services?lang=fr')->assertSee('+33 6 99 88 77 66');

        CmsService::query()->create(['slug' => $slug, 'content' => ['fr' => ['title' => 'Titre réécrit 7F3A']]]);
        $this->get('/services?lang=fr')->assertSee('Titre réécrit 7F3A');

        $photo = $this->uploadPhoto();
        MediaSlot::query()->create(['slot' => 'home.feature', 'media_id' => $photo->id]);
        $this->get('/robots.txt');
        $this->assertSame($photo->id, cms()->slot('home.feature')?->id);

        CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda', 'body_fr' => 'Avant', 'is_published' => true]);
        $this->get('/robots.txt');
        $this->assertSame('Avant', cms()->page('agenda')['body']['fr']);

        CustomPage::query()->where('slug', 'agenda')->sole()->update(['body_fr' => 'Après']);
        $this->get('/robots.txt');
        $this->assertSame('Après', cms()->page('agenda')['body']['fr'], 'the cached body is renewed with the snapshot');
    }

    public function test_query_builder_writes_show_after_an_explicit_flush(): void
    {
        $this->get('/services?lang=fr')->assertOk();

        DB::table('translation_overrides')->upsert(
            [['locale' => 'fr', 'group' => 'ui', 'key' => 'nav.about', 'value' => 'Version groupée 7F3A']],
            ['locale', 'group', 'key'],
            ['value'],
        );
        $this->get('/services?lang=fr')->assertDontSee('Version groupée 7F3A');

        cms()->flush();
        $this->get('/services?lang=fr')->assertSee('Version groupée 7F3A');
    }

    public function test_writes_in_a_transaction_flush_again_after_the_commit(): void
    {
        $this->assertTrue(cms()->available());
        $stale = $this->cachedSnapshot();

        DB::transaction(function () use ($stale): void {
            Setting::query()->create(['key' => 'contact.phone', 'value' => '01 02 03 04 05']);
            // A concurrent request caches the rows it still sees before the commit.
            Cache::store('cms_serializing')->put(config('cms.cache.key'), $stale, 600);
        });

        $this->assertNull($this->cachedSnapshot(), 'flushed after the commit');
        $this->get('/services?lang=fr')->assertSee('01 02 03 04 05');
    }

    public function test_a_flush_drops_the_loaded_translations_and_the_services_catalog(): void
    {
        $catalog = app(ServiceCatalog::class);
        $this->assertNotSame('', __('ui.nav.about'));

        DB::table('translation_overrides')->insert(['locale' => 'fr', 'group' => 'ui', 'key' => 'nav.about', 'value' => 'Mémo 7F3A']);
        cms()->flush();

        $this->assertSame('Mémo 7F3A', __('ui.nav.about'));
        $this->assertNotSame($catalog, app(ServiceCatalog::class));
    }

    public function test_the_log_and_stored_files_do_not_flush_the_snapshot(): void
    {
        $this->assertTrue(cms()->available());

        Activity::record('test.action', 'Rien');
        CmsActivity::query()->create(['action' => 'test.direct', 'summary' => 'Rien']);
        MediaFile::query()->create(['key' => str_repeat('c', 26).'.webp', 'mime' => 'image/webp', 'size' => 1, 'contents' => base64_encode('x')]);

        $this->assertIsArray($this->cachedSnapshot());
    }

    public function test_flush_never_throws(): void
    {
        Log::spy();
        config(['cms.cache.store' => 'magasin-inexistant']);

        app(Cms::class)->flush();

        $this->assertTrue(cms()->available(), 'the snapshot still loads, uncached');
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'CMS cache'))->atLeast()->once();
    }
}
