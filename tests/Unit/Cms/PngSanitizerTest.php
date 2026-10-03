<?php

namespace Tests\Unit\Cms;

use App\Cms\Media\PngSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/** PNG rebuilt from an allow-list of its chunks (docs/CMS.md §4.5, §13 C8), on real byte streams. */
class PngSanitizerTest extends TestCase
{
    use InteractsWithCms, MediaFixtures;

    public function test_gps_xmp_text_and_data_after_iend_are_removed(): void
    {
        $dirty = self::dirtyPng(12, 9);
        foreach (['eXIf', 'iTXt', 'tEXt', 'zTXt', 'tIME', 'iDOT', 'TRAILING-PAYLOAD'] as $needle) {
            $this->assertStringContainsString($needle, $dirty);
        }

        $clean = PngSanitizer::strip($dirty);

        $this->assertSame(self::pngBytes(12, 9), $clean);
        foreach (self::metadataNeedles() as $label => $needle) {
            $this->assertStringNotContainsString($needle, $clean, $label);
        }
        foreach (['eXIf', 'iTXt', 'tEXt', 'zTXt', 'tIME', 'iDOT'] as $chunk) {
            $this->assertStringNotContainsString($chunk, $clean);
        }
        $this->assertSame([12, 9, IMAGETYPE_PNG], array_slice(getimagesizefromstring($clean), 0, 3));
        $this->assertStringEndsWith(self::pngChunk('IEND', ''), $clean);
        $this->assertSame($clean, PngSanitizer::strip($clean), 'a rebuilt file is stable');
    }

    public function test_colour_transparency_and_animation_chunks_are_kept_in_order(): void
    {
        $png = self::pngBytes(4, 4);
        $header = substr($png, 0, 8 + 25);
        $rest = substr($png, 8 + 25);
        $before = self::pngChunk('gAMA', pack('N', 45455))
            .self::pngChunk('cHRM', str_repeat(pack('N', 31270), 8))
            .self::pngChunk('sRGB', "\x00")
            .self::pngChunk('iCCP', "sRGB\x00\x00".(string) gzcompress('icc-profile'))
            .self::pngChunk('sBIT', "\x08\x08\x08")
            .self::pngChunk('cICP', "\x01\x0D\x00\x01")
            .self::pngChunk('pHYs', pack('NNC', 2835, 2835, 1))
            .self::pngChunk('tRNS', pack('nnn', 0, 0, 0))
            .self::pngChunk('bKGD', pack('nnn', 255, 255, 255))
            .self::pngChunk('acTL', pack('NN', 1, 0))
            .self::pngChunk('fcTL', pack('NNNNNnnCC', 0, 4, 4, 0, 0, 1, 10, 0, 0));
        $kept = $header.$before.$rest;
        $dropped = self::pngChunk('hIST', str_repeat("\x00\x01", 4))
            .self::pngChunk('sPLT', "Palette\x00\x08".str_repeat("\x00", 6))
            .self::pngChunk('prVt', 'Secret comment')
            .self::pngChunk('caBX', 'TRAILING-PAYLOAD'); // C2PA manifests and other private chunks

        $clean = PngSanitizer::strip($header.$dropped.$before.$rest);

        $this->assertSame($kept, $clean);
        $this->assertSame([4, 4], array_slice(getimagesizefromstring($clean), 0, 2));
    }

    public function test_an_apng_keeps_its_frames(): void
    {
        $png = self::pngBytes(4, 4);
        $iend = self::pngChunk('IEND', '');
        $frame = self::pngChunk('fcTL', pack('NNNNNnnCC', 1, 4, 4, 0, 0, 1, 10, 0, 0))
            .self::pngChunk('fdAT', pack('N', 2).(string) gzcompress(str_repeat("\x00".str_repeat("\x26\x5F\xA5", 4), 4)));
        $apng = str_replace($iend, $frame.self::pngChunk('tEXt', "Comment\x00Secret comment").$iend, $png);

        $this->assertSame(str_replace($iend, $frame.$iend, $png), PngSanitizer::strip($apng));
    }

    public function test_an_animation_is_told_by_an_actl_chunk_before_the_image_data(): void
    {
        $png = self::pngBytes(4, 4);
        $actl = self::pngChunk('acTL', pack('NN', 1, 0));
        $iend = self::pngChunk('IEND', '');

        $this->assertFalse(PngSanitizer::animated($png));
        $this->assertTrue(PngSanitizer::animated(PngSanitizer::strip(substr($png, 0, 8 + 25).$actl.substr($png, 8 + 25))));
        // After the image data, an acTL chunk animates nothing (APNG decoders ignore it).
        $this->assertFalse(PngSanitizer::animated(str_replace($iend, $actl.$iend, $png)));
    }

    public function test_a_clean_png_is_unchanged(): void
    {
        foreach ([self::pngBytes(3, 2), self::pngBytes(300, 200, [38, 95, 165])] as $png) {
            $this->assertSame($png, PngSanitizer::strip($png));
        }
    }

    public function test_a_dropped_chunk_with_a_wrong_crc_does_not_matter(): void
    {
        $png = self::pngBytes(5, 5);
        $iend = self::pngChunk('IEND', '');
        $broken = substr(self::pngChunk('tEXt', "GPS\x0048.85N"), 0, -4).'XXXX';

        $this->assertSame($png, PngSanitizer::strip(str_replace($iend, $broken.$iend, $png)));
    }

    /** @return array<string, array{string}> */
    public static function malformed(): array
    {
        $png = self::pngBytes(6, 4);
        $signature = substr($png, 0, 8);
        $ihdr = substr($png, 8, 25);
        $idat = substr($png, 33, -12);
        $iend = self::pngChunk('IEND', '');

        return [
            'not a png' => ['GIF89a'.str_repeat("\x00", 20)],
            'chunk longer than the file' => [$signature."\x00\x00\x00\xFFIHDR"],
            'bad chunk type' => [$signature."\x00\x00\x00\x0D1HDR".str_repeat("\x00", 17)],
            'ihdr not first' => [$signature.self::pngChunk('gAMA', pack('N', 45455)).$ihdr.$idat.$iend],
            'ihdr of a wrong size' => [$signature.self::pngChunk('IHDR', substr($ihdr, 8, 12)).$idat.$iend],
            'kept chunk with a wrong crc' => [$signature.$ihdr.substr($idat, 0, -4).'XXXX'.$iend],
            'no idat' => [$signature.$ihdr.$iend],
            'no iend' => [$signature.$ihdr.$idat],
            'cut inside a chunk' => [substr($png, 0, 40)],
        ];
    }

    #[DataProvider('malformed')]
    public function test_malformed_streams_are_refused(string $bytes): void
    {
        $this->expectException(InvalidArgumentException::class);

        PngSanitizer::strip($bytes);
    }
}
