<?php

namespace Tests\Feature\Cms;

use App\Models\CmsService;
use App\Support\ServiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/** The services edited and created in the CMS, over the content files (docs/CMS.md §4.4, §13 F26). */
class ServiceOverlayTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    private const SLUG = 'sculpture';

    private function catalog(): ServiceCatalog
    {
        return app(ServiceCatalog::class);
    }

    private function files(): int
    {
        return count(glob(resource_path('content/services/*.php')));
    }

    /** @param array<string, mixed> $attributes */
    private function custom(string $slug = 'vitrail-participatif', array $attributes = []): CmsService
    {
        return CmsService::query()->create($attributes + [
            'slug' => $slug,
            'is_custom' => true,
            'content' => ['fr' => [
                'title' => 'Vitrail participatif 7F3A',
                'short' => 'Un vitrail composé avec les habitants.',
                'tagline' => 'La lumière partagée',
                'intro' => 'Nous dessinons et assemblons un vitrail avec vous.',
                'features' => [['title' => 'Atelier ouvert', 'text' => 'Chacun pose sa pièce.']],
            ]],
        ]);
    }

    public function test_content_overrides_are_merged_leaf_by_leaf_with_the_file(): void
    {
        $file = $this->catalog()->fileData(self::SLUG);
        CmsService::query()->create(['slug' => self::SLUG, 'content' => [
            'fr' => [
                'title' => 'Sculpture & taille directe 7F3A',
                'tagline' => '   ',                                    // blank: keeps the file
                'features' => [1 => ['title' => 'Atout réécrit 7F3A']],
                'ideal_for' => [3 => 'Les jardins partagés 7F3A'],
                'body' => 'not a list',                               // wrong shape: ignored
                'slug' => 'detournement',                              // not a content key: ignored
            ],
            'en' => ['tagline' => 'Carved by the CMS 7F3A'],
        ]]);

        $fr = $this->catalog()->find(self::SLUG, 'fr');
        $en = $this->catalog()->find(self::SLUG, 'en');

        $this->assertSame('Sculpture & taille directe 7F3A', $fr['title']);
        $this->assertSame($file['fr']['tagline'], $fr['tagline']);
        $this->assertSame(['title' => 'Atout réécrit 7F3A', 'text' => $file['fr']['features'][1]['text']], $fr['features'][1]);
        $this->assertSame($file['fr']['features'][0], $fr['features'][0]);
        $this->assertCount(count($file['fr']['features']), $fr['features']);
        $this->assertSame('Les jardins partagés 7F3A', $fr['ideal_for'][3]);
        $this->assertSame($file['fr']['body'], $fr['body']);
        $this->assertSame(self::SLUG, $fr['slug']);
        $this->assertTrue($fr['modified']);
        $this->assertFalse($fr['custom']);
        $this->assertTrue($fr['published']);

        $this->assertSame($file['en']['title'], $en['title'], 'a French override does not replace the English text');
        $this->assertSame('Carved by the CMS 7F3A', $en['tagline']);

        $this->get('/services/'.self::SLUG.'?lang=fr')->assertOk()->assertSee('Sculpture &amp; taille directe 7F3A', false)->assertSee('Atout réécrit 7F3A');
        $this->get('/services/'.self::SLUG.'?lang=en')->assertOk()->assertSee('Carved by the CMS 7F3A');
        $this->assertSame($file, $this->catalog()->fileData(self::SLUG), 'fileData() is the raw file');
    }

    public function test_meta_overrides_apply_only_when_valid(): void
    {
        $file = $this->catalog()->fileData(self::SLUG);
        CmsService::query()->create(['slug' => self::SLUG, 'category' => 'image', 'accent' => 'purple', 'icon' => 'Pas une icône', 'art_style' => 'vitrail']);

        $service = $this->catalog()->find(self::SLUG);

        $this->assertSame('image', $service['category']);
        $this->assertSame(__('ui.categories.image'), $service['category_label']);
        $this->assertSame($file['accent'], $service['accent']);
        $this->assertSame($file['icon'], $service['icon']);
        $this->assertSame('vitrail', $service['art_style']);
        $this->assertTrue($service['modified']);
    }

    public function test_a_row_that_only_hides_or_repeats_the_file_is_not_a_modification(): void
    {
        $file = $this->catalog()->fileData(self::SLUG);
        CmsService::query()->create(['slug' => self::SLUG, 'position' => $file['order'], 'accent' => $file['accent'], 'content' => ['fr' => ['title' => $file['fr']['title']]]]);

        $this->assertFalse($this->catalog()->find(self::SLUG)['modified']);
    }

    public function test_positions_reorder_the_services_and_numbers_follow_the_display_order(): void
    {
        $slugs = $this->catalog()->slugs();
        $last = end($slugs);
        CmsService::query()->create(['slug' => $last, 'position' => 0]);

        $all = $this->catalog()->all();

        $this->assertSame($last, $all[0]['slug']);
        $this->assertSame('01', $all[0]['number']);
        $this->assertSame(0, $all[0]['order']);
        $this->assertSame($slugs[0], $all[1]['slug']);
        $this->assertSame('02', $all[1]['number']);
        $this->assertSame(str_pad((string) $this->files(), 2, '0', STR_PAD_LEFT), $all->last()['number']);
    }

    public function test_hidden_services_disappear_from_the_public_site(): void
    {
        $url = route('services.show', self::SLUG);
        CmsService::query()->create(['slug' => self::SLUG, 'is_published' => false]);

        $this->assertNull($this->catalog()->find(self::SLUG));
        $this->assertFalse($this->catalog()->has(self::SLUG));
        $this->assertNotContains(self::SLUG, $this->catalog()->slugs());
        $this->assertSame($this->files() - 1, $this->catalog()->count());
        $this->assertSame(range(1, $this->files() - 1), $this->catalog()->all()->pluck('number')->map(fn ($n) => (int) $n)->all());

        $this->get($url)->assertNotFound();
        $this->get('/?lang=fr')->assertOk()->assertDontSee($url.'"', false);
        $this->get('/services')->assertOk()->assertDontSee($url.'"', false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee($url.'<', false);
        $this->get('/contact')->assertOk()->assertDontSee('value="'.self::SLUG.'"', false);
        $this->get('/contact?service='.self::SLUG)->assertViewHas('selected', null);

        $hidden = $this->catalog()->withHidden()->find(self::SLUG);
        $this->assertFalse($hidden['published']);
        $this->assertSame($this->files(), $this->catalog()->withHidden()->count());
    }

    public function test_services_created_in_the_cms_are_listed_with_the_generic_scene(): void
    {
        $this->custom();
        $catalog = $this->catalog();
        $categories = array_keys(config('atelier.categories'));

        $service = $catalog->find('vitrail-participatif', 'fr');

        $this->assertSame($this->files() + 1, $catalog->count());
        $this->assertSame('vitrail-participatif', $catalog->slugs()[$this->files()], 'without a position it comes last');
        $this->assertSame(str_pad((string) ($this->files() + 1), 2, '0', STR_PAD_LEFT), $service['number']);
        $this->assertSame($categories[0], $service['category']);
        $this->assertSame(config('atelier.categories.'.$categories[0].'.accent'), $service['accent']);
        $this->assertSame('triangle', $service['icon']);
        $this->assertSame('pehouet', $service['art_style']);
        $this->assertSame('vitrail-participatif', $service['scene']);
        $this->assertTrue($service['custom']);
        $this->assertTrue($catalog->isCustom('vitrail-participatif'));
        $this->assertFalse($catalog->isCustom(self::SLUG));
        $this->assertNull($catalog->path('vitrail-participatif'));
        $this->assertNull($catalog->fileData('vitrail-participatif'));
        $this->assertSame([], $service['process']);
        $this->assertSame([['title' => 'Atelier ouvert', 'text' => 'Chacun pose sa pièce.']], $service['features']);
        $this->assertSame('Vitrail participatif 7F3A', $catalog->find('vitrail-participatif', 'en')['title'], 'English falls back to French');

        $this->get('/services/vitrail-participatif?lang=fr')
            ->assertOk()
            ->assertSee('Vitrail participatif 7F3A')
            ->assertSeeHtml('scene--')
            ->assertDontSee('css/scenes/vitrail-participatif.css', false);
        $this->assertMatchesRegularExpression('/scene--(fallback|generic)/', $this->get('/services/vitrail-participatif')->getContent());
        $this->get('/services?lang=fr')->assertOk()->assertSee('Vitrail participatif 7F3A');
        $this->get('/contact')->assertOk()->assertSee('value="vitrail-participatif"', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('services.show', 'vitrail-participatif'), false);
        $this->get('/mouvement')->assertOk()->assertViewHas('scenes', fn ($scenes) => ! $scenes->contains('slug', 'vitrail-participatif') && $scenes->count() === $this->files());
    }

    public function test_cms_services_keep_their_own_position_and_meta(): void
    {
        $this->custom('fresque-mobile', ['position' => 0, 'category' => 'espaces', 'accent' => 'blue', 'icon' => 'van', 'art_style' => 'eclats']);

        $first = $this->catalog()->all()->first();

        $this->assertSame(['fresque-mobile', 'espaces', 'blue', 'van', 'eclats', '01'], [$first['slug'], $first['category'], $first['accent'], $first['icon'], $first['art_style'], $first['number']]);
    }

    public function test_unusable_cms_services_are_skipped_with_a_log_line(): void
    {
        Log::spy();
        CmsService::query()->create(['slug' => 'sans-titre', 'is_custom' => true, 'content' => ['fr' => ['short' => 'Pas de titre']]]);
        CmsService::query()->create(['slug' => 'Mauvais_Slug', 'is_custom' => true, 'content' => ['fr' => ['title' => 'Titre']]]);
        CmsService::query()->create(['slug' => 'orphelin', 'is_custom' => false, 'content' => ['fr' => ['title' => 'Plus de fichier']]]);

        $this->assertSame($this->files(), $this->catalog()->count());
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, '"sans-titre" skipped'));
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, '"Mauvais_Slug" skipped'));
    }

    public function test_a_custom_flag_on_a_file_slug_only_overrides_the_file(): void
    {
        CmsService::query()->create(['slug' => self::SLUG, 'is_custom' => true, 'content' => ['fr' => ['title' => 'Sculpture 7F3A']]]);

        $service = $this->catalog()->find(self::SLUG);

        $this->assertFalse($service['custom']);
        $this->assertSame('Sculpture 7F3A', $service['title']);
        $this->assertSame($this->files(), $this->catalog()->count());
    }

    public function test_list_items_masked_with_null_are_hidden_in_that_language(): void
    {
        $file = $this->catalog()->fileData(self::SLUG);

        CmsService::query()->create(['slug' => self::SLUG, 'content' => [
            'fr' => ['features' => [1 => null, 2 => ['title' => 'Atelier réécrit 7F3A']], 'faq' => [0 => null], 'ideal_for' => [3 => null]],
            'en' => ['body' => [0 => null]],
        ]]);

        $fr = $this->catalog()->find(self::SLUG, 'fr');
        $this->assertCount(count($file['fr']['features']) - 1, $fr['features']);
        $this->assertSame($file['fr']['features'][0], $fr['features'][0]);
        $this->assertSame('Atelier réécrit 7F3A', $fr['features'][1]['title'], 'later indexes keep addressing the file items');
        $this->assertSame($file['fr']['features'][2]['text'], $fr['features'][1]['text']);
        $this->assertSame(array_slice($file['fr']['faq'], 1), $fr['faq']);
        $this->assertSame(array_slice($file['fr']['ideal_for'], 0, 3), $fr['ideal_for']);
        $this->assertSame($file['fr']['body'], $fr['body'], 'the French body is untouched');

        $en = $this->catalog()->find(self::SLUG, 'en');
        $this->assertSame(array_slice($file['en']['body'], 1), $en['body']);
        $this->assertTrue($en['modified']);
    }

    public function test_items_without_their_required_text_are_dropped(): void
    {
        $this->custom('fresque-quartier', ['content' => ['fr' => [
            'title' => 'Fresque de quartier',
            'short' => 'Une fresque peinte avec le quartier.',
            'tagline' => 'Le mur de tous',
            'intro' => 'Nous peignons avec vous.',
            'body' => ['Premier paragraphe.', '', '   ', null, 'Dernier paragraphe.'],
            'features' => [['title' => 'Esquisse', 'text' => 'Ensemble.'], ['title' => '', 'text' => 'Sans titre'], ['text' => 'Toujours sans titre'], null],
            'process' => [['title' => 'Rencontre', 'text' => ''], ['title' => '  ', 'text' => 'Vide']],
            'ideal_for' => ['Écoles', '', 'Mairies'],
            'faq' => [['q' => 'Combien ?', 'a' => 'Sur devis.'], ['q' => 'Sans réponse ?', 'a' => ''], ['q' => '', 'a' => 'Sans question.']],
        ]]]);

        $service = $this->catalog()->find('fresque-quartier', 'fr');

        $this->assertSame(['Premier paragraphe.', 'Dernier paragraphe.'], $service['body']);
        $this->assertSame([['title' => 'Esquisse', 'text' => 'Ensemble.']], $service['features']);
        $this->assertSame([['title' => 'Rencontre', 'text' => '']], $service['process'], 'a step keeps its title even without text');
        $this->assertSame(['Écoles', 'Mairies'], $service['ideal_for']);
        $this->assertSame([['q' => 'Combien ?', 'a' => 'Sur devis.']], $service['faq']);
    }

    public function test_the_catalog_follows_cms_writes_within_one_container(): void
    {
        $this->assertNotNull($this->catalog()->find(self::SLUG));

        $row = CmsService::query()->create(['slug' => self::SLUG, 'is_published' => false]);
        $this->assertNull($this->catalog()->find(self::SLUG));

        $row->update(['is_published' => true]);
        $this->assertNotNull($this->catalog()->find(self::SLUG));
    }
}
