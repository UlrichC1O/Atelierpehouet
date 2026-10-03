<?php

namespace Tests\Unit\Cms;

use App\Cms\Media\WebpSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/** WebP rebuilt from an allow-list of its chunks (docs/CMS.md §4.5, §13 C8), on real byte streams. */
class WebpSanitizerTest extends TestCase
{
    use InteractsWithCms, MediaFixtures;

    public function test_exif_xmp_unknown_chunks_and_appended_data_are_removed_and_the_flags_fixed(): void
    {
        $dirty = self::dirtyWebp(10, 6);
        $this->assertSame(0x20 | 0x08 | 0x04, ord($dirty[20]), 'flagged ICC + EXIF + XMP');

        $clean = WebpSanitizer::strip($dirty);

        $this->assertSame(self::cleanWebp(10, 6), $clean);
        foreach (self::metadataNeedles() as $label => $needle) {
            $this->assertStringNotContainsString($needle, $clean, $label);
        }
        foreach (['EXIF', 'XMP ', 'JUNK'] as $chunk) {
            $this->assertStringNotContainsString($chunk, $clean);
        }
        $this->assertSame(0x20, ord($clean[20]), 'only the ICC flag stays set');
        $this->assertSame(strlen($clean) - 8, unpack('V', $clean, 4)[1], 'RIFF size of what is left');
        $this->assertSame([10, 6, IMAGETYPE_WEBP], array_slice(getimagesizefromstring($clean), 0, 3));
        $this->assertSame($clean, WebpSanitizer::strip($clean), 'a rebuilt file is stable');
    }

    public function test_without_an_icc_profile_no_flag_stays(): void
    {
        $image = substr(self::webpBytes(7, 5), 12);
        $dirty = self::webpFileBytes(self::vp8x(0x20 | 0x08, 7, 5).$image.self::webpChunk('EXIF', self::exifTiff(6)));

        $clean = WebpSanitizer::strip($dirty);

        $this->assertSame(self::webpFileBytes(self::vp8x(0, 7, 5).$image), $clean);
        $this->assertSame([7, 5], array_slice(getimagesizefromstring($clean), 0, 2));
    }

    public function test_the_alpha_flag_and_chunk_are_kept(): void
    {
        $image = substr(self::webpBytes(4, 4), 12);
        $alpha = self::webpChunk('ALPH', "\x00".str_repeat("\xFF", 16));

        $clean = WebpSanitizer::strip(self::webpFileBytes(self::vp8x(0x10 | 0x04, 4, 4).$alpha.$image.self::webpChunk('XMP ', self::xmpPacket())));

        $this->assertSame(self::webpFileBytes(self::vp8x(0x10, 4, 4).$alpha.$image), $clean);
    }

    public function test_an_animation_keeps_its_frames_without_their_metadata(): void
    {
        $image = substr(self::webpBytes(6, 4), 12);
        $anim = self::webpChunk('ANIM', "\x00\x00\x00\x00".pack('v', 0));
        $header = substr(pack('V', 0), 0, 3).substr(pack('V', 0), 0, 3).substr(pack('V', 5), 0, 3).substr(pack('V', 3), 0, 3).substr(pack('V', 100), 0, 3)."\x00";
        $frame = fn (string $extra): string => self::webpChunk('ANMF', $header.$image.$extra);
        $dirty = self::vp8x(0x02 | 0x08 | 0x04, 6, 4)
            .self::webpChunk('EXIF', self::exifTiff())
            .$anim
            .$frame(self::webpChunk('JUNK', 'Secret comment'))
            .$frame('')
            .self::webpChunk('XMP ', self::xmpPacket());

        $clean = WebpSanitizer::strip(self::webpFileBytes($dirty).'TRAILING-PAYLOAD');

        $this->assertSame(self::webpFileBytes(self::vp8x(0x02, 6, 4).$anim.$frame('').$frame('')), $clean);
        foreach (self::metadataNeedles() as $label => $needle) {
            $this->assertStringNotContainsString($needle, $clean, $label);
        }
        $this->assertSame([6, 4], array_slice(getimagesizefromstring($clean), 0, 2));
    }

    public function test_a_simple_webp_keeps_its_image_chunk_only(): void
    {
        $webp = self::webpBytes(4, 4);

        $this->assertSame($webp, WebpSanitizer::strip($webp));
        $this->assertSame($webp, WebpSanitizer::strip($webp.'TRAILING-PAYLOAD'));
        // Chunks after the image of a simple file are not part of the format: dropped.
        $this->assertSame($webp, WebpSanitizer::strip(self::webpFileBytes(substr($webp, 12).self::webpChunk('EXIF', self::exifTiff()))));
    }

    /** @return array<string, array{string}> */
    public static function malformed(): array
    {
        $image = substr(self::webpBytes(4, 4), 12);

        return [
            'not a webp' => ['RIFF'.pack('V', 12).'WAVEfmt '.pack('V', 0)],
            'riff larger than the file' => ['RIFF'.pack('V', 400).'WEBP'.$image],
            'chunk larger than the riff' => ['RIFF'.pack('V', 40).'WEBPVP8L'.pack('V', 999).str_repeat("\x00", 28)],
            'cut chunk header' => [self::webpFileBytes($image.'EXI')],
            'unknown first chunk' => [self::webpFileBytes(self::webpChunk('JUNK', 'xx').$image)],
            'vp8x without an image' => [self::webpFileBytes(self::vp8x(0x08, 4, 4).self::webpChunk('EXIF', 'exif'))],
            'vp8x of a wrong size' => [self::webpFileBytes(self::webpChunk('VP8X', str_repeat("\x00", 6)).$image)],
            'animation without frames' => [self::webpFileBytes(self::vp8x(0x02, 4, 4).self::webpChunk('ANIM', str_repeat("\x00", 6)))],
            'frame header too short' => [self::webpFileBytes(self::vp8x(0x02, 4, 4).self::webpChunk('ANIM', str_repeat("\x00", 6)).self::webpChunk('ANMF', str_repeat("\x00", 10)))],
            'frame without an image' => [self::webpFileBytes(self::vp8x(0x02, 4, 4).self::webpChunk('ANIM', str_repeat("\x00", 6)).self::webpChunk('ANMF', str_repeat("\x00", 16).self::webpChunk('ALPH', 'aa')))],
        ];
    }

    #[DataProvider('malformed')]
    public function test_malformed_streams_are_refused(string $bytes): void
    {
        $this->expectException(InvalidArgumentException::class);

        WebpSanitizer::strip($bytes);
    }
}
