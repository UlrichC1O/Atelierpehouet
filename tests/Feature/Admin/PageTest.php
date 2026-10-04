<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\PageController;
use App\Models\CmsActivity;
use App\Models\CustomPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * « Pages libres » in the admin (docs/CMS.md §7.4, §13 E21/F28): list with the legal pages first,
 * create / edit / delete, slug rules, Markdown preview, cover photo, 422 re-render, cookie sessions.
 */
class PageTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + [
            'title_fr' => 'Agenda de l’atelier',
            'title_en' => 'Workshop diary',
            'slug' => '',
            'body_fr' => "## Prochains rendez-vous\r\n\r\nAtelier fresque **samedi**.",
            'body_en' => 'Mural workshop on *Saturday*.',
            'meta_fr' => 'Les prochains ateliers.',
            'meta_en' => '',
            'cover_media_id' => '',
            'is_published' => '1',
            'in_footer' => '1',
            'position' => '3',
        ];
    }

    public function test_the_list_shows_the_legal_pages_to_complete_first(): void
    {
        $this->admin();

        $this->get('/admin/pages')->assertOk()
            ->assertSee('<title>'.__('admin_content.pages.title'), false)
            ->assertSee(__('admin_content.pages.legal.title'))
            ->assertSee(__('admin_content.pages.legal.todo'))
            ->assertSee(route('admin.pages.edit', CustomPage::query()->where('slug', 'mentions-legales')->value('id')), false)
            ->assertSee(route('admin.pages.edit', CustomPage::query()->where('slug', 'confidentialite')->value('id')), false)
            ->assertSee('/mentions-legales')
            ->assertSee(route('admin.pages.create'), false);
    }

    public function test_a_deleted_legal_page_can_be_created_again_from_the_list(): void
    {
        $this->admin();
        CustomPage::query()->where('slug', 'confidentialite')->delete();

        $this->get('/admin/pages')->assertOk()
            ->assertSee(__('admin_content.pages.legal.missing', ['slug' => 'confidentialite']))
            ->assertSee(route('admin.pages.create', ['slug' => 'confidentialite']), false);

        $this->get('/admin/pages/creer?slug=confidentialite')->assertOk()
            ->assertSee('value="confidentialite"', false)
            ->assertSee('value="Politique de confidentialité"', false);
    }

    public function test_the_create_form_offers_every_field(): void
    {
        $this->admin();
        $photo = $this->uploadPhoto(['alt_fr' => 'Fresque du quartier']);

        $this->get('/admin/pages/creer')->assertOk()
            ->assertSee('action="'.route('admin.pages.store').'"', false)
            ->assertSee(['name="title_fr"', 'name="title_en"', 'name="slug"', 'name="body_fr"', 'name="body_en"', 'name="meta_fr"',
                'name="meta_en"', 'name="cover_media_id"', 'name="is_published"', 'name="in_footer"', 'name="position"', 'data-slug-auto',
                'data-media-picker', 'data-media-target="#page-insert-media"', 'data-media-target="#page-cover-input"',
                'value="'.$photo->id.'"', 'data-key="'.$photo->ulid.'.webp"'], false)
            ->assertSee(__('admin_content.pages.markdown.summary'))
            ->assertSee(__('admin_content.pages.insert_photo'));
    }

    public function test_a_page_is_created_with_a_slug_from_its_french_title(): void
    {
        $this->useSerializingCmsCache();
        $this->get('/')->assertOk(); // a visitor fills the cache first
        $admin = $this->admin();
        $photo = $this->uploadPhoto();

        $response = $this->post('/admin/pages', $this->form(['cover_media_id' => (string) $photo->id]));

        $page = CustomPage::query()->where('slug', 'agenda-de-l-atelier')->sole();
        $response->assertRedirect(route('admin.pages.edit', $page))
            ->assertSessionHas('status', __('admin_content.pages.created', ['title' => 'Agenda de l’atelier']));

        $this->assertSame("## Prochains rendez-vous\n\nAtelier fresque **samedi**.", $page->body_fr, 'line endings are \n');
        $this->assertNull($page->meta_en);
        $this->assertSame($photo->id, $page->cover_media_id);
        $this->assertTrue($page->is_published);
        $this->assertTrue($page->in_footer);
        $this->assertSame(3, $page->position);
        $this->assertSame($admin->id, $page->updated_by);
        $this->assertSame('page:'.$page->id, CmsActivity::query()->where('action', 'pages.create')->sole()->subject);

        // Visible to visitors at once, with its footer link.
        Auth::logout();
        $this->get('/agenda-de-l-atelier')->assertOk()->assertSee('Agenda de l’atelier')->assertSee('Prochains rendez-vous');
        $this->assertStringContainsString('href="'.route('pages.custom', 'agenda-de-l-atelier').'"', view('partials.footer-pages')->render());

        // Once the public footer includes the partial (docs/CMS.md §12), every page links to it.
        if (str_contains((string) file_get_contents(resource_path('views/partials/footer.blade.php')), 'partials.footer-pages')) {
            $this->get('/')->assertOk()->assertSee('href="'.route('pages.custom', 'agenda-de-l-atelier').'"', false);
        }
    }

    public function test_a_suggested_slug_steps_aside_from_taken_and_reserved_addresses(): void
    {
        $this->admin();
        CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda']);

        $this->post('/admin/pages', $this->form(['title_fr' => 'Agenda']))->assertRedirect();
        $this->post('/admin/pages', $this->form(['title_fr' => 'Contact']))->assertRedirect();

        $this->assertTrue(CustomPage::query()->where('slug', 'agenda-2')->exists());
        $this->assertTrue(CustomPage::query()->where('slug', 'contact-2')->exists());
    }

    public function test_typed_slugs_must_be_free_well_formed_and_not_a_site_address(): void
    {
        $this->admin();
        $existing = CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda']);

        $cases = [
            'admin' => 'slug_reserved',          // reserved word
            'services' => 'slug_reserved',       // reserved word and a route
            'artistes' => 'slug_reserved',       // reserved word (artist pages)
            'up' => 'slug_reserved',             // health check route
            'galerie' => 'slug_reserved',
            'agenda' => 'slug_taken',            // another page
            'Mon Agenda!' => 'slug_format',
            'a--b' => 'slug_format',
            '-agenda' => 'slug_format',
        ];

        foreach ($cases as $slug => $error) {
            $this->post('/admin/pages', $this->form(['slug' => $slug, 'title_fr' => 'Essai '.$slug]))
                ->assertStatus(422)
                ->assertSee(__('admin_content.pages.errors.'.$error))
                ->assertSee('value="Essai '.e($slug).'"', false);
        }

        $this->assertSame(1, CustomPage::query()->where('title_fr', 'like', 'Agenda%')->count());
        $this->assertSame(0, CustomPage::query()->where('title_fr', 'like', 'Essai%')->count());

        // Its own slug is fine when editing.
        $this->put('/admin/pages/'.$existing->id, $this->form(['slug' => 'agenda', 'title_fr' => 'Agenda']))->assertRedirect(route('admin.pages.edit', $existing));
    }

    public function test_the_registered_first_url_segments_are_reserved(): void
    {
        foreach (['admin', 'services', 'a-propos', 'contact', 'langue', 'media', 'sitemap', 'up', 'robots'] as $slug) {
            $this->assertTrue(PageController::reserved($slug), $slug);
        }

        $this->assertFalse(PageController::reserved('mentions-legales'));
        $this->assertFalse(PageController::reserved('agenda'));
    }

    public function test_invalid_input_is_shown_again_with_a_422_and_french_messages(): void
    {
        $this->admin();

        $response = $this->post('/admin/pages', $this->form([
            'title_fr' => '',
            'body_fr' => str_repeat('a', PageController::MAX_BODY + 1),
            'meta_fr' => str_repeat('m', 171),
            'cover_media_id' => '999999',
            'position' => '-4',
        ]));

        $response->assertStatus(422)
            ->assertSee(__('validation.required', ['attribute' => __('admin_content.pages.attributes.title_fr')]))
            ->assertSee(__('admin_content.pages.errors.cover'))
            ->assertSee('href="#field-body_fr"', false)
            ->assertSee('Workshop diary');

        $this->assertSame(2, CustomPage::query()->count(), 'only the two legal pages');
    }

    public function test_an_invalid_submit_keeps_the_cookie_session_small(): void
    {
        config(['session.driver' => 'cookie']);
        $this->admin();

        $response = $this->post('/admin/pages', $this->form([
            'title_fr' => '',
            'body_fr' => str_repeat('Un long texte de page. ', 800),
            'body_en' => str_repeat('A long page text. ', 800),
        ]))->assertStatus(422);

        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies);

        foreach ($cookies as $cookie) {
            $this->assertLessThan(4096, strlen((string) $cookie), 'Set-Cookie '.$cookie->getName().' is too large');
        }
    }

    public function test_the_preview_renders_the_submitted_markdown_without_saving(): void
    {
        $this->admin();
        $page = CustomPage::query()->where('slug', 'mentions-legales')->sole();
        $before = $page->body_fr;

        $this->put('/admin/pages/'.$page->id, $this->form([
            'slug' => 'mentions-legales',
            'title_fr' => 'Mentions légales',
            'body_fr' => "## Éditeur du site\n\nAteliers **Pehouet** <script>alert(1)</script> [lien](javascript:alert(1))",
            'preview' => '1',
        ]))->assertOk()
            ->assertSee('<h2>Éditeur du site</h2>', false)
            ->assertSee('<strong>Pehouet</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false)
            ->assertSee(__('admin_content.pages.preview_notice'))
            ->assertSee('data-page-previewed', false);

        $this->assertSame($before, $page->fresh()->body_fr);
        $this->assertFalse($page->fresh()->is_published);
    }

    public function test_a_page_is_updated_and_its_versions_logged(): void
    {
        $this->admin();
        $page = CustomPage::query()->where('slug', 'mentions-legales')->sole();
        $this->get('/mentions-legales')->assertOk(); // admin preview, no cache for visitors yet

        $this->put('/admin/pages/'.$page->id, $this->form([
            'slug' => 'mentions-legales',
            'title_fr' => 'Mentions légales',
            'body_fr' => 'Éditeur : Ateliers Pehouet 5K1.',
        ]))->assertRedirect(route('admin.pages.edit', $page))
            ->assertSessionHas('status', __('admin_content.pages.saved', ['title' => 'Mentions légales']));

        $activity = CmsActivity::query()->where('action', 'pages.update')->sole();
        $this->assertFalse($activity->before['is_published']);
        $this->assertTrue($activity->after['is_published']);
        $this->assertSame('Éditeur : Ateliers Pehouet 5K1.', $activity->after['body_fr']);

        // Saving again without a change says so.
        $this->put('/admin/pages/'.$page->id, $this->form([
            'slug' => 'mentions-legales',
            'title_fr' => 'Mentions légales',
            'body_fr' => 'Éditeur : Ateliers Pehouet 5K1.',
        ]))->assertSessionHas('status', __('admin.flash.nothing'));

        Auth::logout();
        $this->get('/mentions-legales')->assertOk()->assertSee('5K1');
    }

    public function test_the_edit_form_shows_the_saved_page_and_its_rendered_body(): void
    {
        $this->admin();
        $photo = $this->uploadPhoto();
        $page = CustomPage::query()->create([
            'slug' => 'evenements', 'title_fr' => 'Événements', 'body_fr' => "- Marché d’art\n- Fresque", 'cover_media_id' => $photo->id,
        ]);

        $this->get('/admin/pages/'.$page->id)->assertOk()
            ->assertSee('value="evenements"', false)
            ->assertSee('<li>Marché d’art</li>', false)
            ->assertSee($photo->ulid, false)
            ->assertSee(route('pages.custom', 'evenements'), false)
            ->assertSee(route('admin.pages.destroy', $page), false);
    }

    public function test_a_page_is_deleted(): void
    {
        $this->admin();
        $page = CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda', 'is_published' => true]);
        $this->get('/agenda')->assertOk();

        $this->delete('/admin/pages/'.$page->id)
            ->assertRedirect(route('admin.pages.index'))
            ->assertSessionHas('status', __('admin_content.pages.deleted', ['title' => 'Agenda']));

        $this->assertModelMissing($page);
        $this->assertSame('Agenda', CmsActivity::query()->where('action', 'pages.delete')->sole()->before['title_fr']);

        Auth::logout();
        $this->get('/agenda')->assertNotFound();
    }

    public function test_guests_and_non_admins_cannot_manage_pages(): void
    {
        $this->post('/admin/pages', $this->form())->assertRedirect(route('admin.login'));

        $this->admin(['is_admin' => false]);
        $this->post('/admin/pages', $this->form())->assertForbidden();
        $this->get('/admin/pages')->assertForbidden();

        $this->assertSame(2, CustomPage::query()->count());
    }
}
