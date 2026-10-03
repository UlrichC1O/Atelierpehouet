<?php

namespace Tests\Feature\Artists;

use App\Artists\ArtistDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Tests\Feature\Artists\Concerns\BuildsArtists;
use Tests\TestCase;

/** The public read model of the artist pages (docs/ARTISTS.md §4.1), without photos. */
class ArtistDirectoryTest extends TestCase
{
    use BuildsArtists, RefreshDatabase;

    private function directory(): ArtistDirectory
    {
        return app(ArtistDirectory::class);
    }

    public function test_it_is_a_container_singleton(): void
    {
        $this->assertSame(app(ArtistDirectory::class), app(ArtistDirectory::class));
    }

    public function test_all_lists_the_published_artists_by_position_then_name(): void
    {
        $this->artist(['name' => 'Bruno Morel', 'slug' => 'bruno-morel', 'position' => 2]);
        $this->artist(['name' => 'Chloé Marin', 'slug' => 'chloe-marin', 'position' => 1]);
        $this->artist(['name' => 'Ariane Roux', 'slug' => 'ariane-roux', 'position' => 2]);
        $this->artist(['name' => 'Caché', 'slug' => 'cache', 'position' => 0, 'is_published' => false]);

        $this->assertSame(['chloe-marin', 'ariane-roux', 'bruno-morel'], array_column($this->directory()->all(), 'slug'));
        $this->assertSame(3, $this->directory()->count());
        $this->assertTrue($this->directory()->available());
    }

    public function test_a_summary_holds_the_documented_keys(): void
    {
        $artist = $this->artist([
            'name' => 'Camille Durand', 'slug' => 'camille-durand', 'discipline_fr' => 'Peintre', 'discipline_en' => 'Painter',
            'location' => 'Lyon, France', 'statement_fr' => 'Peindre la lumière.', 'accent' => 'blue', 'is_example' => true,
        ]);
        $this->artwork($artist);
        $this->artwork($artist, ['is_published' => false]);
        $this->exhibition($artist);
        $this->exhibition($artist, ['is_published' => false]);

        $summary = $this->directory()->all('fr')[0];

        $this->assertSame(['id', 'slug', 'url', 'name', 'initials', 'discipline', 'location', 'statement', 'accent', 'portrait',
            'cover', 'counts', 'published', 'example', 'updated_at'], array_keys($summary));
        $this->assertSame($artist->id, $summary['id']);
        $this->assertSame(route('artists.show', 'camille-durand'), $summary['url']);
        $this->assertSame('CD', $summary['initials']);
        $this->assertSame('Peintre', $summary['discipline']);
        $this->assertSame('Lyon, France', $summary['location']);
        $this->assertSame('blue', $summary['accent']);
        $this->assertNull($summary['portrait']);
        $this->assertNull($summary['cover']);
        $this->assertSame(['artworks' => 1, 'exhibitions' => 1], $summary['counts']);
        $this->assertTrue($summary['published']);
        $this->assertTrue($summary['example']);
        $this->assertSame($artist->refresh()->updated_at?->getTimestamp(), $summary['updated_at']);
    }

    public function test_english_falls_back_to_french_and_invalid_accents_to_yellow(): void
    {
        $this->artist(['discipline_fr' => 'Céramiste', 'discipline_en' => null, 'statement_fr' => 'La terre.', 'statement_en' => 'Clay.', 'accent' => 'purple']);

        $en = $this->directory()->all('en')[0];

        $this->assertSame('Céramiste', $en['discipline']);
        $this->assertSame('Clay.', $en['statement']);
        $this->assertSame('yellow', $en['accent']);

        app()->setLocale('en');
        $this->assertSame('Clay.', $this->directory()->all()[0]['statement']);
        $this->assertSame('La terre.', $this->directory()->all('fr')[0]['statement']);
    }

    public function test_find_returns_published_artists_and_hidden_ones_only_on_request(): void
    {
        $this->artist(['slug' => 'publiee']);
        $this->artist(['slug' => 'brouillon', 'is_published' => false]);

        $this->assertSame('publiee', $this->directory()->find('publiee')['slug'] ?? null);
        $this->assertNull($this->directory()->find('brouillon'));
        $this->assertNull($this->directory()->find('inconnue', null, true));

        $hidden = $this->directory()->find('brouillon', null, true);

        $this->assertNotNull($hidden);
        $this->assertFalse($hidden['published']);
        $this->assertNotContains('brouillon', array_column($this->directory()->all(), 'slug'));
    }

    public function test_the_full_artist_page(): void
    {
        $this->travelTo(Carbon::create(2025, 3, 15, 10));

        $artist = $this->artist([
            'name' => 'Camille Durand', 'slug' => 'camille-durand', 'discipline_fr' => 'Peintre', 'statement_fr' => 'Peindre la lumière.',
            'bio_fr' => "Un **atelier** à Lyon.\n\n<script>alert(1)</script>\n\n- un\n- deux", 'bio_en' => '',
            'website' => 'https://www.camille-durand.test/oeuvres', 'instagram' => 'https://instagram.com/camille.durand/',
        ]);
        $first = $this->artwork($artist, ['title_fr' => 'Nocturne', 'title_en' => 'Night piece', 'year' => '2025', 'medium_fr' => 'Huile sur toile',
            'dimensions' => '100 × 80 cm', 'availability' => 'sold', 'description_fr' => 'Une nuit bleue.', 'position' => 1]);
        $this->artwork($artist, ['title_fr' => 'Étude', 'availability' => 'available', 'position' => 2]);
        $this->artwork($artist, ['title_fr' => 'Sans date', 'availability' => 'none', 'position' => 3]);
        $this->artwork($artist, ['title_fr' => 'Cachée', 'availability' => 'reserved', 'is_published' => false, 'position' => 4]);
        $this->artwork($artist, ['title_fr' => 'Hors liste', 'availability' => 'bogus', 'position' => 5]);

        $fr = $this->directory()->find('camille-durand', 'fr');

        $this->assertInstanceOf(HtmlString::class, $fr['bio_html']);
        $this->assertStringContainsString('<strong>atelier</strong>', $fr['bio_html']->toHtml());
        $this->assertStringContainsString('<li>deux</li>', $fr['bio_html']->toHtml());
        $this->assertStringNotContainsString('<script', $fr['bio_html']->toHtml());
        $this->assertSame('Peindre la lumière.', $fr['meta_description']);
        $this->assertSame('https://www.camille-durand.test/oeuvres', $fr['website']);
        $this->assertSame([
            ['label' => 'camille-durand.test', 'url' => 'https://www.camille-durand.test/oeuvres', 'icon' => 'globe'],
            ['label' => '@camille.durand', 'url' => 'https://instagram.com/camille.durand/', 'icon' => 'instagram'],
        ], $fr['links']);

        $this->assertSame(['Nocturne', 'Étude', 'Sans date', 'Hors liste'], array_column($fr['artworks'], 'title'));
        $this->assertSame(['available', 'sold'], $fr['availability_filters']);

        $work = $fr['artworks'][0];
        $this->assertSame(['id', 'title', 'year', 'medium', 'dimensions', 'description', 'availability', 'availability_label', 'media',
            'alt', 'caption', 'landscape'], array_keys($work));
        $this->assertSame($first->id, $work['id']);
        $this->assertSame('sold', $work['availability']);
        $this->assertSame(__('artists.availability.sold'), $work['availability_label']);
        $this->assertSame('Nocturne — Camille Durand', $work['alt']);
        $this->assertSame('Nocturne, 2025 — Huile sur toile, 100 × 80 cm', $work['caption']);
        $this->assertSame('Une nuit bleue.', $work['description']);
        $this->assertNull($work['media']);
        $this->assertFalse($work['landscape']);
        $this->assertNull($fr['artworks'][2]['availability_label']);
        $this->assertSame('Sans date', $fr['artworks'][2]['caption']);
        $this->assertSame('none', $fr['artworks'][3]['availability']);

        $en = $this->directory()->find('camille-durand', 'en');

        $this->assertSame('Night piece', $en['artworks'][0]['title']);
        $this->assertSame('Night piece, 2025 — Huile sur toile, 100 × 80 cm', $en['artworks'][0]['caption']);
        $this->assertSame('Étude', $en['artworks'][1]['title']);
        $this->assertStringContainsString('<strong>atelier</strong>', $en['bio_html']->toHtml(), 'the English bio falls back to French');
    }

    public function test_exhibitions_are_grouped_by_status_and_listed_as_a_cv(): void
    {
        $this->travelTo(Carbon::create(2025, 3, 15, 10));
        $artist = $this->artist(['slug' => 'camille-durand']);

        $current = $this->exhibition($artist, ['title_fr' => 'En cours', 'kind' => 'solo', 'year' => 2025, 'starts_on' => '2025-03-01', 'ends_on' => '2025-04-30', 'venue' => 'Galerie Nord', 'city' => 'Lyon']);
        $currentSooner = $this->exhibition($artist, ['title_fr' => 'Finit bientôt', 'year' => 2025, 'starts_on' => '2025-02-01', 'ends_on' => '2025-03-20']);
        $openToday = $this->exhibition($artist, ['title_fr' => 'Un jour', 'year' => 2025, 'starts_on' => '2025-03-15']);
        $upcomingLate = $this->exhibition($artist, ['title_fr' => 'Plus tard', 'kind' => 'fair', 'year' => 2025, 'starts_on' => '2025-09-01']);
        $upcoming = $this->exhibition($artist, ['title_fr' => 'Bientôt', 'kind' => 'residency', 'year' => 2025, 'starts_on' => '2025-05-02', 'ends_on' => '2025-05-30']);
        $nextYear = $this->exhibition($artist, ['title_fr' => 'L’an prochain', 'year' => 2026]);
        $pastDated = $this->exhibition($artist, ['title_fr' => 'Automne', 'year' => 2024, 'starts_on' => '2024-10-01', 'ends_on' => '2024-11-15']);
        $pastSpring = $this->exhibition($artist, ['title_fr' => 'Printemps', 'year' => 2024, 'starts_on' => '2024-04-01']);
        $pastYearOnly = $this->exhibition($artist, ['title_fr' => 'Année seule', 'year' => 2024, 'kind' => 'bogus']);
        $old = $this->exhibition($artist, ['title_fr' => 'Débuts', 'year' => 2019, 'url' => 'http://pas-https.test']);
        $this->exhibition($artist, ['title_fr' => 'Masquée', 'year' => 2023, 'is_published' => false]);

        $page = $this->directory()->find('camille-durand', 'fr');
        $ids = fn (array $items): array => array_column($items, 'id');

        $this->assertSame([$currentSooner->id, $current->id, $openToday->id], $ids($page['exhibitions']['current']));
        $this->assertSame([$upcoming->id, $upcomingLate->id, $nextYear->id], $ids($page['exhibitions']['upcoming']));
        $this->assertSame([$pastDated->id, $pastSpring->id, $pastYearOnly->id, $old->id], $ids($page['exhibitions']['past']));
        $this->assertSame([2026, 2025, 2024, 2019], array_keys($page['cv']));
        $this->assertSame([$upcomingLate->id, $upcoming->id, $openToday->id, $current->id, $currentSooner->id], $ids($page['cv'][2025]));
        $this->assertSame([$pastDated->id, $pastSpring->id, $pastYearOnly->id], $ids($page['cv'][2024]));

        $first = $page['exhibitions']['current'][1];
        $this->assertSame(['id', 'title', 'kind', 'kind_label', 'venue', 'city', 'year', 'starts_on', 'ends_on', 'dates', 'status',
            'status_label', 'description', 'url', 'media'], array_keys($first));
        $this->assertSame('solo', $first['kind']);
        $this->assertSame(__('artists.kinds.solo'), $first['kind_label']);
        $this->assertSame('1er mars – 30 avril 2025', $first['dates']);
        $this->assertSame('current', $first['status']);
        $this->assertSame(__('artists.status.current'), $first['status_label']);
        $this->assertSame('2025-03-01', $first['starts_on']);
        $this->assertSame('2025-04-30', $first['ends_on']);
        $this->assertSame('other', $page['cv'][2024][2]['kind']);
        $this->assertNull($page['cv'][2019][0]['url'], 'only https links are printed');
        $this->assertSame('2019', $page['cv'][2019][0]['dates']);
        $this->assertSame('1 March – 30 April 2025', $this->directory()->find('camille-durand', 'en')['exhibitions']['current'][1]['dates']);
    }

    public function test_meta_description_falls_back_to_the_statement_then_the_name_and_discipline(): void
    {
        $this->artist(['slug' => 'avec-meta', 'meta_fr' => 'Description choisie.', 'statement_fr' => 'Déclaration.']);
        $this->artist(['slug' => 'sans-meta', 'statement_fr' => "Une déclaration\nsur deux lignes ".str_repeat('très ', 60)]);
        $this->artist(['slug' => 'nue', 'name' => 'Léa Blanc', 'discipline_fr' => 'Sculptrice']);
        $this->artist(['slug' => 'anonyme', 'name' => 'Noé']);

        $this->assertSame('Description choisie.', $this->directory()->find('avec-meta')['meta_description']);
        $long = $this->directory()->find('sans-meta')['meta_description'];
        $this->assertStringStartsWith('Une déclaration sur deux lignes très', $long);
        $this->assertStringEndsWith('…', $long);
        $this->assertLessThanOrEqual(161, mb_strlen($long));
        $this->assertSame('Léa Blanc — Sculptrice', $this->directory()->find('nue')['meta_description']);
        $this->assertSame('Noé', $this->directory()->find('anonyme')['meta_description']);
        $this->assertNull($this->directory()->find('anonyme')['bio_html']);
        $this->assertSame([], $this->directory()->find('anonyme')['links']);
    }

    public function test_external_links_must_be_https(): void
    {
        DB::table('artists')->insert([
            'slug' => 'liens', 'name' => 'Liens', 'is_published' => true, 'website' => 'http://site.test',
            'instagram' => 'javascript:alert(1)', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $page = $this->directory()->find('liens');

        $this->assertNull($page['website']);
        $this->assertNull($page['instagram']);
        $this->assertSame([], $page['links']);
    }

    public function test_neighbors_wrap_around_the_published_artists(): void
    {
        $this->artist(['slug' => 'a', 'position' => 1]);
        $this->artist(['slug' => 'b', 'position' => 2]);
        $this->artist(['slug' => 'c', 'position' => 3]);
        $this->artist(['slug' => 'masque', 'position' => 4, 'is_published' => false]);

        $slugs = fn (array $neighbors): array => [$neighbors['prev']['slug'] ?? null, $neighbors['next']['slug'] ?? null];

        $this->assertSame(['a', 'c'], $slugs($this->directory()->neighbors('b')));
        $this->assertSame(['c', 'b'], $slugs($this->directory()->neighbors('a')));
        $this->assertSame(['b', 'a'], $slugs($this->directory()->neighbors('c')));
        $this->assertSame([null, null], $slugs($this->directory()->neighbors('masque')));
        $this->assertSame([null, null], $slugs($this->directory()->neighbors('inconnu')));
    }

    public function test_neighbors_are_empty_with_fewer_than_two_artists(): void
    {
        $this->artist(['slug' => 'seule']);

        $this->assertSame(['prev' => null, 'next' => null], $this->directory()->neighbors('seule'));
    }

    public function test_sitemap_entries(): void
    {
        $this->assertSame([], $this->directory()->sitemapEntries());

        $this->travelTo(Carbon::create(2025, 1, 10, 12));
        $first = $this->artist(['slug' => 'premiere']);
        $this->travelTo(Carbon::create(2025, 2, 10, 12));
        $second = $this->artist(['slug' => 'seconde']);
        $this->artist(['slug' => 'brouillon', 'is_published' => false]);
        $this->travelTo(Carbon::create(2025, 3, 10, 12));
        $this->artwork($first);

        $entries = $this->freshDirectory()->sitemapEntries();

        $this->assertSame([
            route('artists.index') => Carbon::create(2025, 3, 10, 12)->getTimestamp(),
            route('artists.show', 'premiere') => Carbon::create(2025, 3, 10, 12)->getTimestamp(),
            route('artists.show', 'seconde') => $second->updated_at?->getTimestamp(),
        ], $entries);
        $this->assertStringStartsWith('http', array_key_first($entries));
    }

    public function test_the_snapshot_takes_three_queries_then_comes_from_the_cache(): void
    {
        $artist = $this->artist();
        $this->artwork($artist);
        $this->exhibition($artist);

        // Only the artist tables: the CMS snapshot (translations, photos) has its own cache.
        $artistQueries = fn (): array => array_values(array_filter(
            array_column(DB::getQueryLog(), 'query'),
            fn (string $sql): bool => preg_match('/from "(artists|artworks|exhibitions)"/', $sql) === 1,
        ));

        $directory = $this->freshDirectory();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertCount(1, $directory->all());
        $this->assertNotNull($directory->find($artist->slug));
        $directory->neighbors($artist->slug);
        $this->assertCount(3, $artistQueries(), 'one snapshot (3 queries) per request');
        $this->assertIsArray(cache()->get(ArtistDirectory::CACHE_KEY)['artists'] ?? null);

        DB::flushQueryLog();
        $this->assertCount(1, $this->freshDirectory()->all());
        $this->assertSame([], $artistQueries(), 'the next request reads the cache');
    }

    public function test_the_cached_snapshot_holds_plain_values_only(): void
    {
        $artist = $this->artist();
        $this->artwork($artist);
        $this->exhibition($artist, ['starts_on' => '2025-03-01', 'year' => 2025]);
        $this->directory()->all();

        $cached = cache()->get(ArtistDirectory::CACHE_KEY);
        $objects = [];
        array_walk_recursive($cached, function (mixed $value) use (&$objects): void {
            if (is_object($value)) {
                $objects[] = $value::class;
            }
        });

        $this->assertSame([], $objects);
        $this->assertSame('2025-03-01', $cached['artists'][0]['exhibitions'][0]['starts_on']);
    }

    public function test_every_model_write_flushes_the_cache(): void
    {
        $artist = $this->artist(['name' => 'Avant']);
        $this->directory()->all();
        $this->assertTrue(cache()->has(ArtistDirectory::CACHE_KEY));

        $artist->update(['name' => 'Après']);

        $this->assertFalse(cache()->has(ArtistDirectory::CACHE_KEY));
        $this->assertSame('Après', $this->directory()->all()[0]['name']);

        $artwork = $this->artwork($artist);
        $this->assertSame(1, $this->directory()->all()[0]['counts']['artworks']);

        $artwork->update(['is_published' => false]);
        $this->assertSame(0, $this->directory()->all()[0]['counts']['artworks']);

        $exhibition = $this->exhibition($artist);
        $this->assertSame(1, $this->directory()->all()[0]['counts']['exhibitions']);

        $exhibition->delete();
        $this->assertSame(0, $this->directory()->all()[0]['counts']['exhibitions']);

        $artist->delete();
        $this->assertSame([], $this->directory()->all());
    }

    public function test_query_builder_writes_need_an_explicit_flush(): void
    {
        $artist = $this->artist(['name' => 'Avant']);
        $this->directory()->all();

        DB::table('artists')->where('id', $artist->id)->update(['name' => 'Après']);

        $this->assertSame('Avant', $this->freshDirectory()->all()[0]['name'], 'served from the cache');

        $this->directory()->flush();

        $this->assertSame('Après', $this->directory()->all()[0]['name']);
    }

    public function test_logged_in_users_read_fresh_data_without_touching_the_cache(): void
    {
        $artist = $this->artist(['name' => 'Avant']);
        $this->directory()->all();
        DB::table('artists')->where('id', $artist->id)->update(['name' => 'Après']);

        $this->assertSame('Avant', $this->freshDirectory()->all()[0]['name']);

        $this->admin();

        $this->assertSame('Après', $this->directory()->all()[0]['name']);
        $this->assertSame('Avant', cache()->get(ArtistDirectory::CACHE_KEY)['artists'][0]['name'], 'a fresh read never writes the cache');
    }

    public function test_media_resolves_photos_of_the_cms_library(): void
    {
        $this->assertNull(ArtistDirectory::media(null));
        $this->assertNull(ArtistDirectory::media(0));
        $this->assertNull(ArtistDirectory::media(999999));

        if (! function_exists('cms')) {
            return;
        }

        $id = $this->mediaRow(['alt_fr' => 'Atelier']);
        cms()->flush(); // a raw insert fires no model event

        $this->assertSame($id, ArtistDirectory::media($id)?->id);
        $this->assertSame('Atelier', ArtistDirectory::media($id)?->alt('fr'));
    }
}
