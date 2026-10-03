<?php

namespace Tests\Feature\Cms;

use App\Cms\Media\InvalidImage;
use App\Cms\Media\JpegSanitizer;
use App\Cms\Media\MediaManager;
use App\Cms\Media\MediaStorageManager;
use App\Cms\Media\RetiredFiles;
use App\Models\CustomPage;
use App\Models\Media;
use App\Models\MediaFile;
use App\Models\MediaSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;
use Tests\Unit\Cms\MediaFixtures;

/** The photo library's write side (docs/CMS.md §4.5, §13 C8–C12). */
class MediaManagerTest extends TestCase
{
    use InteractsWithCms, MediaFixtures, RefreshDatabase;

    private function manager(): MediaManager
    {
        return app(MediaManager::class);
    }

    /** Bytes stored for a key, through the photo's own driver. */
    private function stored(Media $media, string $key): ?string
    {
        return app(MediaStorageManager::class)->driver($media->driver)->get($key)['bytes'] ?? null;
    }

    public function test_a_png_is_stored_in_the_database_with_its_texts(): void
    {
        $user = User::factory()->create();

        $media = $this->manager()->store($this->pngFile('Fresque École.png', 12, 9), [], [
            'alt_fr' => '  Fresque de l’école  ',
            'alt_en' => '',
            'caption_fr' => "Ligne 1\r\nLigne 2",
            'service_slug' => 'peinture-murale',
            'in_gallery' => '1',
            'position' => '3',
            'focal_x' => 140,
            'focal_y' => '20',
            'driver' => 'filesystem',   // not accepted: ignored
            'ulid' => 'forged',
        ], $user->id);

        $media->refresh();
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $media->ulid);
        $this->assertSame(['database', 'image/png', 'png', 12, 9], [$media->driver, $media->mime, $media->extension, $media->width, $media->height]);
        $this->assertSame([], $media->variants);
        $this->assertSame('Fresque de l’école', $media->alt_fr);
        $this->assertNull($media->alt_en);
        $this->assertSame("Ligne 1\nLigne 2", $media->caption_fr);
        $this->assertSame('peinture-murale', $media->service_slug);
        $this->assertTrue($media->in_gallery);
        $this->assertSame([3, 100, 20], [$media->position, $media->focal_x, $media->focal_y]);
        $this->assertSame('Fresque École.png', $media->original_name);
        $this->assertSame($user->id, $media->uploaded_by);

        $file = MediaFile::query()->where('key', $media->key())->firstOrFail();
        $this->assertSame(self::pngBytes(12, 9), base64_decode($file->contents, true));
        $this->assertSame($media->size, $file->size);
        $this->assertSame('image/png', $file->mime);
    }

    public function test_variants_are_kept_only_when_they_match_the_main_image(): void
    {
        $media = $this->manager()->store($this->webpFile('main.webp', 1600, 1200), [
            480 => $this->webpFile('a.webp', 481, 361),     // ±2 px: kept
            960 => $this->webpFile('b.webp', 960, 720),     // kept
            1600 => $this->webpFile('c.webp', 1600, 1200),  // not smaller than the main image
            777 => $this->webpFile('d.webp', 777, 583),     // not a configured width
            '480x' => $this->webpFile('e.webp', 480, 360),  // not a width
        ]);

        $this->assertSame([[481, 361], [960, 720]], array_map(fn (array $v): array => [$v['width'], $v['height']], $media->variants));
        $this->assertSame([$media->ulid.'-480.webp', $media->ulid.'-960.webp'], array_column($media->variants, 'key'));
        foreach ($media->keys() as $key) {
            $this->assertNotNull($this->stored($media, $key), $key);
        }
        $this->assertSame(3, MediaFile::query()->count());

        $skipped = $this->manager()->store($this->webpFile('main.webp', 1600, 1200), [
            480 => $this->webpFile('square.webp', 480, 480),             // other aspect ratio
            960 => $this->webpFile('narrow.webp', 940, 705),             // 20 px off
            1600 => $this->fileWith('not an image', 'x.webp'),           // not an image
        ]);
        $this->assertSame([], $skipped->variants);
    }

    public function test_jpeg_gps_and_camera_data_are_stripped_and_the_orientation_kept(): void
    {
        $media = $this->manager()->store($this->jpegFile('IMG_0001.JPG', 40, 30, self::exifSegment(6)));

        $bytes = $this->stored($media, $media->key());
        $this->assertSame(['image/jpeg', 'jpg'], [$media->mime, $media->extension]);
        $this->assertStringNotContainsString('Pehouet Test Camera', $bytes);
        $this->assertStringNotContainsString(pack('V*', 48, 1, 51, 1, 2400, 100), $bytes);
        $this->assertSame(6, JpegSanitizer::orientation($bytes));
        $this->assertSame([30, 40], [$media->width, $media->height], 'width and height as displayed (rotated a quarter turn)');
        $this->assertSame(strlen($bytes), $media->size);
    }

    public function test_png_text_chunks_are_stripped(): void
    {
        $iend = self::pngChunk('IEND', '');
        $png = str_replace($iend, self::pngChunk('tEXt', "GPS\x0048.85N 2.35E").$iend, self::pngBytes(5, 5));

        $media = $this->manager()->store($this->fileWith($png, 'carte.png', 'image/png'));

        $this->assertSame(self::pngBytes(5, 5), $this->stored($media, $media->key()));
    }

    public function test_the_type_is_sniffed_from_the_content_not_the_name(): void
    {
        $media = $this->manager()->store($this->fileWith(self::pngBytes(), 'photo.jpg', 'image/jpeg'));

        $this->assertSame(['image/png', 'png'], [$media->mime, $media->extension]);
        $this->assertStringEndsWith('.png', $media->key());
    }

    /** @return array<string, array{string, string, string}> */
    public static function refusedFiles(): array
    {
        return [
            'svg' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="10" height="10"/></svg>', 'logo.svg', 'type'],
            'svg with xml prolog' => ['<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'logo.png', 'type'],
            'html' => ['<!DOCTYPE html><html><body><script>alert(1)</script></body></html>', 'page.jpg', 'not_image'],
            'text' => ['Juste du texte, pas une image.', 'notes.png', 'not_image'],
            'bmp' => ['BM'.pack('V', 70).str_repeat("\x00", 4).pack('V', 54).pack('VVVvvVVVVVV', 40, 2, 2, 1, 24, 0, 16, 2835, 2835, 0, 0).str_repeat("\x00", 16), 'image.bmp', 'type'],
            'truncated png' => [substr(self::pngBytes(4, 4), 0, 40), 'cut.png', 'not_image'],
            'png with a damaged image chunk' => [substr(self::pngBytes(4, 4), 0, -16).'XXXX'.self::pngChunk('IEND', ''), 'crc.png', 'not_image'], // wrong IDAT CRC
            'jpeg without its end' => [substr(self::jpegBytes(16, 8), 0, -2), 'cut.jpg', 'not_image'],
            'gif frame outside its screen' => [substr_replace(self::gifBytes(6, 4), pack('v', 60), 13 + 6 + 5, 2), 'wide.gif', 'not_image'],
            'avif' => [pack('N', 32).'ftypavif'.pack('N', 0).'avifmif1miafMA1A'.pack('N', 8).'meta'.str_repeat("\x00", 64), 'photo.avif', 'type'],
            'heic' => [pack('N', 28).'ftypheic'.pack('N', 0).'mif1heicmiaf'.str_repeat("\x00", 64), 'IMG_0001.HEIC', 'type'],
        ];
    }

    #[DataProvider('refusedFiles')]
    public function test_non_raster_or_broken_files_are_refused(string $bytes, string $name, string $reason): void
    {
        try {
            $this->manager()->store($this->fileWith($bytes, $name));
            $this->fail('The file was accepted.');
        } catch (InvalidImage $e) {
            $this->assertSame($reason, $e->reason);
        }

        $this->assertSame(0, Media::query()->count());
        $this->assertSame(0, MediaFile::query()->count());
    }

    public function test_size_and_pixel_limits(): void
    {
        config(['cms.media.max_kb' => 1]);

        $this->assertRefused('too_big', fn () => $this->manager()->store($this->jpegFile('big.jpg', 1600, 1200))); // ≈ 45 KB
        $this->assertRefused('too_big', fn () => $this->manager()->store($this->fileWith(str_repeat('x', 2048), 'big.png')));

        config(['cms.media.max_kb' => 8192, 'cms.media.max_pixels' => 40_000_000]);
        // A header claiming 8000 × 6000 px (a decompression bomb): refused before any decoding.
        $bomb = substr(self::pngBytes(1, 1), 0, 8).self::pngChunk('IHDR', pack('NNCCCCC', 8000, 6000, 8, 2, 0, 0, 0))
            .self::pngChunk('IDAT', (string) gzcompress("\x00\x00\x00\x00")).self::pngChunk('IEND', '');
        $this->assertRefused('too_many_pixels', fn () => $this->manager()->store($this->fileWith($bomb, 'bomb.png')));

    }

    /** @return array<string, array{int, string}> */
    public static function uploadErrors(): array
    {
        return [
            'over upload_max_filesize' => [UPLOAD_ERR_INI_SIZE, 'too_big'],
            'over the form MAX_FILE_SIZE' => [UPLOAD_ERR_FORM_SIZE, 'too_big'],
            'partial upload' => [UPLOAD_ERR_PARTIAL, 'interrupted'],
            'no file' => [UPLOAD_ERR_NO_FILE, 'interrupted'],
            'no temporary folder' => [UPLOAD_ERR_NO_TMP_DIR, 'storage'],
            'disk full' => [UPLOAD_ERR_CANT_WRITE, 'storage'],
        ];
    }

    #[DataProvider('uploadErrors')]
    public function test_php_upload_errors_have_their_own_reasons(int $error, string $reason): void
    {
        $failed = new UploadedFile($this->pngFile()->getRealPath(), 'photo.png', 'image/png', $error, true);

        $this->assertRefused($reason, fn () => $this->manager()->store($failed));
        $this->assertContains($reason, InvalidImage::REASONS);
        $this->assertSame(0, Media::query()->count());
    }

    public function test_a_stored_file_is_capped_at_three_and_a_half_megabytes(): void
    {
        $header = substr(self::pngBytes(4, 4), 0, 8 + 25);
        $idat = self::pngChunk('IDAT', random_bytes(3_400_000)); // checked by its header and CRC only
        $iend = self::pngChunk('IEND', '');

        $this->assertRefused('too_big', fn () => $this->manager()->store($this->fileWith($header.self::pngChunk('IDAT', random_bytes(3_600_000)).$iend, 'huge.png')));

        // The cap applies to what is kept: metadata dropped, the same upload fits.
        $media = $this->manager()->store($this->fileWith($header.self::pngChunk('tEXt', 'Comment'."\x00".str_repeat('x', 300_000)).$idat.$iend, 'heavy.png'));
        $this->assertLessThanOrEqual(MediaManager::MAX_STORED_BYTES, $media->size);
        $this->assertSame(strlen($header.$idat.$iend), $media->size);
    }

    public function test_a_storage_failure_is_reported_and_leaves_nothing_behind(): void
    {
        Log::spy();
        Schema::drop('media_files');

        $this->assertRefused('storage', fn () => $this->manager()->store($this->pngFile()));
        $this->assertSame(0, Media::query()->count());

        config(['cms.media.driver' => 'cloud']);
        $this->assertRefused('storage', fn () => $this->manager()->store($this->pngFile()));
    }

    public function test_the_filesystem_driver_writes_to_the_media_disk(): void
    {
        Storage::fake('media');
        config(['cms.media.driver' => 'filesystem']);

        $media = $this->manager()->store($this->webpFile('a.webp', 1600, 1200), [480 => $this->webpFile('b.webp', 480, 360)]);

        $this->assertSame('filesystem', $media->driver);
        Storage::disk('media')->assertExists([$media->key(), $media->ulid.'-480.webp']);
        $this->assertSame(0, MediaFile::query()->count());
        $this->assertSame(self::webpBytes(1600, 1200), $this->stored($media, $media->key()));

        $this->manager()->delete($media);
        Storage::disk('media')->assertExists([$media->key(), $media->ulid.'-480.webp']); // retired, still served a while

        $this->travel(RetiredFiles::grace() + 1)->seconds();
        $this->manager()->store($this->webpFile('c.webp', 8, 6)); // a later write purges them
        Storage::disk('media')->assertMissing([$media->key(), $media->ulid.'-480.webp']);
    }

    public function test_replacing_a_photo_keeps_its_id_texts_and_placements_and_changes_its_urls(): void
    {
        $media = $this->uploadPhoto(['alt_fr' => 'Avant 7F3A', 'in_gallery' => true, 'service_slug' => 'sculpture']);
        MediaSlot::query()->create(['slot' => 'home.feature', 'media_id' => $media->id]);
        $oldKeys = $media->keys();
        $oldUrl = $media->item()->url();

        $replaced = $this->manager()->replace($media, $this->jpegFile('nouvelle.jpg', 1200, 900), [960 => $this->jpegFile('n-960.jpg', 960, 720)]);

        $this->assertSame($media->id, $replaced->id);
        $fresh = Media::query()->findOrFail($media->id);
        $this->assertNotSame($oldKeys[0], $fresh->key());
        $this->assertSame(['image/jpeg', 'jpg', 1200, 900], [$fresh->mime, $fresh->extension, $fresh->width, $fresh->height]);
        $this->assertSame([$fresh->ulid.'-960.jpg'], array_column($fresh->variants, 'key'));
        $this->assertSame(['Avant 7F3A', true, 'sculpture', 'nouvelle.jpg'], [$fresh->alt_fr, $fresh->in_gallery, $fresh->service_slug, $fresh->original_name]);
        $this->assertSame($media->id, cms()->slot('home.feature')?->id);
        $this->assertNotSame($oldUrl, cms()->slot('home.feature')?->url());
        foreach ($fresh->keys() as $key) {
            $this->assertNotNull($this->stored($fresh, $key));
        }
        foreach ($oldKeys as $key) {
            $this->assertNotNull($this->stored($fresh, $key), 'old file '.$key.' retired, still servable');
        }

        $this->travel(RetiredFiles::grace() + 1)->seconds();
        $this->uploadPhoto();
        foreach ($oldKeys as $key) {
            $this->assertNull($this->stored($fresh, $key), 'old file '.$key.' purged by a later write');
        }
    }

    public function test_a_replacement_can_carry_the_original_file_name(): void
    {
        $media = $this->uploadPhoto();

        $this->manager()->replace($media, $this->webpFile('blob', 800, 600), [], 'DSC_0042.JPG');

        $this->assertSame('DSC_0042.JPG', $media->fresh()->original_name);
    }

    public function test_a_refused_replacement_changes_nothing(): void
    {
        $media = $this->uploadPhoto();
        $keys = $media->keys();

        $this->assertRefused('type', fn () => $this->manager()->replace($media, $this->fileWith('<svg xmlns="http://www.w3.org/2000/svg"/>', 'x.svg')));

        $this->assertSame($keys, Media::query()->findOrFail($media->id)->keys());
        $this->assertSame($keys, $media->keys());
        $this->assertSame(3, MediaFile::query()->count());
    }

    public function test_deleting_a_photo_removes_its_files_and_placements(): void
    {
        $media = $this->uploadPhoto(['in_gallery' => true]);
        $other = $this->uploadPhoto();
        MediaSlot::query()->create(['slot' => 'about.portrait', 'media_id' => $media->id]);
        MediaSlot::query()->create(['slot' => 'home.feature', 'media_id' => $other->id]);
        $page = CustomPage::query()->create(['slug' => 'agenda', 'title_fr' => 'Agenda', 'cover_media_id' => $media->id, 'is_published' => true]);
        $this->assertCount(1, cms()->gallery());

        $this->manager()->delete($media);

        $this->assertNull(Media::query()->find($media->id));
        $this->assertSame(['home.feature'], MediaSlot::query()->pluck('slot')->all());
        $this->assertNull($page->fresh()->cover_media_id);
        $this->assertSame([], cms()->gallery());
        $this->assertNull(cms()->slot('about.portrait'));
        $this->assertNull(cms()->page('agenda')['cover']);
        $this->assertSame([...$media->keys(), ...$other->keys()], MediaFile::query()->orderBy('id')->pluck('key')->all(), 'files retired, not deleted yet');
        $this->assertSame(3, DB::table(RetiredFiles::TABLE)->whereIn('key', $media->keys())->count());

        $this->travel(RetiredFiles::grace() + 1)->seconds();
        app(RetiredFiles::class)->purge();
        $this->assertSame($other->keys(), MediaFile::query()->orderBy('id')->pluck('key')->all());
    }

    /** @return array<string, array{string, string, string, string, int, int}> */
    public static function dirtyPhotos(): array
    {
        return [
            'jpeg (Exif + XMP GPS, MPF image, motion-photo video)' => [self::dirtyJpeg(24, 16, 6), self::cleanJpeg(24, 16, 6), 'image/jpeg', 'jpg', 16, 24],
            'png (eXIf, XMP, text, data after IEND)' => [self::dirtyPng(12, 9), self::pngBytes(12, 9), 'image/png', 'png', 12, 9],
            'webp (EXIF + XMP chunks, VP8X flags, appended data)' => [self::dirtyWebp(10, 6), self::cleanWebp(10, 6), 'image/webp', 'webp', 10, 6],
            'gif (comments, XMP, ICC, plain text, appended data)' => [self::dirtyGif(6, 4), self::gifBytes(6, 4, 2), 'image/gif', 'gif', 6, 4],
        ];
    }

    #[DataProvider('dirtyPhotos')]
    public function test_every_format_is_stored_without_its_metadata(string $dirty, string $clean, string $mime, string $extension, int $width, int $height): void
    {
        $media = $this->manager()->store($this->fileWith($dirty, 'phone.'.$extension));

        $bytes = (string) $this->stored($media, $media->key());
        $this->assertSame($clean, $bytes);
        foreach (self::metadataNeedles() as $label => $needle) {
            $this->assertStringNotContainsString($needle, $bytes, $label);
        }
        $this->assertSame($mime, getimagesizefromstring($bytes)['mime'] ?? null, 'the stored file still decodes');
        $this->assertSame([$mime, $extension, $width, $height, strlen($bytes)], [$media->mime, $media->extension, $media->width, $media->height, $media->size]);
    }

    /** @return array<string, array{int, list<int>}> */
    public static function orientations(): array
    {
        $cases = [];

        foreach (range(1, 8) as $orientation) {
            $cases['orientation '.$orientation] = [$orientation, $orientation >= 5 ? [30, 40] : [40, 30]];
        }

        return $cases;
    }

    /** @param  list<int>  $size */
    #[DataProvider('orientations')]
    public function test_a_quarter_turn_orientation_swaps_the_stored_width_and_height(int $orientation, array $size): void
    {
        $media = $this->manager()->store($this->jpegFile('IMG.JPG', 40, 30, self::exifSegment($orientation)));

        $bytes = (string) $this->stored($media, $media->key());
        $this->assertSame($size, [$media->width, $media->height]);
        $this->assertSame($orientation === 1 ? null : $orientation, JpegSanitizer::orientation($bytes));
        $this->assertSame([40, 30], array_slice(getimagesizefromstring($bytes), 0, 2), 'the pixels are not touched');
    }

    public function test_a_gif_is_kept_whole_and_never_gets_variants(): void
    {
        config(['cms.media.server_variants' => true]);

        $media = $this->manager()->store($this->gifFile('anim.gif', 1200, 900), [
            480 => $this->gifFile('a.gif', 480, 360),
            960 => $this->webpFile('b.webp', 960, 720),
        ]);

        $this->assertSame(['image/gif', 'gif', 1200, 900, []], [$media->mime, $media->extension, $media->width, $media->height, $media->variants]);
        $this->assertSame([$media->key()], MediaFile::query()->pluck('key')->all());
        $this->assertSame(self::gifBytes(1200, 900, 2), $this->stored($media, $media->key()));
    }

    public function test_an_animated_png_or_webp_never_gets_variants(): void
    {
        config(['cms.media.server_variants' => true]);

        $png = self::pngBytes(1200, 900);
        $apng = substr($png, 0, 8 + 25).self::pngChunk('acTL', pack('NN', 1, 0)).substr($png, 8 + 25);
        $webp = self::webpFileBytes(self::vp8x(0x02, 1200, 900).self::webpChunk('ANIM', str_repeat("\x00", 6))
            .self::anmfChunk(0, 0, 1200, 900, substr(self::webpBytes(1200, 900), 12)));
        $variants = fn (): array => [480 => $this->webpFile('a.webp', 480, 360), 960 => $this->pngFile('b.png', 960, 720)];

        foreach (['anim.png' => $apng, 'anim.webp' => $webp] as $name => $bytes) {
            $media = $this->manager()->store($this->fileWith($bytes, $name), $variants());

            $this->assertSame([1200, 900, []], [$media->width, $media->height, $media->variants], $name);
            $this->assertSame($bytes, $this->stored($media, $media->key()), $name);
        }

        // Still images keep the variants sent with them.
        $still = $this->manager()->store($this->pngFile('still.png', 1200, 900), $variants());
        $this->assertSame([480, 960], array_column($still->variants, 'width'));

        // …but never an animated copy: it would move at some screen widths only.
        $small = self::pngBytes(960, 720);
        $mixed = $this->manager()->store($this->pngFile('still.png', 1200, 900), [
            480 => $this->gifFile('a.gif', 480, 360),
            960 => $this->fileWith(substr($small, 0, 8 + 25).self::pngChunk('acTL', pack('NN', 1, 0)).substr($small, 8 + 25), 'b.png'),
        ]);
        $this->assertSame([], $mixed->variants);
    }

    public function test_the_server_resizes_nothing_unless_enabled(): void
    {
        $this->assertFalse(config('cms.media.server_variants'), 'off by default');

        $media = $this->manager()->store($this->jpegFile('large.jpg', 1200, 900));

        $this->assertSame([], $media->variants);
        $this->assertSame(1, MediaFile::query()->count());
    }

    public function test_the_server_resizes_the_variants_when_enabled_and_gd_exists(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD is not installed here: server-side variants cannot be generated.');
        }

        config(['cms.media.server_variants' => true]);

        $media = $this->manager()->store($this->jpegFile('large.jpg', 1200, 900));

        $this->assertSame([[480, 360], [960, 720]], array_map(fn (array $v): array => [$v['width'], $v['height']], $media->variants));
        foreach ($media->keys() as $key) {
            $this->assertSame('image/jpeg', getimagesizefromstring((string) $this->stored($media, $key))['mime'] ?? null, $key);
        }

        // Variants sent by the browser win: nothing is generated next to them.
        $sent = $this->manager()->store($this->jpegFile('large.jpg', 1200, 900), [480 => $this->jpegFile('s.jpg', 480, 360)]);
        $this->assertSame([480], array_column($sent->variants, 'width'));
    }

    public function test_the_library_quota_refuses_photos_beyond_it(): void
    {
        config(['cms.media.quota_mb' => 1]);
        $quota = 1024 * 1024;
        $photo = $this->manager()->store($this->pngFile('a.png', 40, 30));
        $this->assertSame($quota, $this->manager()->quotaBytes());
        DB::table('media_files')->insert(['key' => 'filler.png', 'mime' => 'image/png', 'size' => $quota - $photo->size - 10, 'contents' => '', 'created_at' => now()]);
        $this->assertSame($quota - 10, $this->manager()->usage());

        $this->assertRefused('quota', fn () => $this->manager()->store($this->pngFile('b.png', 40, 30)));
        $this->assertSame(1, Media::query()->count());

        // A replacement counts without the files it replaces.
        $this->manager()->replace($photo, $this->pngFile('c.png', 40, 30, [38, 95, 165]));
        $this->assertSame('c.png', $photo->fresh()->original_name);
        $this->assertRefused('quota', fn () => $this->manager()->replace($photo, $this->pngFile('d.png', 400, 300)));

        config(['cms.media.quota_mb' => 0]);
        $this->assertNull($this->manager()->quotaBytes());
        $this->manager()->store($this->pngFile('e.png', 40, 30));
        $this->assertSame(2, Media::query()->count());
    }

    public function test_the_usage_counts_the_storage_new_photos_go_to(): void
    {
        Storage::fake('media');
        $inDatabase = $this->uploadPhoto();
        $databaseBytes = (int) MediaFile::query()->sum('size');
        $this->assertSame($inDatabase->size + array_sum(array_column($inDatabase->variants, 'size')), $databaseBytes);
        $this->assertSame($databaseBytes, $this->manager()->usage());

        config(['cms.media.driver' => 'filesystem']);
        $this->assertSame(0, $this->manager()->usage());
        $onDisk = $this->uploadPhoto();
        $this->assertSame($onDisk->size + array_sum(array_column($onDisk->variants, 'size')), $this->manager()->usage());
    }

    public function test_a_file_imported_by_another_module_keeps_the_name_it_is_given(): void
    {
        // How the artists module imports its bundled pictures: a test-mode UploadedFile of a local file.
        $path = (string) tempnam(sys_get_temp_dir(), 'ap-import-');
        file_put_contents($path, self::webpBytes(64, 48));

        try {
            $media = $this->manager()->store(new UploadedFile($path, 'oeuvre.webp', 'image/webp', null, true), [], [
                'original_name' => "../../Œuvre\x01 d’atelier.webp",
                'alt_fr' => 'Œuvre',
            ]);
        } finally {
            @unlink($path);
        }

        $this->assertSame(['Œuvre d’atelier.webp', 'Œuvre', 64, 48], [$media->original_name, $media->alt_fr, $media->width, $media->height]);
        $this->assertSame(self::webpBytes(64, 48), $this->stored($media, $media->key()));
    }

    public function test_the_upload_helper_builds_what_the_admin_uploader_sends(): void
    {
        $media = $this->uploadPhoto(['alt_fr' => 'Atelier']);
        $item = cms()->media($media->id);

        $this->assertSame([1600, 1200], [$item->width, $item->height]);
        $this->assertSame([480, 960], array_column($item->variants, 'width'));
        $this->assertSame('Atelier', $item->alt('en'));
        $this->assertStringContainsString(' 480w, ', $item->srcset());
    }

    private function assertRefused(string $reason, callable $upload): void
    {
        try {
            $upload();
            $this->fail('The upload was accepted; expected '.$reason.'.');
        } catch (InvalidImage $e) {
            $this->assertSame($reason, $e->reason);
        }
    }
}
