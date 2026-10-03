<?php

namespace Tests\Feature\Cms;

use App\Cms\Media\MediaManager;
use App\Cms\Media\RetiredFiles;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/** GET /media/{key}: the photos of the library, immutable and stateless (docs/CMS.md §4.5, §13 C12). */
class MediaFileTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    /**
     * Browsers keep a key for a year (it never changes content); Vercel's CDN for a day only, so a
     * deleted photo leaves the edge within 24 hours.
     */
    private function assertCachedForever(TestResponse $response): void
    {
        foreach (['public' => true, 'immutable' => true, 'max-age' => '31536000', 's-maxage' => null, 'private' => null, 'no-store' => null] as $directive => $value) {
            $this->assertSame($value, $response->headers->getCacheControlDirective($directive), $directive);
        }
        $response->assertHeader('Vercel-CDN-Cache-Control', 'public, max-age=86400');
    }

    private function assertNotFoundUncached(TestResponse $response): void
    {
        $response->assertNotFound()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->assertTrue($response->headers->getCacheControlDirective('no-store'));
        $this->assertNull($response->headers->getCacheControlDirective('immutable'));
        $this->assertFalse($response->headers->has('Vercel-CDN-Cache-Control'));
        $this->assertNoCookie($response);
    }

    private function assertNoCookie(TestResponse $response): void
    {
        $this->assertSame([], $response->headers->getCookies());
        $this->assertFalse($response->headers->has('Set-Cookie'));
    }

    public function test_a_photo_is_served_with_long_lived_safe_headers(): void
    {
        $media = $this->uploadPhoto();
        $key = $media->key();

        $response = $this->get('/media/'.$key);

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Content-Length', (string) strlen(self::webpBytes(1600, 1200)))
            ->assertHeader('ETag', '"'.$key.'"')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'")
            ->assertHeader('Content-Disposition', 'inline; filename="pehouet-'.$key.'"');
        $this->assertSame(self::webpBytes(1600, 1200), $response->getContent());
        $this->assertCachedForever($response);
        $this->assertSame($media->item()->url(), url('/media/'.$key));
        $this->assertNoCookie($response);
    }

    public function test_variants_have_their_own_urls(): void
    {
        $media = $this->uploadPhoto();

        $this->get($media->item()->url(480))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('ETag', '"'.$media->ulid.'-480.webp"');
        $this->assertSame(self::webpBytes(480, 360), $this->get('/media/'.$media->ulid.'-480.webp')->getContent());
    }

    public function test_a_browser_holding_the_key_gets_a_304(): void
    {
        $key = $this->uploadPhoto()->key();

        foreach (['"'.$key.'"', 'W/"'.$key.'"', '"other", "'.$key.'"', '*'] as $etag) {
            $response = $this->withHeader('If-None-Match', $etag)->get('/media/'.$key);

            $response->assertStatus(304)->assertHeader('ETag', '"'.$key.'"');
            $this->assertSame('', $response->getContent());
            $this->assertFalse($response->headers->has('Content-Type'));
            $this->assertCachedForever($response);
            $this->assertNoCookie($response);
        }

        $this->withHeader('If-None-Match', '"something-else"')->get('/media/'.$key)->assertOk();
    }

    public function test_unknown_or_foreign_keys_are_404(): void
    {
        $media = $this->uploadPhoto();

        foreach ([
            str_repeat('0', 26).'.webp',            // well-formed, unknown
            $media->ulid.'.png',                     // other extension than the photo's
            $media->ulid.'-1600.webp',               // a variant that does not exist
            $media->ulid.'-480.png',
        ] as $key) {
            $this->assertNotFoundUncached($this->get('/media/'.$key));
        }
    }

    public function test_malformed_keys_never_reach_the_storage(): void
    {
        $media = $this->uploadPhoto();

        foreach ([strtoupper($media->ulid).'.webp', $media->ulid.'.svg', $media->ulid.'.avif', $media->ulid.'.webp.php', '..%2F..%2F.env', $media->ulid.'-5.webp', 'x.webp'] as $key) {
            $this->get('/media/'.$key)->assertNotFound();
        }
    }

    public function test_a_replaced_photo_keeps_its_old_urls_for_the_grace_period_only(): void
    {
        $media = $this->uploadPhoto();
        $old = $media->keys();

        app(MediaManager::class)->replace($media, $this->pngFile());

        // Pages cached with the old URLs keep showing the photo until their snapshot expires.
        foreach ($old as $key) {
            $response = $this->get('/media/'.$key);
            $response->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('ETag', '"'.$key.'"');
            $this->assertCachedForever($response);
        }
        $this->withHeader('If-None-Match', '"'.$old[0].'"')->get('/media/'.$old[0])->assertStatus(304);
        $this->get('/media/'.$media->fresh()->key())->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->travel(RetiredFiles::grace() + 1)->seconds();

        foreach ($old as $key) {
            $this->assertNotFoundUncached($this->get('/media/'.$key));
        }
        $this->withHeader('If-None-Match', '"'.$old[0].'"')->get('/media/'.$old[0])->assertNotFound();
        $this->get('/media/'.$media->fresh()->key())->assertOk();
    }

    public function test_a_deleted_photo_is_served_for_the_grace_period_then_404(): void
    {
        $media = $this->uploadPhoto();
        $keys = $media->keys();

        app(MediaManager::class)->delete($media);

        $this->get('/media/'.$keys[0])->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get('/media/'.$keys[1])->assertOk();

        $this->travel(RetiredFiles::grace() + 1)->seconds();
        $this->assertNotFoundUncached($this->get('/media/'.$keys[0]));

        app(RetiredFiles::class)->purge();
        $this->assertNotFoundUncached($this->get('/media/'.$keys[1]));
    }

    public function test_without_the_retired_table_old_keys_are_404_at_once(): void
    {
        Schema::drop(RetiredFiles::TABLE); // production right after a deploy, before the migration ran
        $media = $this->uploadPhoto();
        $old = $media->key();

        app(MediaManager::class)->replace($media, $this->pngFile());

        $this->assertNotFoundUncached($this->get('/media/'.$old));
        $this->get('/media/'.$media->fresh()->key())->assertOk();
    }

    public function test_files_are_read_through_the_driver_recorded_on_the_photo(): void
    {
        Storage::fake('media');
        config(['cms.media.driver' => 'filesystem']);
        $onDisk = app(MediaManager::class)->store($this->pngFile('disk.png', 4, 3));
        config(['cms.media.driver' => 'database']);
        $inDatabase = app(MediaManager::class)->store($this->jpegFile('db.jpg', 4, 3));

        $this->get('/media/'.$onDisk->key())->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/media/'.$inDatabase->key())->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        Storage::disk('media')->delete($onDisk->key());
        $this->assertNotFoundUncached($this->get('/media/'.$onDisk->key()));
        $this->assertTrue(Media::query()->whereKey($onDisk->id)->exists());
    }

    public function test_head_requests_get_the_headers_only(): void
    {
        $key = $this->uploadPhoto()->key();

        $response = $this->call('HEAD', '/media/'.$key);

        $response->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->assertSame('', $response->getContent());
    }
}
