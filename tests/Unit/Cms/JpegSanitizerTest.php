<?php

namespace Tests\Unit\Cms;

use App\Cms\Media\ExifOrientation;
use App\Cms\Media\JpegSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/** JPEG rebuilt from an allow-list of its segments (docs/CMS.md §4.5, §13 C8), on real byte streams. */
class JpegSanitizerTest extends TestCase
{
    use InteractsWithCms, MediaFixtures;

    private const GPS_LATITUDE = [48, 1, 51, 1, 2400, 100];

    public function test_gps_and_camera_data_are_removed_but_the_orientation_is_kept(): void
    {
        $original = self::jpegBytes(40, 30, segments: self::exifSegment(6));
        $this->assertStringContainsString('Pehouet Test Camera', $original);
        $this->assertStringContainsString(pack('V*', ...self::GPS_LATITUDE), $original);

        $clean = JpegSanitizer::strip($original);

        $this->assertStringNotContainsString('Pehouet Test Camera', $clean);
        $this->assertStringNotContainsString(pack('V*', ...self::GPS_LATITUDE), $clean);
        $this->assertSame(6, JpegSanitizer::orientation($clean));
        $this->assertSame([40, 30], array_slice(getimagesizefromstring($clean), 0, 2));
        // The minimal segment holds the Orientation tag only, right after the JFIF header.
        $this->assertSame(1, substr_count($clean, "Exif\x00\x00"));
        $this->assertSame(2 + 18, strpos($clean, "\xFF\xE1"));
        $this->assertStringContainsString(JpegSanitizer::orientationSegment(6), $clean);
    }

    public function test_the_image_data_is_kept_byte_for_byte(): void
    {
        $plain = self::jpegBytes(24, 16);
        $withMetadata = self::jpegBytes(24, 16, segments: self::exifSegment(null).self::jpegSegment(0xFE, 'Shot at 48.85N 2.35E'));

        $this->assertSame($plain, JpegSanitizer::strip($withMetadata));
        $this->assertSame($plain, JpegSanitizer::strip($plain));
    }

    public function test_an_upright_orientation_is_not_rewritten(): void
    {
        $clean = JpegSanitizer::strip(self::jpegBytes(segments: self::exifSegment(1)));

        $this->assertStringNotContainsString('Exif', $clean);
        $this->assertNull(JpegSanitizer::orientation($clean));
    }

    public function test_xmp_iptc_comments_and_foreign_app_segments_are_dropped_icc_and_adobe_kept(): void
    {
        $icc = self::jpegSegment(0xE2, "ICC_PROFILE\x00\x01\x01profile-bytes");
        $adobe = self::jpegSegment(0xEE, "Adobe\x00\x64\x00\x00\x00\x00\x01");
        $dropped = [
            self::jpegSegment(0xE1, "http://ns.adobe.com/xap/1.0/\x00<x:xmpmeta>GPSLatitude 48,51N</x:xmpmeta>"),
            self::jpegSegment(0xED, "Photoshop 3.0\x008BIM IPTC city=Paris"),
            self::jpegSegment(0xFE, 'Comment with a secret'),
            self::jpegSegment(0xE2, "MPF\x00multi-picture offsets"),
            self::jpegSegment(0xEC, 'Ducky quality'),
        ];

        $clean = JpegSanitizer::strip(self::jpegBytes(segments: $icc.implode('', $dropped).$adobe));

        $this->assertStringContainsString($icc, $clean);
        $this->assertStringContainsString($adobe, $clean);
        foreach ($dropped as $segment) {
            $this->assertStringNotContainsString($segment, $clean);
        }
        $this->assertStringStartsWith("\xFF\xD8\xFF\xE0", $clean);
    }

    public function test_data_after_the_end_of_image_is_cut(): void
    {
        $jpeg = self::jpegBytes(16, 16);

        $this->assertSame($jpeg, JpegSanitizer::strip($jpeg.'ftypmp42 motion photo video'));
    }

    public function test_big_endian_exif_is_read_too(): void
    {
        $tiff = ExifOrientation::tiff(8);

        $this->assertSame(8, ExifOrientation::read($tiff));
        $this->assertSame(8, JpegSanitizer::orientation(JpegSanitizer::strip(self::jpegBytes(segments: JpegSanitizer::orientationSegment(8)))));
        $this->assertTrue(ExifOrientation::swapsDimensions(8));
        $this->assertFalse(ExifOrientation::swapsDimensions(3));
        $this->assertNull(ExifOrientation::read('MM'.str_repeat("\x00", 10)));
    }

    public function test_a_phone_photo_loses_its_secondary_image_its_video_and_every_metadata(): void
    {
        $dirty = self::dirtyJpeg(24, 16, 6);
        $this->assertSame(2, substr_count($dirty, "\xFF\xD8"), 'an MPF secondary image follows the photo');

        $clean = JpegSanitizer::strip($dirty);

        $this->assertSame(self::cleanJpeg(24, 16, 6), $clean);
        foreach (self::metadataNeedles() as $label => $needle) {
            $this->assertStringNotContainsString($needle, $clean, $label);
        }
        $this->assertSame(1, substr_count($clean, "\xFF\xD8"));
        $this->assertStringNotContainsString('JFXX', $clean);
        $this->assertSame(6, JpegSanitizer::orientation($clean));
        $this->assertSame([24, 16, IMAGETYPE_JPEG], array_slice(getimagesizefromstring($clean), 0, 3));
        $this->assertSame($clean, JpegSanitizer::strip($clean), 'a rebuilt file is stable');
    }

    public function test_the_jfif_thumbnail_is_dropped_and_the_adobe_segment_trimmed(): void
    {
        $plain = self::jpegBytes(16, 8);
        $jfif = self::jpegSegment(0xE0, "JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00");
        $withThumbnail = self::jpegSegment(0xE0, "JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x01\x01\xC0\xFF\xEE");
        $adobe = "Adobe\x00\x64\x00\x00\x00\x00\x01";

        $this->assertSame($plain, JpegSanitizer::strip(str_replace($jfif, $withThumbnail, $plain)));
        $this->assertSame(
            self::jpegBytes(16, 8, segments: self::jpegSegment(0xEE, $adobe)),
            JpegSanitizer::strip(self::jpegBytes(16, 8, segments: self::jpegSegment(0xEE, $adobe.'Secret comment'))),
        );
        // No JFIF header at all (Exif-first files): the orientation goes right after SOI.
        $exifFirst = "\xFF\xD8".self::exifSegment(8).substr(str_replace($jfif, '', $plain), 2);
        $this->assertStringStartsWith("\xFF\xD8".JpegSanitizer::orientationSegment(8)."\xFF\xDB", JpegSanitizer::strip($exifFirst));
    }

    public function test_restart_markers_and_fill_bytes_inside_the_scan_are_kept(): void
    {
        $plain = self::jpegBytes(16, 16);
        $sos = strpos($plain, "\xFF\xDA");
        $withRestart = substr($plain, 0, $sos).self::jpegSegment(0xDD, pack('n', 1)).substr($plain, $sos);
        $data = strpos($withRestart, "\xFF\xDA") + 14;
        // An RST0 marker and a stuffed 0xFF00 in the entropy-coded data are not segment boundaries.
        $scan = substr($withRestart, 0, $data + 2)."\xFF\xD0".substr($withRestart, $data + 2, -2)."\xFF\x00\xFF\xD9";

        $this->assertSame($scan, JpegSanitizer::strip($scan));
    }

    /** @return array<string, array{string}> */
    public static function corrupt(): array
    {
        $jpeg = self::jpegBytes(16, 8);
        $sof = strpos($jpeg, "\xFF\xC0");
        $sos = strpos($jpeg, "\xFF\xDA");
        $frame = substr($jpeg, $sof, 19);

        return [
            'not a jpeg' => ["\x89PNG\r\n\x1A\n"],
            'truncated segment' => ["\xFF\xD8\xFF\xE0\x00\x10JFIF"],
            'garbage between segments' => ["\xFF\xD8\x00\x00\x00\x00"],
            'no end of image' => [substr($jpeg, 0, -2)],
            'cut in the middle of the scan' => [substr($jpeg, 0, $sos + 16)],
            'no frame' => [str_replace($frame, '', $jpeg)],
            'scan before the frame' => [str_replace($frame, '', substr($jpeg, 0, -2)).$frame."\xFF\xD9"],
            'two frames' => [str_replace($frame, $frame.$frame, $jpeg)],
            'lossless frame' => [str_replace($frame, "\xFF\xC3".substr($frame, 2), $jpeg)],
            '12-bit samples' => [str_replace($frame, substr($frame, 0, 4)."\x0C".substr($frame, 5), $jpeg)],
            'height left to a DNL marker' => [str_replace($frame, substr($frame, 0, 5)."\x00\x00".substr($frame, 7), $jpeg)],
            'second start of image' => ["\xFF\xD8\xFF\xD8".substr($jpeg, 2)],
        ];
    }

    #[DataProvider('corrupt')]
    public function test_malformed_streams_are_refused(string $bytes): void
    {
        $this->expectException(InvalidArgumentException::class);

        JpegSanitizer::strip($bytes);
    }
}
