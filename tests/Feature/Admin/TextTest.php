<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\TextController;
use App\Models\CmsActivity;
use App\Models\TranslationOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * « Textes des pages » (docs/CMS.md §7.3, §13 E21/F27): the overrides of the lang/ texts, French and
 * English, saved, shown to visitors at once, deleted when the text goes back to the original, and
 * guarded (known texts only, length, placeholders) with a 422 re-render that keeps what was typed.
 */
class TextTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    public function test_the_index_lists_every_editable_page_with_its_changes_and_photo_spots(): void
    {
        $this->admin();
        TranslationOverride::query()->create(['locale' => 'fr', 'group' => 'home', 'key' => 'hero.cta_services', 'value' => 'Nos savoir-faire']);
        TranslationOverride::query()->create(['locale' => 'en', 'group' => 'home', 'key' => 'hero.cta_services', 'value' => 'Our crafts']);
        TranslationOverride::query()->create(['locale' => 'fr', 'group' => 'home', 'key' => 'does.not.exist', 'value' => 'Ignoré']);

        $response = $this->get('/admin/textes')->assertOk()
            ->assertSee('<title>'.__('admin_content.texts.title'), false)
            ->assertSee(__('admin_content.groups.home.label'))
            ->assertSee(__('admin_content.groups.contact.label'))
            ->assertSee(route('admin.texts.edit', 'home'), false)
            ->assertSee(route('admin.texts.edit', 'mail'), false)
            ->assertSee(trans_choice('admin_content.texts.modified', 1, ['count' => 1]))
            ->assertSee('href="'.route('about').'"', false)
            // The photo spots of the pages (shared slot partial).
            ->assertSee('data-slot-key="home.feature"', false)
            ->assertSee('data-slot-key="about.portrait"', false)
            ->assertSee('data-slot-key="site.share"', false);

        foreach (array_keys(app(TextController::class)->groups()) as $group) {
            $response->assertSee('id="texts-'.$group.'"', false);
        }
    }

    public function test_groups_whose_file_or_page_is_missing_are_skipped(): void
    {
        $this->admin();
        config(['cms.editable_groups' => ['home' => 'home', 'nowhere' => null, 'about' => 'route.that.does.not.exist']]);

        $this->get('/admin/textes')->assertOk()
            ->assertSee('id="texts-home"', false)
            ->assertDontSee('id="texts-nowhere"', false)
            ->assertDontSee('id="texts-about"', false);

        $this->get('/admin/textes/nowhere')->assertNotFound();
        $this->get('/admin/textes/about')->assertNotFound();
        $this->get('/admin/textes/inconnu')->assertNotFound();
    }

    public function test_the_editor_shows_french_and_english_side_by_side_with_labels_and_sections(): void
    {
        $this->admin();
        TranslationOverride::query()->create(['locale' => 'en', 'group' => 'home', 'key' => 'cta.title', 'value' => 'Let us create']);

        $this->get('/admin/textes/home')->assertOk()
            ->assertSee('name="t[fr][hero.heading]"', false)
            ->assertSee('name="t[en][hero.heading]"', false)
            ->assertSee('id="field-t-fr-hero-heading"', false)
            ->assertSee(__('admin_content.labels.home.hero.heading'))
            ->assertSee(__('admin_content.sections.home.manifesto'))
            ->assertSee(__('admin_content.texts.meta_label'))
            ->assertSee(__('admin_content.texts.empty_note'))
            // Overridden: current value, original text, reset checkbox.
            ->assertSee('value="Let us create"', false)
            ->assertSee($this->original('home.cta.title', 'en'))
            ->assertSee('name="reset[en][cta.title]"', false)
            ->assertDontSee('name="reset[fr][cta.title]"', false)
            // Placeholders to keep are announced.
            ->assertSee('data-placeholders="[&quot;:count&quot;]"', false)
            ->assertSee('action="'.route('admin.texts.update', 'home').'"', false);

        // Technical leaves (icon names) are not texts.

        $this->get('/admin/textes/community')->assertOk()
            ->assertSee('name="t[fr][programmes.0.title]"', false)
            ->assertDontSee('name="t[fr][programmes.0.icon]"', false);
    }

    public function test_a_saved_text_is_shown_to_visitors_at_once_in_both_languages(): void
    {
        // A cache store that serializes like production's file store; a visitor fills it first.
        $this->useSerializingCmsCache();
        $this->get('/')->assertOk()->assertSee($this->original('home.hero.cta_services'));

        $admin = $this->admin();
        $this->put('/admin/textes/home', ['t' => [
            'fr' => ['hero.cta_services' => 'Explorer nos savoir-faire 7Q2'],
            'en' => ['hero.cta_services' => "Explore our crafts\r\n8R4"],
        ]])->assertRedirect(route('admin.texts.edit', 'home'))
            ->assertSessionHas('status', trans_choice('admin_content.texts.saved', 2, ['count' => 2]));

        $this->assertDatabaseHas('translation_overrides', ['locale' => 'fr', 'group' => 'home', 'key' => 'hero.cta_services', 'value' => 'Explorer nos savoir-faire 7Q2', 'updated_by' => $admin->id]);
        $this->assertSame("Explore our crafts\n8R4", TranslationOverride::query()->where('locale', 'en')->value('value'), 'line endings are \n');

        $activity = CmsActivity::query()->where('action', 'texts.update')->sole();
        $this->assertSame('texts:home', $activity->subject);
        $this->assertSame(['group' => 'home', 'texts' => ['fr' => ['hero.cta_services' => null], 'en' => ['hero.cta_services' => null]]], $activity->before);
        $this->assertSame('Explorer nos savoir-faire 7Q2', $activity->after['texts']['fr']['hero.cta_services']);

        Auth::logout();
        $this->get('/')->assertOk()->assertSee('Explorer nos savoir-faire 7Q2')->assertDontSee($this->original('home.hero.cta_services'));
        $this->get('/?lang=en')->assertOk()->assertSee('8R4');
    }

    public function test_an_emptied_unchanged_or_reset_text_goes_back_to_the_original(): void
    {
        $this->admin();
        foreach (['hero.cta_services', 'hero.cta_create', 'cta.title'] as $key) {
            TranslationOverride::query()->create(['locale' => 'fr', 'group' => 'home', 'key' => $key, 'value' => 'Changé '.$key]);
        }

        $this->put('/admin/textes/home', [
            't' => ['fr' => [
                'hero.cta_services' => '',
                'hero.cta_create' => '  '.$this->original('home.hero.cta_create').'  ',
                'cta.title' => 'Ignoré car rétabli',
            ]],
            'reset' => ['fr' => ['cta.title' => '1']],
        ])->assertRedirect(route('admin.texts.edit', 'home'));

        $this->assertSame(0, TranslationOverride::query()->count());

        Auth::logout();
        $this->get('/')->assertOk()->assertSee($this->original('home.cta.title'))->assertDontSee('Changé');
    }

    public function test_nothing_changed_says_so(): void
    {
        $this->admin();

        $this->put('/admin/textes/home', ['t' => ['fr' => ['hero.cta_services' => $this->original('home.hero.cta_services')]]])
            ->assertRedirect(route('admin.texts.edit', 'home'))
            ->assertSessionHas('status', __('admin_content.texts.nothing'));

        $this->assertSame(0, TranslationOverride::query()->count());
        $this->assertSame(0, CmsActivity::query()->where('action', 'texts.update')->count());
    }

    public function test_unknown_keys_and_other_groups_are_ignored(): void
    {
        $this->admin();

        $this->put('/admin/textes/home', ['t' => [
            'fr' => ['nope.key' => 'Pirate', 'hero' => 'Tableau', 'manifesto.colours.blue.name' => 'Azur'],
            'de' => ['hero.cta_services' => 'Deutsch'],
        ], 'reset' => ['fr' => ['other.key' => '1']]])->assertRedirect();

        $this->assertSame(['manifesto.colours.blue.name'], TranslationOverride::query()->pluck('key')->all());
        $this->assertSame('Azur', TranslationOverride::query()->value('value'));
    }

    public function test_placeholders_are_protected_with_a_french_explanation_and_a_422(): void
    {
        $this->admin();
        TranslationOverride::query()->create(['locale' => 'fr', 'group' => 'home', 'key' => 'cta.title', 'value' => 'Créons 9Z9']);

        $response = $this->put('/admin/textes/home', ['t' => ['fr' => [
            'services.title' => 'Tous nos services',
            'services.family_count' => 'Un seul service',
            'hero.cta_services' => 'Valide mais pas enregistré',
            'cta.title' => '',
        ]]]);

        $response->assertStatus(422)
            ->assertSee(__('admin_content.texts.errors.placeholder', ['token' => ':count', 'meaning' => __('admin_content.texts.placeholders.count')]))
            ->assertSee('« :count »', false)
            ->assertSee(__('admin_content.texts.errors.plural'))
            // What was typed comes back, nothing is saved.
            ->assertSee('value="Tous nos services"', false)
            ->assertSee('value="Valide mais pas enregistré"', false)
            ->assertSee('name="t[fr][cta.title]" lang="fr" value=""', false)
            ->assertSee(__('admin_content.texts.errors.summary'))
            ->assertSee('href="#field-t-fr-services-title"', false);

        $this->assertSame(['Créons 9Z9'], TranslationOverride::query()->pluck('value')->all(), 'nothing saved, nothing deleted');

        // Keeping the placeholder (any case) is accepted.
        $this->put('/admin/textes/home', ['t' => ['fr' => ['services.title' => ':COUNT savoir-faire au service de tous']]])->assertRedirect();
        $this->assertSame(':COUNT savoir-faire au service de tous', TranslationOverride::query()->where('key', 'services.title')->value('value'));
    }

    public function test_texts_longer_than_the_limit_are_refused(): void
    {
        $this->admin();

        $this->put('/admin/textes/home', ['t' => ['fr' => ['cta.text' => str_repeat('a', TextController::MAX_LENGTH + 1)]]])
            ->assertStatus(422)
            ->assertSee(__('admin_content.texts.errors.too_long', ['max' => TextController::MAX_LENGTH]));

        $this->put('/admin/textes/home', ['t' => ['fr' => ['cta.text' => ['not' => 'a string']]]])
            ->assertStatus(422)
            ->assertSee(__('admin_content.texts.errors.invalid'));

        $this->assertSame(0, TranslationOverride::query()->count());
    }

    public function test_an_invalid_submit_keeps_the_cookie_session_small(): void
    {
        config(['session.driver' => 'cookie']);
        $this->admin();

        $long = str_repeat('Texte très long de la page. ', 150);
        $response = $this->put('/admin/textes/home', ['t' => [
            'fr' => ['services.title' => $long, 'cta.text' => $long, 'manifesto.text' => $long],
            'en' => ['services.title' => $long, 'cta.text' => $long],
        ]])->assertStatus(422);

        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies);

        foreach ($cookies as $cookie) {
            $this->assertLessThan(4096, strlen((string) $cookie), 'Set-Cookie '.$cookie->getName().' is too large');
        }
    }

    public function test_the_editor_and_the_public_page_follow_the_language_switch(): void
    {
        $this->admin();
        TranslationOverride::query()->create(['locale' => 'en', 'group' => 'contact', 'key' => 'submit', 'value' => 'Send my request now']);

        $this->get('/admin/textes/contact?lang=en')->assertOk()
            ->assertSee(__('admin_content.texts.edit_title', ['group' => __('admin_content.groups.contact.label', [], 'en')], 'en'))
            ->assertSee('value="Send my request now"', false);

        Auth::logout();
        $this->get('/contact?lang=en')->assertOk()->assertSee('Send my request now');
        $this->get('/contact?lang=fr')->assertOk()->assertSee($this->original('contact.submit'));
    }

    public function test_guests_and_non_admins_cannot_edit_texts(): void
    {
        $this->put('/admin/textes/home', ['t' => ['fr' => ['cta.title' => 'Pirate']]])->assertRedirect(route('admin.login'));

        $this->admin(['is_admin' => false]);
        $this->put('/admin/textes/home', ['t' => ['fr' => ['cta.title' => 'Pirate']]])->assertForbidden();

        $this->assertSame(0, TranslationOverride::query()->count());
    }

    /** The original text of the lang/ file (__() would return the override). */
    private function original(string $key, string $locale = 'fr'): string
    {
        [$group, $path] = explode('.', $key, 2);

        return (string) Arr::get(require lang_path($locale.'/'.$group.'.php'), $path);
    }
}
