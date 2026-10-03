<?php

namespace Tests\Feature\Cms;

use App\Cms\OverridingTranslationLoader;
use App\Models\TranslationOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Translation\FileLoader;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/** Texts edited in the CMS replace the lang/ file lines on the public pages (docs/CMS.md §4.3). */
class TranslationOverlayTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    private function override(string $locale, string $group, string $key, string $value): void
    {
        TranslationOverride::query()->create(compact('locale', 'group', 'key', 'value'));
    }

    public function test_the_translator_loads_lines_through_the_overlay(): void
    {
        $loader = app('translator')->getLoader();

        $this->assertInstanceOf(OverridingTranslationLoader::class, $loader);
        $this->assertInstanceOf(FileLoader::class, $loader->files());
        $this->assertSame(app('translation.loader'), $loader);
    }

    public function test_overrides_appear_on_the_public_pages_in_french_and_in_english(): void
    {
        $this->override('fr', 'ui', 'nav.about', 'Qui sommes-nous 7F3A');
        $this->override('en', 'ui', 'nav.about', 'Who we are 7F3A');
        $this->override('fr', 'ui', 'tagline', 'L’art pour tous 7F3A');

        $this->get('/services?lang=fr')->assertOk()->assertSee('Qui sommes-nous 7F3A')->assertSee('L’art pour tous 7F3A');
        $this->get('/galerie?lang=en')->assertOk()->assertSee('Who we are 7F3A')->assertDontSee('Qui sommes-nous 7F3A');
    }

    public function test_only_existing_string_leaves_of_editable_groups_are_replaced(): void
    {
        $defaults = app('translator')->getLoader()->files()->load('fr', 'ui');
        $this->override('fr', 'ui', 'nav', 'Remplace tout le menu');          // an array, not a leaf
        $this->override('fr', 'ui', 'nav.inexistant', 'Clé périmée');        // unknown key
        $this->override('fr', 'ui', 'nav.about.deeper', 'Plus profond');     // below a string
        $this->override('fr', 'validation', 'required', 'Non éditable');     // group not editable
        $this->override('fr', 'home', 'hero.words.0', 'la gravure 7F3A');    // list item: a string leaf

        $this->assertSame($defaults['nav'], __('ui.nav'));
        $this->assertSame('ui.nav.inexistant', __('ui.nav.inexistant'));
        $this->assertSame($defaults['nav']['about'], __('ui.nav.about'));
        $this->assertSame(app('translator')->getLoader()->files()->load('fr', 'validation')['required'], __('validation.required'));
        $this->assertSame('la gravure 7F3A', __('home.hero.words')[0]);
        $this->assertCount(count(app('translator')->getLoader()->files()->load('fr', 'home')['hero']['words']), __('home.hero.words'));

        $this->get('/?lang=fr')->assertOk()->assertDontSee('Remplace tout le menu')->assertDontSee('Clé périmée');
    }

    public function test_the_admin_reads_the_defaults_through_the_wrapped_loader(): void
    {
        $this->override('fr', 'ui', 'nav.about', 'Qui sommes-nous 7F3A');

        $this->assertSame('Qui sommes-nous 7F3A', __('ui.nav.about'));
        $this->assertNotSame('Qui sommes-nous 7F3A', app('translator')->getLoader()->files()->load('fr', 'ui')['nav']['about']);
    }

    public function test_english_overrides_do_not_leak_into_french_and_the_fallback_still_works(): void
    {
        $this->override('en', 'ui', 'nav.about', 'Who we are 7F3A');

        $this->assertSame('Who we are 7F3A', __('ui.nav.about', [], 'en'));
        $this->assertNotSame('Who we are 7F3A', __('ui.nav.about', [], 'fr'));
    }

    public function test_namespaced_and_json_lines_are_untouched(): void
    {
        $loader = app('translator')->getLoader();
        $this->override('fr', 'ui', 'nav.about', 'Qui sommes-nous 7F3A');

        $this->assertSame($loader->files()->load('fr', '*', '*'), $loader->load('fr', '*', '*'));
        $this->assertSame($loader->files()->namespaces(), $loader->namespaces());
    }
}
