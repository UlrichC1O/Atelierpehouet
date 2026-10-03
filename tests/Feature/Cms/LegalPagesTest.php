<?php

namespace Tests\Feature\Cms;

use App\Cms\Markdown;
use App\Models\CustomPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/** The two legal pages the site needs, created unpublished for the owner to fill in (docs/CMS.md §13 F28). */
class LegalPagesTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_03_000022_insert_legal_pages.php');
    }

    public function test_the_legal_pages_exist_unpublished_with_french_and_english_skeletons(): void
    {
        $pages = CustomPage::query()->whereIn('slug', ['mentions-legales', 'confidentialite'])->orderBy('position')->get();

        $this->assertSame(['mentions-legales', 'confidentialite'], $pages->pluck('slug')->all());
        $this->assertSame(['Mentions légales', 'Politique de confidentialité'], $pages->pluck('title_fr')->all());
        $this->assertSame(['Legal notice', 'Privacy policy'], $pages->pluck('title_en')->all());

        foreach ($pages as $page) {
            $this->assertFalse($page->is_published);
            $this->assertTrue($page->in_footer);
            $this->assertStringStartsWith('## ', $page->body_fr);
            $this->assertStringStartsWith('## ', $page->body_en);
            $this->assertStringContainsString('À compléter', $page->body_fr);
            $this->assertStringContainsString('To be completed', $page->body_en);
            $this->assertLessThanOrEqual(170, mb_strlen($page->meta_fr));
            $this->assertLessThanOrEqual(170, mb_strlen($page->meta_en));
            $this->assertStringContainsString('<h2>', (string) Markdown::render($page->body_fr));
        }

        $this->assertNull(cms()->page('mentions-legales'), 'nothing shows on the site until published');
        $this->get('/mentions-legales')->assertNotFound();
    }

    public function test_an_existing_slug_is_never_overwritten(): void
    {
        CustomPage::query()->where('slug', 'mentions-legales')->update(['title_fr' => 'Nos mentions', 'body_fr' => 'Texte de l’atelier.']);
        CustomPage::query()->where('slug', 'confidentialite')->delete();

        $this->migration()->up();

        $this->assertSame('Texte de l’atelier.', CustomPage::query()->where('slug', 'mentions-legales')->sole()->body_fr);
        $this->assertSame(1, CustomPage::query()->where('slug', 'confidentialite')->count(), 'the missing page is created again');
        $this->assertSame(2, CustomPage::query()->count());
    }

    public function test_rolling_back_keeps_the_owners_pages(): void
    {
        $this->migration()->down();

        $this->assertSame(2, CustomPage::query()->count());
    }
}
