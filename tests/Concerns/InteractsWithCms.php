<?php

namespace Tests\Concerns;

use App\Cms\Cms;
use App\Cms\DatabaseMigrator;
use App\Cms\Media\MediaManager;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helpers for tests of the admin CMS (docs/CMS.md §4.6): an administrator, real image files built
 * without GD (PNG with zlib + crc32, a tiny baseline JPEG encoder for solid colours, lossless WebP),
 * photos in the library, and ways to take the CMS database away.
 *
 * Every image decodes in browsers and image libraries (not only getimagesize()).
 */
trait InteractsWithCms
{
    /** The logo's blue (#265fa5): the colour of every generated WebP. */
    protected const CMS_BLUE = [38, 95, 165];

    /** Lossless WebP (VP8L) bitstream after the size header: a solid #265fa5 image of any size. */
    private const WEBP_SOLID_BLUE = "\x07\xD0\xAF\x9A\xB4\xB4\xFF\x81\x88\xE8\x7F\x00\x00";

    /** @var list<string> temporary files behind the UploadedFile instances */
    private array $cmsTemporaryFiles = [];

    /**
     * Creates an administrator (users.is_admin = true when the column exists; pass
     * ['is_admin' => false] for a signed-in non-admin) and logs in as them.
     */
    protected function admin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + (Schema::hasColumn('users', 'is_admin') ? ['is_admin' => true] : []));
        $this->actingAs($user);

        return $user;
    }

    /**
     * Makes the CMS cache a store that serializes its values like the file or database stores do
     * (cache.serializable_classes = false: objects come back as incomplete classes).
     */
    protected function useSerializingCmsCache(): void
    {
        config([
            'cache.stores.cms_serializing' => ['driver' => 'array', 'serialize' => true],
            'cms.cache.store' => 'cms_serializing',
        ]);

        app(Cms::class)->flush();
    }

    /** A valid PNG upload ($w × $h, solid colour). */
    protected function pngFile(string $name = 'photo.png', int $w = 8, int $h = 6, array $rgb = [248, 212, 73]): UploadedFile
    {
        return $this->cmsUpload(self::pngBytes($w, $h, $rgb), $name, 'image/png');
    }

    /**
     * A valid baseline JPEG upload ($w × $h, solid colour). $segments (raw APPn/COM segments, e.g.
     * self::exifSegment()) are inserted after the JFIF header.
     */
    protected function jpegFile(string $name = 'photo.jpg', int $w = 8, int $h = 6, string $segments = '', array $rgb = [179, 44, 43]): UploadedFile
    {
        return $this->cmsUpload(self::jpegBytes($w, $h, $rgb, $segments), $name, 'image/jpeg');
    }

    /** A valid lossless WebP upload ($w × $h, solid #265fa5) — what the admin's uploader sends. */
    protected function webpFile(string $name = 'photo.webp', int $w = 8, int $h = 6): UploadedFile
    {
        return $this->cmsUpload(self::webpBytes($w, $h), $name, 'image/webp');
    }

    /** Any bytes as an upload (SVG, HTML, text… for refusal tests). */
    protected function fileWith(string $bytes, string $name, string $mime = 'application/octet-stream'): UploadedFile
    {
        return $this->cmsUpload($bytes, $name, $mime);
    }

    /**
     * A photo in the library as the admin's uploader stores it: 1600 × 1200 WebP with its 480 and
     * 960 px variants. $attributes: any of MediaManager::ATTRIBUTES (alt_fr, in_gallery…).
     */
    protected function uploadPhoto(array $attributes = []): Media
    {
        return app(MediaManager::class)->store(
            $this->webpFile('atelier.webp', 1600, 1200),
            [480 => $this->webpFile('atelier-480.webp', 480, 360), 960 => $this->webpFile('atelier-960.webp', 960, 720)],
            $attributes,
            auth()->id(),
        );
    }

    /**
     * Drops the CMS tables and forgets that the CMS migrations ran (as before them: every
     * 2026_10_03_* migration pending), then forgets the cached snapshot.
     */
    protected function dropCmsTables(): void
    {
        foreach (['media_slots', 'custom_pages', 'media_files', 'media', 'cms_activity', 'settings', 'cms_services', 'translation_overrides'] as $table) {
            Schema::dropIfExists($table);
        }

        DB::table('migrations')->where('migration', 'like', DatabaseMigrator::CMS_PREFIX.'%')->delete();
        app(DatabaseMigrator::class)->reset();
        app(Cms::class)->flush();
    }

    /**
     * Points the default database connection at a database that cannot be opened (an unreachable or
     * paused server): the public pages must still render with the file defaults.
     */
    protected function breakDatabase(): void
    {
        $this->useDefaultConnection('cms_unreachable', [
            'driver' => 'sqlite',
            'database' => '/nonexistent/ateliers-pehouet/unreachable.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * Makes $name (configured with $config) the default connection until the test ends. The previous
     * default comes back before RefreshDatabase rolls its transaction back on "the default connection".
     *
     * @param  array<string, mixed>  $config
     */
    protected function useDefaultConnection(string $name, array $config): void
    {
        $previous = config('database.default');

        config(['database.connections.'.$name => $config, 'database.default' => $name]);
        array_unshift($this->beforeApplicationDestroyedCallbacks, fn () => config(['database.default' => $previous]));

        app(Cms::class)->flush();
    }

    /** PNG bytes: 8-bit RGB, one solid colour, built with zlib and crc32. */
    protected static function pngBytes(int $w = 8, int $h = 6, array $rgb = [248, 212, 73]): string
    {
        $row = "\x00".str_repeat(pack('C3', ...$rgb), $w); // filter type 0 + pixels

        return "\x89PNG\r\n\x1A\n"
            .self::pngChunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0))
            .self::pngChunk('IDAT', (string) gzcompress(str_repeat($row, $h), 9))
            .self::pngChunk('IEND', '');
    }

    /** One PNG chunk: length, type, data, CRC-32 of type + data. */
    protected static function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }

    /**
     * Baseline JPEG bytes of a solid colour: every 8×8 block holds only a DC coefficient (quantisation
     * table of 1s), Huffman tables reduced to the few symbols used.
     */
    protected static function jpegBytes(int $w = 8, int $h = 6, array $rgb = [179, 44, 43], string $segments = ''): string
    {
        [$r, $g, $b] = $rgb;
        $components = [
            0.299 * $r + 0.587 * $g + 0.114 * $b,
            128 - 0.168736 * $r - 0.331264 * $g + 0.5 * $b,
            128 + 0.5 * $r - 0.418688 * $g - 0.081312 * $b,
        ];
        $dc = array_map(fn (float $value): int => 8 * (max(0, min(255, (int) round($value))) - 128), $components);

        // DC Huffman table: the categories of the three DC values and 0 (the next blocks repeat them),
        // all with 3-bit codes 000, 001…; AC table: only end-of-block, code "0".
        $categories = array_values(array_unique([0, ...array_map(fn (int $value): int => self::bitLength(abs($value)), $dc)]));
        sort($categories);
        $codes = array_flip($categories);

        $data = '';
        $buffer = 0;
        $count = 0;
        $write = function (int $value, int $length) use (&$data, &$buffer, &$count): void {
            $buffer = ($buffer << $length) | ($value & ((1 << $length) - 1));
            $count += $length;

            while ($count >= 8) {
                $count -= 8;
                $byte = ($buffer >> $count) & 0xFF;
                $data .= $byte === 0xFF ? "\xFF\x00" : chr($byte);
            }

            $buffer &= (1 << $count) - 1;
        };

        $previous = [0, 0, 0];
        $blocks = (int) (ceil($w / 8) * ceil($h / 8));

        for ($block = 0; $block < $blocks; $block++) {
            foreach ($dc as $component => $value) {
                $diff = $value - $previous[$component];
                $previous[$component] = $value;
                $category = self::bitLength(abs($diff));
                $write($codes[$category], 3);

                if ($category > 0) {
                    $write($diff >= 0 ? $diff : $diff + (1 << $category) - 1, $category);
                }

                $write(0, 1); // end of block
            }
        }

        if ($count > 0) {
            $write((1 << (8 - $count)) - 1, 8 - $count); // pad with 1-bits
        }

        $dcCounts = array_fill(0, 16, 0);
        $dcCounts[2] = count($categories);
        $acCounts = array_fill(0, 16, 0);
        $acCounts[0] = 1;

        return "\xFF\xD8"
            .self::jpegSegment(0xE0, "JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00")
            .$segments
            .self::jpegSegment(0xDB, "\x00".str_repeat("\x01", 64))
            .self::jpegSegment(0xC0, pack('CnnC', 8, $h, $w, 3)."\x01\x11\x00\x02\x11\x00\x03\x11\x00")
            .self::jpegSegment(0xC4, "\x00".pack('C*', ...$dcCounts).pack('C*', ...$categories))
            .self::jpegSegment(0xC4, "\x10".pack('C*', ...$acCounts)."\x00")
            .self::jpegSegment(0xDA, "\x03\x01\x00\x02\x00\x03\x00\x00\x3F\x00")
            .$data
            ."\xFF\xD9";
    }

    /** One JPEG marker segment (marker, length, payload). */
    protected static function jpegSegment(int $marker, string $payload): string
    {
        return "\xFF".chr($marker).pack('n', strlen($payload) + 2).$payload;
    }

    /**
     * An Exif APP1 segment as a phone writes it (little-endian): camera make, the Orientation tag
     * (when given) and a GPS position (48°51'24" N, 2°21' E) when $gps.
     */
    protected static function exifSegment(?int $orientation = 6, bool $gps = true): string
    {
        $make = "Pehouet Test Camera\x00";
        $entries = [];
        $count = 1 + ($orientation !== null ? 1 : 0) + ($gps ? 1 : 0);
        $makeOffset = 8 + 2 + 12 * $count + 4;
        $gpsOffset = $makeOffset + strlen($make);

        $entries[] = pack('vvVV', 0x010F, 2, strlen($make), $makeOffset);

        if ($orientation !== null) {
            $entries[] = pack('vvVvv', 0x0112, 3, 1, $orientation, 0);
        }

        if ($gps) {
            $entries[] = pack('vvVV', 0x8825, 4, 1, $gpsOffset);
        }

        $tiff = "II\x2A\x00".pack('V', 8).pack('v', $count).implode('', $entries).pack('V', 0).$make;

        if ($gps) {
            $rationals = $gpsOffset + 2 + 12 * 4 + 4;
            $tiff .= pack('v', 4)
                .pack('vvV', 0x0001, 2, 2)."N\x00\x00\x00"
                .pack('vvVV', 0x0002, 5, 3, $rationals)
                .pack('vvV', 0x0003, 2, 2)."E\x00\x00\x00"
                .pack('vvVV', 0x0004, 5, 3, $rationals + 24)
                .pack('V', 0)
                .pack('V*', 48, 1, 51, 1, 2400, 100)
                .pack('V*', 2, 1, 21, 1, 0, 1);
        }

        return self::jpegSegment(0xE1, "Exif\x00\x00".$tiff);
    }

    /** WebP bytes: lossless, solid #265fa5, any size up to 16384 × 16384. */
    protected static function webpBytes(int $w = 8, int $h = 6): string
    {
        $vp8l = "\x2F".pack('V', ($w - 1) | (($h - 1) << 14)).self::WEBP_SOLID_BLUE;
        $chunk = 'VP8L'.pack('V', strlen($vp8l)).$vp8l.(strlen($vp8l) % 2 ? "\x00" : '');

        return 'RIFF'.pack('V', 4 + strlen($chunk)).'WEBP'.$chunk;
    }

    protected function tearDownInteractsWithCms(): void
    {
        foreach ($this->cmsTemporaryFiles as $path) {
            @unlink($path);
        }

        $this->cmsTemporaryFiles = [];
    }

    private function cmsUpload(string $bytes, string $name, string $mime): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ap-cms-');
        file_put_contents($path, $bytes);
        $this->cmsTemporaryFiles[] = $path;

        return new UploadedFile($path, $name, $mime, null, true);
    }

    private static function bitLength(int $value): int
    {
        $bits = 0;

        while ($value > 0) {
            $bits++;
            $value >>= 1;
        }

        return $bits;
    }
}
