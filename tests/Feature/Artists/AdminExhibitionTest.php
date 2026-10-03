<?php

namespace Tests\Feature\Artists;

use App\Models\Artist;
use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Artists\Concerns\BuildsArtists;
use Tests\TestCase;

/**
 * The exhibitions of an artist page in the admin (docs/ARTISTS.md §6): list by status, create and
 * edit (year-only or dated, the year following the start date), validation, visual, deletion.
 */
class AdminExhibitionTest extends TestCase
{
    use BuildsArtists, RefreshDatabase;

    /** An administrator (with the explicit admin right when that column exists), signed in. */
    protected function admin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + (Schema::hasColumn('users', 'is_admin') ? ['is_admin' => true] : []));
        $this->actingAs($user);

        return $user;
    }

    private function ariane(): Artist
    {
        return $this->artist(['name' => 'Ariane Roux', 'slug' => 'ariane-roux']);
    }

    public function test_the_list_groups_exhibitions_by_status(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 3, 12));
        $this->admin();
        $artist = $this->ariane();
        $this->exhibition($artist, ['title_fr' => 'Ancienne', 'year' => 2019]);
        $this->exhibition($artist, ['title_fr' => 'Maintenant', 'kind' => 'solo', 'year' => 2026, 'starts_on' => '2026-09-20', 'ends_on' => '2026-11-15', 'venue' => 'Galerie du Port', 'city' => 'Marseille']);
        $this->exhibition($artist, ['title_fr' => 'Bientôt', 'kind' => 'residency', 'year' => 2027, 'starts_on' => '2027-01-10']);
        $this->exhibition($artist, ['title_fr' => 'Cachée', 'year' => 2020, 'is_published' => false]);

        $response = $this->get(route('admin.artists.exhibitions.index', $artist))->assertOk();

        $response->assertSeeInOrder([
            __('admin_artists.exhibitions.groups.current'), 'Maintenant',
            __('admin_artists.exhibitions.groups.upcoming'), 'Bientôt',
            __('admin_artists.exhibitions.groups.past'), 'Cachée', 'Ancienne',
        ]);
        $response->assertSee('Galerie du Port, Marseille');
        $response->assertSee(__('admin_artists.kinds.residency'));
        $response->assertSee(__('admin_artists.status.hidden'));
        $response->assertSee('20 septembre – 15 novembre 2026');
        $response->assertSee(route('admin.artists.exhibitions.create', $artist), false);
    }

    public function test_an_artist_without_exhibitions_gets_an_invitation(): void
    {
        $this->admin();
        $artist = $this->ariane();

        $this->get(route('admin.artists.exhibitions.index', $artist))
            ->assertOk()
            ->assertSee(__('admin_artists.exhibitions.empty.title'))
            ->assertSee(route('admin.artists.exhibitions.create', $artist), false);
    }

    public function test_the_creation_form_starts_with_sensible_defaults(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 3, 12));
        $this->admin();
        $artist = $this->ariane();

        $response = $this->get(route('admin.artists.exhibitions.create', $artist))->assertOk();

        $response->assertSee('action="'.route('admin.artists.exhibitions.store', $artist).'"', false);
        $response->assertSee('value="2026"', false);
        $response->assertSee('<option value="group" selected', false);
        $response->assertSee('type="date"', false);
        $response->assertSee('data-exhibition-start', false);
        $response->assertDontSee('name="_method"', false);
    }

    public function test_create_a_dated_exhibition(): void
    {
        $this->admin();
        $artist = $this->ariane();

        $response = $this->post(route('admin.artists.exhibitions.store', $artist), [
            'title_fr' => 'Lumières du Sud',
            'title_en' => 'Southern lights',
            'kind' => 'solo',
            'venue' => 'Galerie du Port',
            'city' => 'Marseille',
            'year' => '2020',
            'starts_on' => '2026-09-20',
            'ends_on' => '2026-11-15',
            'url' => 'https://galerie-du-port.example/lumieres',
            'description_fr' => "Peintures et gravures.\r\nNouvelle série.",
            'description_en' => '',
            'media_id' => '',
            'is_published' => '1',
        ]);

        $response->assertRedirect(route('admin.artists.exhibitions.index', $artist));
        $response->assertSessionHas('status', __('admin_artists.flash.exhibition_created', ['title' => 'Lumières du Sud', 'name' => 'Ariane Roux']));

        $exhibition = Exhibition::query()->where('artist_id', $artist->id)->firstOrFail();
        $this->assertSame(2026, $exhibition->year, 'the year follows the start date');
        $this->assertSame('2026-09-20', $exhibition->starts_on?->format('Y-m-d'));
        $this->assertSame('2026-11-15', $exhibition->ends_on?->format('Y-m-d'));
        $this->assertSame('solo', $exhibition->kind);
        $this->assertSame("Peintures et gravures.\nNouvelle série.", $exhibition->description_fr);
        $this->assertNull($exhibition->description_en);
        $this->assertTrue($exhibition->is_published);

        if (Schema::hasTable('cms_activity')) {
            $this->assertDatabaseHas('cms_activity', ['action' => 'artists.exhibition_created', 'subject' => 'artist:'.$artist->id]);
        }
    }

    public function test_create_a_year_only_exhibition(): void
    {
        $this->admin();
        $artist = $this->ariane();

        $this->post(route('admin.artists.exhibitions.store', $artist), [
            'title_fr' => 'Premiers tirages',
            'kind' => 'group',
            'year' => '2019',
            'starts_on' => '',
            'ends_on' => '',
            'is_published' => '0',
        ])->assertRedirect(route('admin.artists.exhibitions.index', $artist));

        $exhibition = Exhibition::query()->where('artist_id', $artist->id)->firstOrFail();
        $this->assertSame(2019, $exhibition->year);
        $this->assertNull($exhibition->starts_on);
        $this->assertFalse($exhibition->is_published);
    }

    public function test_an_invalid_exhibition_comes_back_with_422_and_plain_messages(): void
    {
        $this->admin();
        $artist = $this->ariane();

        $response = $this->post(route('admin.artists.exhibitions.store', $artist), [
            'title_fr' => '',
            'venue' => 'Un lieu à garder',
            'kind' => 'party',
            'year' => '',
            'starts_on' => '',
            'ends_on' => '2026-05-01',
            'url' => 'http://pas-securise.example',
            'description_fr' => str_repeat('d', 1501),
        ]);

        $response->assertStatus(422);
        $response->assertViewIs('admin.artists.exhibitions.form');
        $response->assertSee('Un lieu à garder', false);
        $response->assertSee('value="2026-05-01"', false);
        $response->assertSee(__('admin_artists.validation.year_required'), false);
        $response->assertSee(__('admin_artists.validation.starts_required'), false);
        $response->assertSee(__('admin_artists.validation.https'), false);
        $errors = $response->viewData('errors')->getBag('default');
        foreach (['title_fr', 'kind', 'year', 'starts_on', 'url', 'description_fr'] as $field) {
            $this->assertTrue($errors->has($field), $field.' should be refused');
        }

        $this->post(route('admin.artists.exhibitions.store', $artist), [
            'title_fr' => 'À l’envers',
            'starts_on' => '2026-05-10',
            'ends_on' => '2026-05-01',
        ])->assertStatus(422)->assertSee(__('admin_artists.validation.ends_after'), false);

        $this->post(route('admin.artists.exhibitions.store', $artist), ['title_fr' => 'Trop tôt', 'year' => '1850'])->assertStatus(422);
        $this->post(route('admin.artists.exhibitions.store', $artist), ['title_fr' => 'Pas une date', 'starts_on' => '2026-02-30'])->assertStatus(422);

        $this->assertSame(0, Exhibition::query()->count());
    }

    public function test_update_an_exhibition(): void
    {
        $this->admin();
        $artist = $this->ariane();
        $exhibition = $this->exhibition($artist, ['title_fr' => 'Lumières', 'year' => 2024, 'starts_on' => '2024-04-01', 'ends_on' => '2024-06-30']);

        $this->get(route('admin.artists.exhibitions.edit', [$artist, $exhibition]))
            ->assertOk()
            ->assertSee('value="2024-04-01"', false)
            ->assertSee('name="_method" value="PUT"', false);

        // Dates removed: a year-only entry again.
        $this->put(route('admin.artists.exhibitions.update', [$artist, $exhibition]), [
            'title_fr' => 'Lumières du Sud',
            'kind' => 'residency',
            'year' => '2023',
            'starts_on' => '',
            'ends_on' => '',
            'is_published' => '0',
        ])->assertRedirect(route('admin.artists.exhibitions.index', $artist))
            ->assertSessionHas('status', __('admin_artists.flash.exhibition_updated', ['title' => 'Lumières du Sud', 'name' => 'Ariane Roux']));

        $exhibition->refresh();
        $this->assertSame('Lumières du Sud', $exhibition->title_fr);
        $this->assertSame('residency', $exhibition->kind);
        $this->assertSame(2023, $exhibition->year);
        $this->assertNull($exhibition->starts_on);
        $this->assertNull($exhibition->ends_on);
        $this->assertFalse($exhibition->is_published);

        $this->put(route('admin.artists.exhibitions.update', [$artist, $exhibition]), ['title_fr' => ''])->assertStatus(422);
        $this->assertSame('Lumières du Sud', $exhibition->fresh()->title_fr);
    }

    public function test_a_visual_from_the_library(): void
    {
        $this->admin();
        $this->requireCmsMedia();
        $artist = $this->ariane();
        $visual = $this->photo(['original_name' => 'vue-expo.png'], 800, 500);

        $form = $this->get(route('admin.artists.exhibitions.create', $artist))->assertOk();
        $form->assertSee('value="'.$visual->id.'"', false);
        $form->assertSee('vue-expo.png');
        $form->assertSee('data-media-picker', false);

        $this->post(route('admin.artists.exhibitions.store', $artist), ['title_fr' => 'Avec visuel', 'year' => '2025', 'media_id' => (string) $visual->id])
            ->assertRedirect(route('admin.artists.exhibitions.index', $artist));

        $exhibition = Exhibition::query()->where('artist_id', $artist->id)->firstOrFail();
        $this->assertSame($visual->id, $exhibition->media_id);

        $this->get(route('admin.artists.exhibitions.index', $artist))->assertOk()->assertSee('/media/', false);
        $this->get(route('admin.artists.exhibitions.edit', [$artist, $exhibition]))->assertOk()->assertSee($visual->ulid, false);
    }

    public function test_delete_an_exhibition(): void
    {
        $this->admin();
        $artist = $this->ariane();
        $exhibition = $this->exhibition($artist, ['title_fr' => 'Lumières']);
        $foreign = $this->exhibition($this->artist(), ['title_fr' => 'Ailleurs']);

        $this->delete(route('admin.artists.exhibitions.destroy', [$artist, $foreign]))->assertNotFound();

        $this->delete(route('admin.artists.exhibitions.destroy', [$artist, $exhibition]))
            ->assertRedirect(route('admin.artists.exhibitions.index', $artist))
            ->assertSessionHas('status', __('admin_artists.flash.exhibition_deleted', ['title' => 'Lumières', 'name' => 'Ariane Roux']));

        $this->assertNull($exhibition->fresh());
        $this->assertNotNull($foreign->fresh());
    }
}
