<?php

namespace Tests\Feature\Artists;

use App\Artists\ArtistDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Feature\Artists\Concerns\BuildsArtists;
use Tests\TestCase;

/**
 * The artist pages never break the public site (docs/ARTISTS.md §1 and §12): a failed load is
 * remembered for cms.cache.retry seconds and the last good copy is served; only without any copy do
 * /artistes (its calm "unavailable" state) and every artist page answer 503 + Retry-After + no-store.
 */
class ResilienceTest extends TestCase
{
    use BuildsArtists, RefreshDatabase;

    public function test_missing_tables_give_an_empty_index_and_not_found_pages(): void
    {
        Log::spy();
        $this->artist(['slug' => 'camille-durand']);
        $this->dropArtistTables();

        $directory = app(ArtistDirectory::class);

        $this->assertSame([], $directory->all());
        $this->assertNull($directory->find('camille-durand', null, true));
        $this->assertSame(0, $directory->count());
        $this->assertSame([], $directory->sitemapEntries());
        $this->assertSame(['prev' => null, 'next' => null], $directory->neighbors('camille-durand'));
        $this->assertFalse($directory->available());
        $this->assertSame(self::failedMarker(), cache()->get(ArtistDirectory::CACHE_KEY));

        if (view()->exists('artists.index')) {
            $this->assertUnavailable($this->get('/artistes'))->assertViewHas('available', false)->assertViewHas('artists', []);
        }

        $this->assertUnavailable($this->get('/artistes/camille-durand'));
        $this->get('/')->assertOk();
    }

    public function test_an_unreachable_database_gives_an_empty_index_and_not_found_pages(): void
    {
        Log::spy();
        $this->breakDatabase();

        if (view()->exists('artists.index')) {
            $this->assertUnavailable($this->get('/artistes'))->assertViewHas('available', false);
            $this->assertUnavailable($this->get('/artistes?lang=en'));
        }

        $this->assertUnavailable($this->get('/artistes/camille-durand'));
        $this->assertSame([], app(ArtistDirectory::class)->sitemapEntries());
        $this->assertFalse(app(ArtistDirectory::class)->available());
    }

    public function test_a_failed_load_is_cached_for_the_retry_window_and_logged_once(): void
    {
        Log::spy();
        $attempts = 0;
        DB::extend('artists_counting', function () use (&$attempts) {
            $attempts++;

            throw new RuntimeException('could not connect to server: Connection refused');
        });
        $this->useDefaultConnection('artists_counting', ['driver' => 'artists_counting']);

        $this->assertSame([], $this->freshDirectory()->all());
        $this->assertSame([], $this->freshDirectory()->all());
        $this->assertNull($this->freshDirectory()->find('camille-durand'));

        $this->assertSame(1, $attempts, 'the database is not retried within the retry window');
        $this->assertSame(self::failedMarker(), cache()->get(ArtistDirectory::CACHE_KEY));
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, 'Artist pages unavailable'));

        $this->travel(config('cms.cache.retry') + 1)->seconds();
        $this->assertSame([], $this->freshDirectory()->all());

        $this->assertSame(2, $attempts);
        Log::shouldHaveReceived('warning')->twice()->withArgs(fn (string $message): bool => str_contains($message, 'Artist pages unavailable'));
    }

    public function test_the_artists_come_back_after_the_retry_window(): void
    {
        Log::spy();
        $this->dropArtistTables();
        $this->assertSame([], $this->freshDirectory()->all());

        // The migration runs, a row arrives without any model event (nothing flushes the failure).
        $this->createArtistTables();
        DB::table('artists')->insert(['slug' => 'camille-durand', 'name' => 'Camille Durand', 'is_published' => true,
            'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame([], $this->freshDirectory()->all(), 'still within the retry window');

        $this->travel(config('cms.cache.retry') + 1)->seconds();

        $this->assertSame(['camille-durand'], array_column($this->freshDirectory()->all(), 'slug'));
        $this->assertTrue(app(ArtistDirectory::class)->available());
    }

    public function test_admins_read_through_a_cached_failure(): void
    {
        $this->artist(['slug' => 'camille-durand', 'is_published' => false]);
        cache()->put(ArtistDirectory::CACHE_KEY, self::failedMarker(), 60);

        $this->assertNull($this->freshDirectory()->find('camille-durand', null, true));

        $this->admin();

        $this->assertNotNull($this->freshDirectory()->find('camille-durand', null, true));
        $this->assertSame(self::failedMarker(), cache()->get(ArtistDirectory::CACHE_KEY));
    }

    public function test_a_broken_cache_store_falls_back_to_the_database(): void
    {
        Log::spy();
        $this->artist(['slug' => 'camille-durand']);
        config(['cms.cache.store' => 'does-not-exist']);

        $this->assertSame(['camille-durand'], array_column($this->freshDirectory()->all(), 'slug'));

        $this->freshDirectory()->flush();
    }

    public function test_the_last_good_copy_is_served_while_the_database_is_unreachable(): void
    {
        Log::spy();
        $this->artist(['slug' => 'camille-durand', 'name' => 'Camille Durand']);
        $this->assertSame(['camille-durand'], array_column($this->freshDirectory()->all(), 'slug'));
        $this->assertIsArray(cache()->get(ArtistDirectory::LASTGOOD_KEY));

        cache()->forget(ArtistDirectory::CACHE_KEY); // the snapshot expired…
        $this->breakDatabase();                       // …and the database is down

        $directory = $this->freshDirectory();
        $this->assertSame(['camille-durand'], array_column($directory->all(), 'slug'));
        $this->assertTrue($directory->available());
        $this->assertTrue($directory->stale());
        $this->assertNotSame([], $directory->sitemapEntries());

        if (view()->exists('artists.show')) {
            $this->get('/artistes/camille-durand')->assertOk()->assertSee('Camille Durand');
            $this->get('/artistes/inconnue')->assertNotFound();
        }
    }

    public function test_cached_values_of_another_shape_are_misses(): void
    {
        $this->artist(['slug' => 'camille-durand']);

        foreach ([['artists' => []], ['version' => ArtistDirectory::VERSION - 1, 'artists' => []], 'garbage', ['failed' => true]] as $value) {
            cache()->put(ArtistDirectory::CACHE_KEY, $value, 60);

            $this->assertSame(['camille-durand'], array_column($this->freshDirectory()->all(), 'slug'), var_export($value, true));
        }
    }

    public function test_the_snapshot_survives_a_serializing_cache_store(): void
    {
        $directory = sys_get_temp_dir().'/artists-cache-'.uniqid();
        config([
            'cache.stores.artists_file' => ['driver' => 'file', 'path' => $directory, 'serialize' => false],
            'cms.cache.store' => 'artists_file',
        ]);
        $artist = $this->artist(['slug' => 'camille-durand', 'name' => 'Camille Durand']);
        $this->exhibition($artist, ['starts_on' => '2025-03-01', 'ends_on' => '2025-04-30']);

        $first = $this->freshDirectory()->find('camille-durand');
        $second = $this->freshDirectory()->find('camille-durand'); // read back from the file store

        $this->assertSame($first['cv'], $second['cv']);
        $this->assertSame(ArtistDirectory::VERSION, cache()->store('artists_file')->get(ArtistDirectory::CACHE_KEY)['version']);

        cache()->store('artists_file')->flush();
        @rmdir($directory);
    }

    private function assertUnavailable(TestResponse $response): TestResponse
    {
        $response->assertStatus(503)->assertHeader('Retry-After', '300');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        return $response;
    }

    /** @return array{version: int, failed: true} */
    private static function failedMarker(): array
    {
        return ['version' => ArtistDirectory::VERSION, 'failed' => true];
    }
}
