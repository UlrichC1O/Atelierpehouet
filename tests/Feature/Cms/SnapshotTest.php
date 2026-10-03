<?php

namespace Tests\Feature\Cms;

use App\Cms\Cms;
use App\Cms\DatabaseHealth;
use App\Models\CmsService;
use App\Models\CustomPage;
use App\Models\MediaSlot;
use App\Models\Setting;
use App\Models\TranslationOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/** The public read model of the CMS (docs/CMS.md §4.1, §13 A2): one cached snapshot of plain values, flushed on writes. */
class SnapshotTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    private function cms(): Cms
    {
        return app(Cms::class);
    }

    /** Where the memoized snapshot came from (database, cache, lastgood, none). */
    private function cmsSource(): string
    {
        return (fn (): string => $this->source)->call($this->cms());
    }

    public function test_the_snapshot_takes_at_most_seven_queries_then_comes_from_the_cache(): void
    {
        TranslationOverride::query()->create(['locale' => 'fr', 'group' => 'home', 'key' => 'hero.prefix', 'value' => 'L’atelier libre de']);
        CmsService::query()->create(['slug' => 'sculpture', 'position' => 3]);
        Setting::query()->create(['key' => 'contact.phone', 'value' => '+33 1 23 45 67 89']);
        $photo = $this->uploadPhoto(['in_gallery' => true]);
        MediaSlot::query()->create(['slot' => 'home.feature', 'media_id' => $photo->id]);
        CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda', 'is_published' => true]);

        $this->cms()->reset();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertTrue($this->cms()->available());
        $this->assertLessThanOrEqual(7, count(DB::getQueryLog()));

        DB::flushQueryLog();
        $this->cms()->reset(); // the next request
        $this->assertSame('L’atelier libre de', $this->cms()->translations('fr', 'home')['hero.prefix']);
        $this->assertSame([], DB::getQueryLog(), 'the next request reads the cache');
    }

    public function test_it_exposes_every_kind_of_cms_data(): void
    {
        TranslationOverride::query()->create(['locale' => 'en', 'group' => 'ui', 'key' => 'nav.about', 'value' => 'Our story 7F3A']);
        CmsService::query()->create(['slug' => 'sculpture', 'is_published' => false, 'position' => 4, 'accent' => 'red', 'content' => ['fr' => ['title' => 'Taille directe']]]);
        Setting::query()->create(['key' => 'announcement.fr', 'value' => '']);
        $first = $this->uploadPhoto(['in_gallery' => true, 'position' => 2, 'service_slug' => 'sculpture']);
        $second = $this->uploadPhoto(['in_gallery' => true, 'position' => 1]);
        $third = $this->uploadPhoto(['in_gallery' => true, 'position' => 2, 'service_slug' => 'sculpture']);
        $hidden = $this->uploadPhoto();
        MediaSlot::query()->create(['slot' => 'about.portrait', 'media_id' => $hidden->id]);
        CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda', 'position' => 2, 'is_published' => true, 'in_footer' => false]);
        CustomPage::query()->create(['slug' => 'infos-pratiques', 'title_fr' => 'Infos pratiques', 'title_en' => 'Practical information', 'body_fr' => '**Éditeur**', 'position' => 1, 'is_published' => true, 'cover_media_id' => $first->id]);
        CustomPage::query()->create(['slug' => 'brouillon', 'title_fr' => 'Brouillon', 'is_published' => false]);

        $cms = $this->cms();

        $this->assertSame(['nav.about' => 'Our story 7F3A'], $cms->translations('en', 'ui'));
        $this->assertSame([], $cms->translations('fr', 'ui'));
        $this->assertSame(
            ['custom' => false, 'published' => false, 'position' => 4, 'category' => null, 'accent' => 'red', 'icon' => null, 'art_style' => null,
                'content' => ['fr' => ['title' => 'Taille directe'], 'en' => []]],
            $cms->services()['sculpture'],
        );
        $this->assertSame('', $cms->setting('announcement.fr', 'défaut'));
        $this->assertSame('défaut', $cms->setting('announcement.en', 'défaut'));
        $this->assertSame(['announcement.fr' => ''], $cms->settings());

        $this->assertSame([$second->id, $third->id, $first->id], array_map(fn ($item) => $item->id, $cms->gallery()));
        $this->assertSame([$third->id, $first->id], array_map(fn ($item) => $item->id, $cms->forService('sculpture')));
        $this->assertSame([], $cms->forService('portraits'));
        $this->assertSame($hidden->id, $cms->slot('about.portrait')?->id);
        $this->assertNull($cms->slot('home.feature'));
        $this->assertSame($hidden->ulid, $cms->media($hidden->id)?->ulid);
        $this->assertNull($cms->media(999));

        // The seeded legal pages are unpublished: only the two published ones are listed.
        $this->assertSame(['infos-pratiques', 'agenda'], array_column($cms->pages(), 'slug'));
        $this->assertSame(['infos-pratiques'], array_column($cms->footerPages(), 'slug'));
        $this->assertArrayNotHasKey('body', $cms->pages()[0], 'bodies are not in the snapshot');
        $page = $cms->page('infos-pratiques');
        $this->assertSame(['fr' => 'Infos pratiques', 'en' => 'Practical information'], $page['title']);
        $this->assertSame(['fr' => '**Éditeur**', 'en' => null], $page['body']);
        $this->assertSame($first->id, $page['cover']);
        $this->assertTrue($page['in_footer']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $page['updated_at'], 'ISO 8601');
        $this->assertNull($cms->page('brouillon'));
        $this->assertNull($cms->page('mentions-legales'), 'seeded unpublished');
    }

    public function test_writes_through_the_models_flush_the_snapshot(): void
    {
        $this->assertSame([], $this->cms()->translations('fr', 'ui'));

        $override = TranslationOverride::query()->create(['locale' => 'fr', 'group' => 'ui', 'key' => 'nav.about', 'value' => 'Qui sommes-nous 7F3A']);
        $this->assertSame('Qui sommes-nous 7F3A', $this->cms()->translations('fr', 'ui')['nav.about']);
        $this->assertSame('Qui sommes-nous 7F3A', __('ui.nav.about'), 'translations already loaded are reloaded');

        $override->update(['value' => 'Notre atelier 7F3A']);
        $this->assertSame('Notre atelier 7F3A', __('ui.nav.about'));

        $override->delete();
        $this->assertNull(Cache::get(config('cms.cache.key')), 'the cached snapshot is forgotten');
        $this->assertSame([], $this->cms()->translations('fr', 'ui'));
    }

    public function test_query_builder_writes_need_an_explicit_flush(): void
    {
        $this->cms()->available();

        DB::table('settings')->insert(['key' => 'contact.phone', 'value' => '01 02 03 04 05']);
        $this->cms()->reset();
        $this->assertNull($this->cms()->setting('contact.phone'), 'still the cached snapshot');

        $this->cms()->flush();
        $this->assertSame('01 02 03 04 05', $this->cms()->setting('contact.phone'));
    }

    public function test_logged_in_admins_read_fresh_data_and_refresh_the_cache(): void
    {
        $this->get('/a-propos?lang=fr')->assertOk();
        DB::table('translation_overrides')->insert(['locale' => 'fr', 'group' => 'ui', 'key' => 'nav.about', 'value' => 'Version fraîche 7F3A']);

        $this->get('/a-propos?lang=fr')->assertOk()->assertDontSee('Version fraîche 7F3A');

        $this->admin();
        $this->get('/a-propos?lang=fr')->assertOk()->assertSee('Version fraîche 7F3A');

        auth()->logout();
        $this->get('/a-propos?lang=fr')->assertOk()->assertSee('Version fraîche 7F3A');
    }

    public function test_the_cache_store_and_lifetime_come_from_the_configuration(): void
    {
        config(['cache.stores.cms_test' => ['driver' => 'array'], 'cms.cache.store' => 'cms_test', 'cms.cache.ttl' => 120]);

        $this->assertTrue($this->cms()->available());
        $this->assertSame(Cms::VERSION, Cache::store('cms_test')->get(config('cms.cache.key'))['version']);
        $this->assertNull(Cache::store('array')->get(config('cms.cache.key')));

        DB::table('settings')->insert(['key' => 'contact.address', 'value' => 'Lyon']);
        $this->travel(121)->seconds();
        $this->cms()->reset();

        $this->assertSame('Lyon', $this->cms()->setting('contact.address'), 'the snapshot expired');
    }

    public function test_a_zero_lifetime_disables_the_cache(): void
    {
        config(['cms.cache.ttl' => 0]);

        $this->assertTrue($this->cms()->available());
        $this->assertNull(Cache::get(config('cms.cache.key')));
        $this->assertNull(Cache::get(config('cms.cache.key').'.lastgood'));
    }

    public function test_the_snapshot_survives_a_serializing_store_and_holds_plain_values_only(): void
    {
        $this->useSerializingCmsCache();
        TranslationOverride::query()->create(['locale' => 'fr', 'group' => 'home', 'key' => 'hero.prefix', 'value' => 'Atelier sérialisé 7F3A']);
        $photo = $this->uploadPhoto(['in_gallery' => true, 'alt_fr' => 'Fresque']);
        MediaSlot::query()->create(['slot' => 'home.feature', 'media_id' => $photo->id]);
        CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda', 'body_fr' => 'Jeudi.', 'is_published' => true]);

        $this->assertTrue($this->cms()->available());
        $this->assertSame('Jeudi.', $this->cms()->page('agenda')['body']['fr']);

        $this->cms()->reset();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertSame('Atelier sérialisé 7F3A', $this->cms()->translations('fr', 'home')['hero.prefix']);
        $this->assertSame($photo->ulid, $this->cms()->slot('home.feature')?->ulid);
        $this->assertSame('Fresque', $this->cms()->gallery()[0]->alt('fr'));
        $this->assertSame('Jeudi.', $this->cms()->page('agenda')['body']['fr']);
        $this->assertSame([], DB::getQueryLog(), 'snapshot and body come back from the serializing store');
        $this->assertSame('cache', $this->cmsSource());

        $snapshot = Cache::store('cms_serializing')->get(config('cms.cache.key'));
        array_walk_recursive($snapshot, fn (mixed $value) => $this->assertTrue($value === null || is_scalar($value)));
        $this->assertSame(Cms::VERSION, $snapshot['version']);
    }

    public function test_anything_but_plain_values_of_this_version_read_back_is_a_miss(): void
    {
        $this->useSerializingCmsCache();
        Setting::query()->create(['key' => 'contact.phone', 'value' => '01 02 03 04 05']);
        $store = Cache::store('cms_serializing');
        $key = config('cms.cache.key');

        $this->assertTrue($this->cms()->available());
        $valid = $store->get($key);

        foreach ([
            'an object inside' => ['settings' => ['contact.phone' => new \ArrayObject(['x'])]] + $valid,
            'another version' => ['version' => Cms::VERSION + 1] + $valid,
            'no version' => array_diff_key($valid, ['version' => true]),
            'a missing part' => array_diff_key($valid, ['media' => true]),
            'a string' => 'cms',
        ] as $case => $value) {
            $store->put($key, $value, 600);
            $this->cms()->reset();
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->assertSame('01 02 03 04 05', $this->cms()->setting('contact.phone'), $case);
            $this->assertNotSame([], DB::getQueryLog(), $case.': read again from the database');
        }
    }

    public function test_admins_preview_unpublished_pages_straight_from_the_database(): void
    {
        CustomPage::query()->create(['slug' => 'brouillon', 'title_fr' => 'Brouillon', 'body_fr' => 'En cours', 'is_published' => false, 'cover_media_id' => null]);

        $page = $this->cms()->preview('brouillon');

        $this->assertSame('Brouillon', $page['title']['fr']);
        $this->assertSame(['fr' => 'En cours', 'en' => null], $page['body']);
        $this->assertFalse($page['published']);
        $this->assertTrue($this->cms()->preview('mentions-legales')['body']['fr'] !== null, 'the seeded legal page');
        $this->assertNull($this->cms()->preview('inconnue'));
        $this->assertFalse($this->cms()->blind());

        $this->breakDatabase();
        $this->assertNull($this->cms()->preview('brouillon'));
        $this->assertTrue($this->cms()->blind(), 'unknown because unreadable, not because absent');
    }

    public function test_an_admin_read_ignores_a_cached_breaker_and_closes_it(): void
    {
        app(DatabaseHealth::class)->failed(new \RuntimeException('could not connect'));
        app(DatabaseHealth::class)->reset(); // the next request: only the cached marker remains

        $this->cms()->reset();
        $this->assertFalse($this->cms()->available(), 'visitors skip the database');

        $this->cms()->bypassCache();
        $this->assertTrue($this->cms()->available(), 'an admin reads it');
        $this->assertTrue(app(DatabaseHealth::class)->available(), 'and the breaker is closed for everyone');
        $this->assertNull(Cache::get(DatabaseHealth::KEY));
    }

    public function test_editable_groups_include_the_artist_pages_once_they_exist(): void
    {
        $groups = Cms::editableGroups();

        $this->assertSame(array_keys(config('cms.editable_groups')), array_slice(array_keys($groups), 0, count(config('cms.editable_groups'))));
        $this->assertSame('home', $groups['home']);
        $this->assertNull($groups['mail']);
        $this->assertSame(is_file(lang_path('fr/artists.php')), array_key_exists('artists', $groups));
    }

    public function test_missing_tables_are_listed(): void
    {
        $this->assertSame([], $this->cms()->missingTables());

        $this->dropCmsTables();

        $this->assertSame(Cms::TABLES, $this->cms()->missingTables());
    }
}
