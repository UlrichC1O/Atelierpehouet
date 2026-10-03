<?php

namespace Tests\Unit\Cms;

use App\Cms\Media\GifSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/** GIF rebuilt from an allow-list of its blocks (docs/CMS.md §13 C8), on real byte streams. */
class GifSanitizerTest extends TestCase
{
    use InteractsWithCms, MediaFixtures;

    public function test_comments_xmp_icc_and_plain_text_are_dropped_and_the_animation_kept(): void
    {
        $dirty = self::dirtyGif(6, 4);
        $this->assertStringContainsString('XMP DataXMP', $dirty);
        $this->assertStringContainsString('Secret comment', $dirty);

        $clean = GifSanitizer::strip($dirty);

        $this->assertSame(self::gifBytes(6, 4, 2), $clean);
        foreach (self::metadataNeedles() as $label => $needle) {
            $this->assertStringNotContainsString($needle, $clean, $label);
        }
        foreach (['XMP Data', 'ICCRGBG1', "\x21\xFE", "\x21\x01"] as $block) {
            $this->assertStringNotContainsString($block, $clean);
        }
        $this->assertSame(1, substr_count($clean, 'NETSCAPE2.0'), 'the loop count stays');
        $this->assertSame(2, substr_count($clean, "\x21\xF9\x04"), 'one graphic control per frame, none for the dropped text');
        $this->assertSame([6, 4, IMAGETYPE_GIF], array_slice(getimagesizefromstring($clean), 0, 3));
        $this->assertStringEndsWith("\x3B", $clean);
    }

    public function test_a_clean_gif_is_unchanged(): void
    {
        foreach ([self::gifBytes(40, 30, 1, '87a'), self::gifBytes(5, 3), self::gifBytes(300, 200, 3)] as $gif) {
            $this->assertSame($gif, GifSanitizer::strip($gif));
        }
    }

    public function test_the_loop_extension_is_reduced_to_its_loop_count(): void
    {
        $gif = self::gifBytes(4, 4, 2);
        $loop = "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";
        $animexts = "\x21\xFF\x0BANIMEXTS1.0\x03\x01\x05\x00\x00";
        $noisy = "\x21\xFF\x0BNETSCAPE2.0\x05\x02\x00\x10\x00\x00\x03\x01\x05\x00\x08GPS48.85\x00";

        $this->assertSame(
            str_replace($loop, "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x05\x00\x00", $gif),
            GifSanitizer::strip(str_replace($loop, $noisy, $gif)),
        );
        $this->assertSame(str_replace($loop, $animexts, $gif), GifSanitizer::strip(str_replace($loop, $animexts, $gif)));
        // A loop extension without a loop sub-block says nothing: dropped.
        $this->assertSame(str_replace($loop, '', $gif), GifSanitizer::strip(str_replace($loop, "\x21\xFF\x0BNETSCAPE2.0\x05\x02\x00\x10\x00\x00\x00", $gif)));
    }

    public function test_a_missing_trailer_is_restored_and_data_after_it_cut(): void
    {
        $gif = self::gifBytes(4, 3);

        $this->assertSame($gif, GifSanitizer::strip(substr($gif, 0, -1)));
        $this->assertSame($gif, GifSanitizer::strip($gif.'TRAILING-PAYLOAD'.self::mp4()));
    }

    public function test_a_graphic_control_extension_is_rebuilt_to_its_four_bytes(): void
    {
        $gif = self::gifBytes(4, 3);
        $image = strpos($gif, "\x2C");
        $control = "\x21\xF9\x06\x04\x0A\x00\x00GP\x02SS\x00";

        $clean = GifSanitizer::strip(substr($gif, 0, $image).$control.substr($gif, $image));

        $this->assertSame(substr($gif, 0, $image)."\x21\xF9\x04\x04\x0A\x00\x00\x00".substr($gif, $image), $clean);
    }

    /** @return array<string, array{string}> */
    public static function malformed(): array
    {
        $gif = self::gifBytes(6, 4);
        $image = strpos($gif, "\x2C");

        return [
            'not a gif' => ["\x89PNG\r\n\x1A\n".str_repeat("\x00", 20)],
            'truncated header' => ['GIF89a'.pack('v', 6)],
            'truncated colour table' => [substr($gif, 0, 15)],
            'truncated image data' => [substr($gif, 0, -4)],
            'no image' => [substr($gif, 0, $image).';'],
            'unknown block' => [substr($gif, 0, $image)."\x99".substr($gif, $image)],
            'frame outside the screen' => [substr($gif, 0, $image + 5).pack('v', 7).substr($gif, $image + 7)],
            'empty frame' => [substr($gif, 0, $image + 5).pack('v', 0).substr($gif, $image + 7)],
            'bad LZW code size' => [substr($gif, 0, $image + 10)."\x0C".substr($gif, $image + 11)],
            'unterminated extension' => [substr($gif, 0, $image)."\x21\xFE\x10Secret comment"],
        ];
    }

    #[DataProvider('malformed')]
    public function test_malformed_streams_are_refused(string $bytes): void
    {
        $this->expectException(InvalidArgumentException::class);

        GifSanitizer::strip($bytes);
    }
}
