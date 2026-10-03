<?php

namespace Tests\Feature\Artists\Concerns;

use App\Artists\ArtistDirectory;
use App\Cms\Cms;
use App\Cms\DatabaseHealth;
use App\Cms\Media\MediaManager;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Exhibition;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Helpers of the artist pages tests (docs/ARTISTS.md §10): an administrator, artist / artwork /
 * exhibition rows (no factories), valid PNG files built with zlib + crc32 (no GD), photos of the CMS
 * library, and ways to take the database away.
 *
 * Self-contained on purpose: it uses no helper of the CMS session, so the suite never depends on a
 * trait that may not exist yet. Tests needing the CMS core call requireCmsMedia(); tests rendering a
 * view written by another area call requireView().
 */
trait BuildsArtists
{
    private int $artistSequence = 0;

    /** @var list<string> temporary files behind the UploadedFile instances */
    private array $artistTemporaryFiles = [];

    /** Creates an administrator (users.is_admin when the CMS added that column) and logs in as them. */
    protected function admin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        if (Schema::hasColumn('users', 'is_admin') && ! array_key_exists('is_admin', $attributes)) {
            $user->forceFill(['is_admin' => true])->save();
        }

        $this->actingAs($user);

        return $user;
    }

    /** A published artist (override anything with $attributes). */
    protected function artist(array $attributes = []): Artist
    {
        $n = ++$this->artistSequence;

        return Artist::query()->create($attributes + [
            'slug' => 'artiste-'.$n,
            'name' => 'Artiste '.$n,
            'is_published' => true,
            'position' => $n,
        ]);
    }

    /** A published artwork of $artist, placed last. */
    protected function artwork(Artist $artist, array $attributes = []): Artwork
    {
        $n = ++$this->artistSequence;

        return Artwork::query()->create($attributes + [
            'artist_id' => $artist->id,
            'title_fr' => 'Œuvre '.$n,
            'position' => $n,
        ]);
    }

    /** A published year-only group exhibition of $artist. */
    protected function exhibition(Artist $artist, array $attributes = []): Exhibition
    {
        $n = ++$this->artistSequence;

        return Exhibition::query()->create($attributes + [
            'artist_id' => $artist->id,
            'title_fr' => 'Exposition '.$n,
            'kind' => 'group',
            'year' => 2019,
        ]);
    }

    /** A valid PNG upload ($w × $h, solid colour). */
    protected function pngFile(string $name = 'photo.png', int $w = 8, int $h = 6, array $rgb = [248, 212, 73]): UploadedFile
    {
        return $this->temporaryUpload(self::pngBytes($w, $h, $rgb), $name, 'image/png');
    }

    /** Any bytes as an upload (refusal tests). */
    protected function fileWith(string $bytes, string $name, string $mime = 'application/octet-stream'): UploadedFile
    {
        return $this->temporaryUpload($bytes, $name, $mime);
    }

    /**
     * A photo stored in the CMS library by MediaManager: a $w × $h PNG with its 480 px variant.
     *
     * @param  array<string, mixed>  $attributes  any of MediaManager::ATTRIBUTES (alt_fr, in_gallery…)
     */
    protected function photo(array $attributes = [], int $w = 600, int $h = 450): Media
    {
        $this->requireCmsMedia();

        return app(MediaManager::class)->store(
            $this->pngFile('oeuvre.png', $w, $h),
            [480 => $this->pngFile('oeuvre-480.png', 480, (int) round(480 * $h / $w))],
            $attributes,
            auth()->id(),
        );
    }

    /**
     * A bare row of the media table (no files): enough for the photo usage checks.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function mediaRow(array $attributes = []): int
    {
        if (! Schema::hasTable('media')) {
            $this->markTestSkipped('The CMS media table does not exist yet.');
        }

        return (int) DB::table('media')->insertGetId($attributes + [
            'ulid' => strtolower((string) Str::ulid()),
            'driver' => 'database',
            'mime' => 'image/png',
            'extension' => 'png',
            'width' => 800,
            'height' => 600,
            'size' => 1234,
            'variants' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Skips the test until the CMS core (photo library + admin layout) is installed. */
    protected function requireCmsMedia(): void
    {
        if (! class_exists(MediaManager::class) || ! class_exists(Cms::class) || ! Schema::hasTable('media')) {
            $this->markTestSkipped('The CMS photo library (App\Cms\Media\MediaManager) is not installed yet.');
        }

        if (! view()->exists('admin.layouts.app')) {
            $this->markTestSkipped('The CMS admin layout (admin.layouts.app) does not exist yet.');
        }
    }

    /** Skips the test until $view (written by another area) exists. */
    protected function requireView(string ...$views): void
    {
        foreach ($views as $view) {
            if (! view()->exists($view)) {
                $this->markTestSkipped('The view '.$view.' does not exist yet.');
            }
        }
    }

    /** Drops the artist tables (as before their migration) and forgets the cached artist pages. */
    protected function dropArtistTables(): void
    {
        Schema::dropIfExists('exhibitions');
        Schema::dropIfExists('artworks');
        Schema::dropIfExists('artists');

        app(ArtistDirectory::class)->flush();
    }

    /** Runs the artists migration again (after dropArtistTables()). */
    protected function createArtistTables(): void
    {
        (require database_path('migrations/2026_10_03_100000_create_artists_tables.php'))->up();
    }

    /**
     * Makes $name (configured with $config) the default connection until the test ends (the previous
     * default comes back before RefreshDatabase rolls its transaction back).
     *
     * @param  array<string, mixed>  $config
     */
    protected function useDefaultConnection(string $name, array $config): void
    {
        $previous = config('database.default');

        config(['database.connections.'.$name => $config, 'database.default' => $name]);
        array_unshift($this->beforeApplicationDestroyedCallbacks, fn () => config(['database.default' => $previous]));

        app(ArtistDirectory::class)->flush();

        if (class_exists(Cms::class)) {
            app(Cms::class)->flush();
        }
    }

    /** Points the default connection at a database that cannot be opened (a paused or unreachable server). */
    protected function breakDatabase(): void
    {
        $this->useDefaultConnection('artists_unreachable', [
            'driver' => 'sqlite',
            'database' => '/nonexistent/ateliers-pehouet/artists-unreachable.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * A new instance of the read model, as the next request on another server would get it — with the
     * per-request state of the CMS circuit breaker forgotten too (the app resets it for every request).
     */
    protected function freshDirectory(): ArtistDirectory
    {
        $this->app->forgetInstance(ArtistDirectory::class);

        if (class_exists(DatabaseHealth::class) && method_exists(DatabaseHealth::class, 'reset')) {
            app(DatabaseHealth::class)->reset();
        }

        return app(ArtistDirectory::class);
    }

    /** PNG bytes: 8-bit RGB, one solid colour, built with zlib and crc32. */
    protected static function pngBytes(int $w = 8, int $h = 6, array $rgb = [248, 212, 73]): string
    {
        $row = "\x00".str_repeat(pack('C3', ...$rgb), $w); // filter type 0 + pixels

        return "\x89PNG\r\n\x1A\n"
            .self::pngChunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0))
            .self::pngChunk('IDAT', (string) gzcompress(str_repeat($row, $h), 6))
            .self::pngChunk('IEND', '');
    }

    /** One PNG chunk: length, type, data, CRC-32 of type + data. */
    protected static function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    protected function tearDownBuildsArtists(): void
    {
        foreach ($this->artistTemporaryFiles as $path) {
            @unlink($path);
        }

        $this->artistTemporaryFiles = [];
    }

    private function temporaryUpload(string $bytes, string $name, string $mime): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ap-artists-');
        file_put_contents($path, $bytes);
        $this->artistTemporaryFiles[] = $path;

        return new UploadedFile($path, $name, $mime, null, true);
    }
}
