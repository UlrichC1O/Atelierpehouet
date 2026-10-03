<?php

namespace Tests\Feature\Artists;

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Exhibition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Artists\Concerns\BuildsArtists;
use Tests\TestCase;

/** The artist models (docs/ARTISTS.md §3). */
class ModelsTest extends TestCase
{
    use BuildsArtists, RefreshDatabase;

    public function test_new_models_carry_the_documented_defaults(): void
    {
        $artist = new Artist;
        $artwork = new Artwork;
        $exhibition = new Exhibition;

        $this->assertSame('yellow', $artist->accent);
        $this->assertFalse($artist->is_published);
        $this->assertFalse($artist->is_example);
        $this->assertSame(0, $artist->position);
        $this->assertSame('none', $artwork->availability);
        $this->assertTrue($artwork->is_published);
        $this->assertSame('group', $exhibition->kind);
        $this->assertTrue($exhibition->is_published);
    }

    public function test_columns_are_cast(): void
    {
        $artist = $this->artist(['is_published' => '1', 'is_example' => 'false', 'position' => '4']);
        $artwork = $this->artwork($artist, ['is_published' => 0, 'position' => '2']);
        $exhibition = $this->exhibition($artist, ['year' => '2024', 'starts_on' => '2024-05-02', 'is_published' => 'on']);

        $artist->refresh();
        $artwork->refresh();
        $exhibition->refresh();

        $this->assertTrue($artist->is_published);
        $this->assertFalse($artist->is_example);
        $this->assertSame(4, $artist->position);
        $this->assertFalse($artwork->is_published);
        $this->assertSame(2, $artwork->position);
        $this->assertSame(2024, $exhibition->year);
        $this->assertTrue($exhibition->is_published);
        $this->assertSame('2024-05-02', $exhibition->starts_on?->format('Y-m-d'));
        $this->assertNull($exhibition->ends_on);
        $this->assertSame('2024-05-02', $exhibition->toArray()['starts_on']);
    }

    public function test_text_falls_back_to_french(): void
    {
        $artist = new Artist(['discipline_fr' => 'Peinture', 'discipline_en' => '  ', 'statement_fr' => 'Le geste', 'statement_en' => 'The gesture']);

        $this->assertSame('Peinture', $artist->text('discipline', 'en'));
        $this->assertSame('Peinture', $artist->text('discipline', 'fr'));
        $this->assertSame('The gesture', $artist->text('statement', 'en'));
        $this->assertSame('Le geste', $artist->text('statement', 'fr'));
        $this->assertNull($artist->text('bio', 'en'));
        $this->assertSame('Le geste', $artist->text('statement', 'de'));

        app()->setLocale('en');
        $this->assertSame('The gesture', $artist->text('statement'));

        $artwork = new Artwork(['title_fr' => 'Nocturne', 'title_en' => null]);
        $this->assertSame('Nocturne', $artwork->text('title', 'en'));
    }

    public function test_initials_and_url(): void
    {
        $this->assertSame('CD', Artist::initialsOf('Camille Durand'));
        $this->assertSame('PE', Artist::initialsOf('Pehouet'));
        $this->assertSame('ÉÖ', Artist::initialsOf('  élise   öberg  dupont'));
        $this->assertSame('JM', Artist::initialsOf('Jean-Pierre Martin'));
        $this->assertSame('DJ', Artist::initialsOf("d'Arc Jeanne"));
        $this->assertSame('', Artist::initialsOf(' — '));

        $artist = new Artist(['name' => 'Camille Durand', 'slug' => 'camille-durand']);

        $this->assertSame('CD', $artist->initials());
        $this->assertSame(route('artists.show', 'camille-durand'), $artist->url());
        $this->assertStringEndsWith('/artistes/camille-durand', $artist->url());
    }

    public function test_relations_and_order_of_the_artworks(): void
    {
        $artist = $this->artist();
        $second = $this->artwork($artist, ['position' => 2]);
        $first = $this->artwork($artist, ['position' => 1]);
        $third = $this->artwork($artist, ['position' => 2]);
        $exhibition = $this->exhibition($artist);

        $this->assertSame([$first->id, $second->id, $third->id], $artist->artworks()->pluck('id')->all());
        $this->assertSame([$exhibition->id], $artist->exhibitions()->pluck('id')->all());
        $this->assertTrue($first->artist->is($artist));
        $this->assertTrue($exhibition->artist->is($artist));
        $this->assertSame(['artworks_count' => 3, 'exhibitions_count' => 1],
            Artist::query()->withCount(['artworks', 'exhibitions'])->find($artist->id)?->only(['artworks_count', 'exhibitions_count']));
    }

    public function test_photo_id_is_the_portrait_else_the_first_artwork_with_a_photo(): void
    {
        $artist = $this->artist();
        $this->artwork($artist, ['position' => 1]);

        $this->assertNull($artist->photoId());

        $photo = $this->mediaRow();
        $this->artwork($artist, ['position' => 2, 'media_id' => $photo]);

        $this->assertSame($photo, $artist->refresh()->photoId());

        $portrait = $this->mediaRow();
        $artist->update(['portrait_media_id' => $portrait]);

        $this->assertSame($portrait, $artist->photoId());
    }

    public function test_saving_or_deleting_an_item_touches_its_artist(): void
    {
        $this->travelTo(now()->subDay());
        $artist = $this->artist();
        $before = $artist->updated_at?->getTimestamp();

        $this->travelBack();
        $artwork = $this->artwork($artist);

        $this->assertGreaterThan($before, $artist->refresh()->updated_at?->getTimestamp());

        $this->travel(1)->hours();
        $touched = $artist->updated_at?->getTimestamp();
        $artwork->delete();

        $this->assertGreaterThan($touched, $artist->refresh()->updated_at?->getTimestamp());
    }

    public function test_deleting_an_artist_cascades_to_its_artworks_and_exhibitions(): void
    {
        $artist = $this->artist();
        $other = $this->artist();
        $this->artwork($artist);
        $this->exhibition($artist);
        $kept = $this->artwork($other);

        $artist->delete();

        $this->assertSame([$kept->id], DB::table('artworks')->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame(0, DB::table('exhibitions')->count());
    }

    public function test_a_deleted_photo_leaves_no_dangling_reference(): void
    {
        $photo = $this->mediaRow();
        $artist = $this->artist(['portrait_media_id' => $photo]);
        $artwork = $this->artwork($artist, ['media_id' => $photo]);
        $exhibition = $this->exhibition($artist, ['media_id' => $photo]);

        DB::table('media')->where('id', $photo)->delete();

        $this->assertNull($artist->refresh()->portrait_media_id);
        $this->assertNull($artwork->refresh()->media_id);
        $this->assertNull($exhibition->refresh()->media_id);
    }

    public function test_the_admin_order_scope(): void
    {
        $b = $this->artist(['name' => 'Bruno', 'position' => 1]);
        $c = $this->artist(['name' => 'Chloé', 'position' => 0]);
        $a = $this->artist(['name' => 'Ariane', 'position' => 1]);

        $this->assertSame([$c->id, $a->id, $b->id], Artist::query()->ordered()->pluck('id')->all());
    }

    public function test_values_are_normalised_before_they_are_written(): void
    {
        $artist = $this->artist([
            'name' => "Camille\0 Durand\xC3\x28",          // a NUL byte and invalid UTF-8
            'location' => str_repeat('L', 300),             // longer than its column (120)
            'is_published' => '1',                          // a form value, not a bool
            'is_example' => 0,
        ]);

        $attributes = $artist->getAttributes();
        $this->assertSame(true, $attributes['is_published']);
        $this->assertSame(false, $attributes['is_example']);
        $this->assertTrue(mb_check_encoding($artist->name, 'UTF-8'));
        $this->assertStringNotContainsString("\0", $artist->name);
        $this->assertStringStartsWith('Camille Durand', $artist->name);
        $this->assertSame(120, mb_strlen($artist->location));

        $work = $this->artwork($artist, ['title_fr' => str_repeat('é', 200), 'is_published' => 'false']);
        $this->assertSame(160, mb_strlen($work->title_fr));
        $this->assertSame(false, $work->getAttributes()['is_published']);
    }
}
