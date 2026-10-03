<?php

namespace Tests\Feature;

use App\Support\ServiceCatalog;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The content contract of docs/ARCHITECTURE.md §4, §7 and §10: at least 20 complete services,
 * each with a scene, its CSS and an icon; French/English parity; 100+ documented animations.
 */
class ContentIntegrityTest extends TestCase
{
    private const LOCALE_KEYS = ['title', 'short', 'tagline', 'intro', 'body', 'features', 'process', 'ideal_for', 'faq', 'scene_alt', 'meta_description'];

    public function test_every_service_file_is_complete_in_french_and_english(): void
    {
        $files = glob(resource_path('content/services/*.php'));
        $this->assertGreaterThanOrEqual(20, count($files));
        $orders = [];

        foreach ($files as $file) {
            $slug = basename($file, '.php');
            $data = require $file;

            $this->assertSame($slug, $data['slug'], "$slug: slug");
            $this->assertSame($slug, $data['scene'], "$slug: scene");
            $this->assertArrayHasKey($data['category'], config('atelier.categories'), "$slug: category");
            $this->assertContains($data['accent'], config('atelier.accents'), "$slug: accent");
            $this->assertContains($data['art_style'], config('atelier.art_styles'), "$slug: art_style");
            $orders[] = $data['order'];

            foreach (['fr', 'en'] as $locale) {
                $content = $data[$locale];
                $this->assertSame(self::LOCALE_KEYS, array_keys($content), "$slug/$locale keys");
                $this->assertCount(2, $content['body'], "$slug/$locale body");
                $this->assertCount(6, $content['features'], "$slug/$locale features");
                $this->assertCount(4, $content['process'], "$slug/$locale process");
                $this->assertCount(4, $content['ideal_for'], "$slug/$locale ideal_for");
                $this->assertCount(4, $content['faq'], "$slug/$locale faq");
                $this->assertLessThanOrEqual(165, mb_strlen($content['meta_description']), "$slug/$locale meta");
            }

            $this->assertFileExists(resource_path("views/services/scenes/$slug.blade.php"));
            $this->assertFileExists(public_path("css/scenes/$slug.css"));
            $icon = Blade::render('<x-icon :name="$name" />', ['name' => $data['icon']]);
            $this->assertStringNotContainsString('icon--triangle', $icon.' ', "$slug: icon '{$data['icon']}' is not drawn");
        }

        $this->assertSame(count($orders), count(array_unique($orders)), 'service orders must be unique');
    }

    public function test_catalog_loads_every_service(): void
    {
        $this->assertSame(count(glob(resource_path('content/services/*.php'))), app(ServiceCatalog::class)->count());
    }

    public function test_french_and_english_translation_files_have_identical_keys(): void
    {
        foreach (glob(lang_path('fr/*.php')) as $fr) {
            $en = lang_path('en/'.basename($fr));
            $this->assertFileExists($en);
            $this->assertSame($this->keys(require $fr), $this->keys(require $en), basename($fr).' differs between fr and en');
        }
        foreach (glob(lang_path('en/*.php')) as $en) {
            $this->assertFileExists(lang_path('fr/'.basename($en)));
        }
    }

    public function test_the_site_has_more_than_one_hundred_documented_animations(): void
    {
        $catalog = json_decode(file_get_contents(resource_path('content/animations.json')), true);
        $this->assertGreaterThanOrEqual(100, $catalog['total']);

        $css = '';
        foreach (array_merge(glob(public_path('css/*.css')), glob(public_path('css/pages/*.css')), glob(public_path('css/scenes/*.css'))) as $file) {
            $css .= file_get_contents($file);
        }
        preg_match_all('/@keyframes\s+([A-Za-z0-9_-]+)/', $css, $matches);
        $defined = array_unique($matches[1]);

        $this->assertCount($catalog['total'], $defined, 'animations.json is out of date: run python3 python/tools/animations.py');
        foreach ($catalog['animations'] as $animation) {
            $this->assertContains($animation['name'], $defined);
            $this->assertStringStartsWith('ap-', $animation['name']);
        }
    }

    public function test_every_scene_has_at_least_three_animations(): void
    {
        $catalog = json_decode(file_get_contents(resource_path('content/animations.json')), true);
        $perScene = collect($catalog['animations'])->where('group', 'scene')->countBy('scene');

        foreach (app(ServiceCatalog::class)->slugs() as $slug) {
            $this->assertGreaterThanOrEqual(3, $perScene[$slug] ?? 0, "scene $slug");
        }
    }

    /** @return list<string> */
    private function keys(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : "$prefix.$key";
            if (is_array($value) && ! array_is_list($value)) {
                $keys = [...$keys, ...$this->keys($value, $path)];
            } elseif (is_array($value)) {
                $keys[] = $path.'['.count($value).']';
            } else {
                $keys[] = $path;
            }
        }
        sort($keys);

        return $keys;
    }
}
