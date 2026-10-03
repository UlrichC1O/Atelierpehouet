<?php

namespace Tests\Feature\Cms;

use App\Cms\Media\MediaManager;
use App\Cms\Media\MediaStorageManager;
use App\Cms\Media\RetiredFiles;
use App\Models\Media;
use App\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * Files of replaced or deleted photos stay servable for a grace period, then a later write purges
 * them (docs/CMS.md §13 C12).
 */
class MediaRetiredTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    private function retired(): RetiredFiles
    {
        return app(RetiredFiles::class);
    }

    /** @return list<string> */
    private function retiredKeys(): array
    {
        return DB::table(RetiredFiles::TABLE)->orderBy('key')->pluck('key')->all();
    }

    public function test_the_grace_period_is_twice_the_snapshot_lifetime_and_at_least_two_minutes(): void
    {
        foreach ([600 => 1200, 60 => 120, 30 => 120, 0 => 120, 3600 => 7200] as $ttl => $grace) {
            config(['cms.cache.ttl' => $ttl]);
            $this->assertSame($grace, RetiredFiles::grace(), 'ttl '.$ttl);
        }
    }

    public function test_replacing_retires_the_old_keys_and_a_later_write_purges_them(): void
    {
        $media = $this->uploadPhoto();
        $old = $media->keys();

        app(MediaManager::class)->replace($media, $this->pngFile('neuf.png', 40, 30));

        sort($old);
        $this->assertSame($old, $this->retiredKeys());
        $this->assertSame(['database'], DB::table(RetiredFiles::TABLE)->distinct()->pluck('driver')->all());
        foreach ($old as $key) {
            $this->assertSame('database', $this->retired()->driverOf($key));
            $this->assertTrue(MediaFile::query()->where('key', $key)->exists(), $key.' still stored');
        }

        // Within the grace period a write purges nothing.
        $this->travel(RetiredFiles::grace() - 5)->seconds();
        $this->uploadPhoto();
        $this->assertSame($old, $this->retiredKeys());

        $this->travel(10)->seconds();
        foreach ($old as $key) {
            $this->assertNull($this->retired()->driverOf($key), $key.' no longer servable');
        }
        $this->assertTrue(MediaFile::query()->where('key', $old[0])->exists(), 'nothing is purged before the next write');

        $this->uploadPhoto();

        $this->assertSame([], $this->retiredKeys());
        $this->assertSame(0, MediaFile::query()->whereIn('key', $old)->count());
        $this->assertSame([$media->fresh()->key()], MediaFile::query()->where('key', 'like', $media->fresh()->ulid.'%')->pluck('key')->all());
    }

    public function test_deleting_retires_the_files_and_purge_removes_only_expired_ones(): void
    {
        $first = $this->uploadPhoto();
        $firstKeys = $first->keys();
        app(MediaManager::class)->delete($first);

        $this->travel(RetiredFiles::grace() - 30)->seconds();
        $second = $this->uploadPhoto();
        $secondKeys = $second->keys();
        app(MediaManager::class)->delete($second);
        $this->assertSame(0, Media::query()->count());
        $this->assertSame(6, MediaFile::query()->count(), 'deleted files stay servable for a while');

        $this->travel(60)->seconds();

        $this->assertSame(3, $this->retired()->purge());
        $this->assertSame(0, MediaFile::query()->whereIn('key', $firstKeys)->count());
        $this->assertSame(3, MediaFile::query()->whereIn('key', $secondKeys)->count());
        $this->assertSame(0, $this->retired()->purge(), 'nothing else has expired');
    }

    public function test_files_on_the_disk_are_retired_and_purged_too(): void
    {
        Storage::fake('media');
        config(['cms.media.driver' => 'filesystem']);
        $media = app(MediaManager::class)->store($this->webpFile('a.webp', 1600, 1200), [480 => $this->webpFile('b.webp', 480, 360)]);
        $keys = $media->keys();

        app(MediaManager::class)->delete($media);

        Storage::disk('media')->assertExists($keys);
        $this->assertSame('filesystem', $this->retired()->driverOf($keys[0]));

        $this->travel(RetiredFiles::grace() + 1)->seconds();
        $this->assertSame(2, $this->retired()->purge());

        Storage::disk('media')->assertMissing($keys);
        $this->assertSame([], $this->retiredKeys());
    }

    public function test_an_entry_of_an_unknown_driver_is_dropped(): void
    {
        Log::spy();
        DB::table(RetiredFiles::TABLE)->insert(['key' => str_repeat('a', 26).'.webp', 'driver' => 'cloud', 'retired_at' => now()->subDay()]);

        $this->assertSame(1, $this->retired()->purge());
        $this->assertSame([], $this->retiredKeys());
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_an_entry_whose_file_cannot_be_deleted_stays_for_the_next_purge(): void
    {
        Log::spy();
        $media = $this->uploadPhoto();
        $keys = $media->keys();
        app(MediaManager::class)->delete($media);
        Schema::drop('media_files'); // the database storage now fails

        $this->travel(RetiredFiles::grace() + 1)->seconds();

        $this->assertSame(0, $this->retired()->purge());
        sort($keys);
        $this->assertSame($keys, $this->retiredKeys());
    }

    public function test_retiring_a_key_twice_is_harmless(): void
    {
        $this->retired()->retire('database', ['k1.webp', 'k1.webp']);
        $this->retired()->retire('database', ['k1.webp', 'k2.webp']);

        $this->assertSame(['k1.webp', 'k2.webp'], $this->retiredKeys());
    }

    public function test_before_the_migration_old_files_are_deleted_at_once(): void
    {
        Schema::drop(RetiredFiles::TABLE);
        $media = $this->uploadPhoto();
        $replaced = $media->keys();

        app(MediaManager::class)->replace($media, $this->pngFile());

        $this->assertSame(0, MediaFile::query()->whereIn('key', $replaced)->count());
        $this->assertSame(1, MediaFile::query()->count());
        $this->assertSame(0, $this->retired()->purge(), 'purging without the table is a no-op');

        $deleted = $media->fresh()->keys();
        app(MediaManager::class)->delete($media->fresh());
        $this->assertSame(0, MediaFile::query()->count());
        $this->assertNull(app(MediaStorageManager::class)->driver('database')->get($deleted[0]));
    }
}
