<?php

namespace Tests\Unit\Cms;

use App\Cms\Media\JpegSanitizer;
use Illuminate\Http\UploadedFile;

/**
 * Image files carrying the metadata the photo library must remove (docs/CMS.md §13 C8), built byte
 * by byte (no GD): GPS in Exif and XMP for every format, camera names and comments, an MPF secondary
 * image and a motion-photo video after a JPEG, data after a PNG's IEND, EXIF/XMP/unknown chunks of a
 * WebP with its VP8X flags, comment, XMP, ICC and plain-text extensions of a GIF — and for each one
 * the clean file a sanitizer must rebuild from it, byte for byte.
 *
 * Classes using this trait also use Tests\Concerns\InteractsWithCms (PNG, JPEG and WebP builders).
 */
trait MediaFixtures
{
    /** The 2-colour palette of every generated GIF: the logo's blue (#265fa5), then white. */
    private const GIF_PALETTE = "\x26\x5F\xA5\xFF\xFF\xFF";

    abstract protected static function pngBytes(int $w = 8, int $h = 6, array $rgb = [248, 212, 73]): string;

    abstract protected static function pngChunk(string $type, string $data): string;

    abstract protected static function jpegBytes(int $w = 8, int $h = 6, array $rgb = [179, 44, 43], string $segments = ''): string;

    abstract protected static function jpegSegment(int $marker, string $payload): string;

    abstract protected static function exifSegment(?int $orientation = 6, bool $gps = true): string;

    abstract protected static function webpBytes(int $w = 8, int $h = 6): string;

    abstract protected function fileWith(string $bytes, string $name, string $mime = 'application/octet-stream'): UploadedFile;

    /**
     * Bytes that must never survive a sanitizer: GPS coordinates (Exif rationals, XMP), camera
     * names, comments, MPF data and whatever was appended to the file.
     *
     * @return array<string, string> label ⇒ needle
     */
    protected static function metadataNeedles(): array
    {
        return [
            'Exif GPS latitude' => pack('V*', 48, 1, 51, 1, 2400, 100),
            'Exif GPS longitude' => pack('V*', 2, 1, 21, 1, 0, 1),
            'camera make' => 'Pehouet Test Camera',
            'XMP packet' => 'xmpmeta',
            'XMP GPS' => 'GPSLatitude',
            'motion photo' => 'MotionPhoto',
            'comment' => 'Secret comment',
            'multi-picture format' => "MPF\x00",
            'secondary image GPS' => pack('V*', 7, 1, 7, 1, 7, 1),
            'video' => 'ftypmp42',
            'appended data' => 'TRAILING-PAYLOAD',
        ];
    }

    /** An XMP packet as cameras and editors write it, with a GPS position and a motion-photo flag. */
    protected static function xmpPacket(): string
    {
        return "<?xpacket begin=\"\u{FEFF}\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?>"
            .'<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            .'<rdf:Description xmlns:exif="http://ns.adobe.com/exif/1.0/" xmlns:GCamera="http://ns.google.com/photos/1.0/camera/"'
            .' exif:GPSLatitude="48,51.4N" exif:GPSLongitude="2,21.0E" GCamera:MotionPhoto="1"/>'
            .'</rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    }

    /** A little-endian Exif (TIFF) structure: camera make, GPS position, orientation when given. */
    protected static function exifTiff(?int $orientation = null): string
    {
        return substr(self::exifSegment($orientation), 10); // after FF E1, the length and "Exif\0\0"
    }

    /**
     * A phone photo as a JPEG: Exif (camera, GPS, orientation) and XMP (GPS, motion photo) APP1s, an
     * MPF APP2 index, a comment, a JFXX thumbnail, then after the end of the image an MPF secondary
     * image with its own Exif GPS and a motion-photo MP4.
     */
    protected static function dirtyJpeg(int $w = 24, int $h = 16, ?int $orientation = 6): string
    {
        $secondaryExif = self::jpegSegment(0xE1, "Exif\x00\x00II\x2A\x00".pack('V', 8).pack('v', 1).pack('vvVV', 0x8825, 4, 1, 26).pack('V', 0)
            .pack('v', 1).pack('vvVV', 0x0002, 5, 3, 44).pack('V', 0).pack('V*', 7, 1, 7, 1, 7, 1));
        $secondary = self::jpegBytes(8, 8, [0, 0, 0], $secondaryExif.self::jpegSegment(0xE2, "MPF\x00MM\x00\x2A".pack('N', 8).pack('n', 0).pack('N', 0)));

        $jfxx = self::jpegSegment(0xE0, "JFXX\x00\x13".chr(2).chr(1).str_repeat("\xC0\xFF\xEE", 2));

        // The MP index points at the secondary image, relative to its own "MM" header: built twice,
        // the second time with the real offsets (every field has a fixed size).
        $primary = function (int $size, int $offset) use ($w, $h, $orientation, $secondary, $jfxx): string {
            $entries = pack('NNNnn', 0x20030000, $size, 0, 0, 0).pack('NNNnn', 0x00020002, strlen($secondary), $offset, 0, 0);
            $mpIndex = "MPF\x00MM\x00\x2A".pack('N', 8).pack('n', 3)
                .pack('nnN', 0xB000, 7, 4).'0100'
                .pack('nnNN', 0xB001, 4, 1, 2)
                .pack('nnNN', 0xB002, 7, 32, 50)
                .pack('N', 0).$entries;

            return self::jpegBytes($w, $h, segments: self::exifSegment($orientation)
                .self::jpegSegment(0xE1, "http://ns.adobe.com/xap/1.0/\x00".self::xmpPacket())
                .self::jpegSegment(0xE2, $mpIndex)
                .$jfxx
                .self::jpegSegment(0xFE, 'Secret comment: 48.85N 2.35E'));
        };
        $draft = $primary(0, 0);
        $header = (int) strpos($draft, "MPF\x00MM") + 4;

        return $primary(strlen($draft), strlen($draft) - $header).$secondary.self::mp4();
    }

    /** What the sanitizer must make of dirtyJpeg(): the same image with only its orientation. */
    protected static function cleanJpeg(int $w = 24, int $h = 16, ?int $orientation = 6): string
    {
        return self::jpegBytes($w, $h, segments: $orientation !== null && $orientation !== 1 ? JpegSanitizer::orientationSegment($orientation) : '');
    }

    /** A motion-photo video as phones append it: an MP4 file (ftyp + mdat boxes). */
    protected static function mp4(): string
    {
        return pack('N', 24).'ftypmp42'.pack('N', 0).'mp42isom'.pack('N', 24).'mdat'.'VIDEO-FRAMES-48.85N';
    }

    /**
     * A PNG with eXIf (GPS), iTXt XMP (GPS), tEXt/zTXt comments, tIME, an Apple iDOT chunk, then
     * after IEND some bytes and another chunk.
     */
    protected static function dirtyPng(int $w = 12, int $h = 9): string
    {
        $png = self::pngBytes($w, $h);
        $afterHeader = 8 + 25; // signature + IHDR chunk
        $iend = self::pngChunk('IEND', '');

        $before = self::pngChunk('eXIf', self::exifTiff(6))
            .self::pngChunk('iTXt', "XML:com.adobe.xmp\x00\x00\x00\x00\x00".self::xmpPacket())
            .self::pngChunk('tIME', pack('nCCCCC', 2026, 10, 3, 12, 0, 0))
            .self::pngChunk('iDOT', str_repeat("\x00", 28));
        $after = self::pngChunk('tEXt', "Comment\x00Secret comment: 48.85N 2.35E")
            .self::pngChunk('zTXt', "Author\x00\x00".gzcompress('Secret comment'));

        $dirty = substr($png, 0, $afterHeader).$before.substr($png, $afterHeader);

        return str_replace($iend, $after.$iend, $dirty).'TRAILING-PAYLOAD'.self::pngChunk('tEXt', "GPS\x0048.85N");
    }

    /** One WebP chunk (fourcc, little-endian size, data, padding byte when the size is odd). */
    protected static function webpChunk(string $fourcc, string $data): string
    {
        return $fourcc.pack('V', strlen($data)).$data.(strlen($data) % 2 ? "\x00" : '');
    }

    /** A complete WebP file around $chunks. */
    protected static function webpFileBytes(string $chunks): string
    {
        return 'RIFF'.pack('V', 4 + strlen($chunks)).'WEBP'.$chunks;
    }

    /**
     * A lossy VP8 image chunk as far as its header goes (what the sanitizer and getimagesize read):
     * the frame tag of a shown key frame, the start code, the size, then $data as the partitions.
     */
    protected static function vp8Chunk(int $w, int $h, string $data = "\x00\x00\x00\x00"): string
    {
        return self::webpChunk('VP8 ', "\x10\x00\x00\x9D\x01\x2A".pack('vv', $w, $h).$data);
    }

    /** An ANMF frame chunk: the image chunks $image placed at ($x, $y) — even numbers — sized $w × $h, shown 0.1 s. */
    protected static function anmfChunk(int $x, int $y, int $w, int $h, string $image): string
    {
        $uint24 = static fn (int $value): string => substr(pack('V', $value), 0, 3);

        return self::webpChunk('ANMF', $uint24(intdiv($x, 2)).$uint24(intdiv($y, 2)).$uint24($w - 1).$uint24($h - 1).$uint24(100)."\x00".$image);
    }

    /** A VP8X header chunk: $flags, then the canvas size. */
    protected static function vp8x(int $flags, int $w, int $h): string
    {
        return self::webpChunk('VP8X', chr($flags)."\x00\x00\x00".substr(pack('V', $w - 1), 0, 3).substr(pack('V', $h - 1), 0, 3));
    }

    /**
     * An extended WebP flagged ICC + EXIF + XMP: ICCP, the image, EXIF (GPS), XMP (GPS), an unknown
     * chunk, then bytes after the RIFF chunk.
     */
    protected static function dirtyWebp(int $w = 10, int $h = 6): string
    {
        $body = self::vp8x(0x20 | 0x08 | 0x04, $w, $h)
            .self::webpChunk('ICCP', 'icc-profile')
            .substr(self::webpBytes($w, $h), 12)
            .self::webpChunk('EXIF', self::exifTiff())
            .self::webpChunk('XMP ', self::xmpPacket())
            .self::webpChunk('JUNK', 'Secret comment: 48.85N 2.35E');

        return self::webpFileBytes($body).'TRAILING-PAYLOAD';
    }

    /** What the sanitizer must make of dirtyWebp(): VP8X flagged ICC only, ICCP, the image. */
    protected static function cleanWebp(int $w = 10, int $h = 6): string
    {
        return self::webpFileBytes(self::vp8x(0x20, $w, $h).self::webpChunk('ICCP', 'icc-profile').substr(self::webpBytes($w, $h), 12));
    }

    /**
     * GIF bytes: $frames frames of $w × $h pixels, each one colour of the 2-colour palette in turn,
     * LZW-compressed. An animation ($frames > 1) has a NETSCAPE2.0 loop extension right after the
     * palette and a graphic control extension (0.1 s) before each frame.
     */
    protected static function gifBytes(int $w = 6, int $h = 4, int $frames = 1, string $version = '89a'): string
    {
        $gif = 'GIF'.$version.pack('vvCCC', $w, $h, 0x80, 0, 0).self::GIF_PALETTE;

        if ($frames > 1) {
            $gif .= "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";
        }

        for ($frame = 0; $frame < $frames; $frame++) {
            if ($frames > 1) {
                $gif .= "\x21\xF9\x04\x04".pack('v', 10)."\x00\x00";
            }

            $gif .= "\x2C".pack('vvvvC', 0, 0, $w, $h, 0)."\x02".self::gifSubBlocks(self::lzw(array_fill(0, $w * $h, $frame % 2), 2));
        }

        return $gif."\x3B";
    }

    /**
     * An animated GIF (2 frames) wrapped in metadata: a comment, an XMP application extension (Adobe's
     * raw packet + magic trailer), an ICC profile extension, a plain-text extension with its own
     * graphic control, a loop extension with an extra buffering sub-block, a comment after the frames
     * and bytes after the trailer. The sanitizer must give back gifBytes($w, $h, 2).
     */
    protected static function dirtyGif(int $w = 6, int $h = 4): string
    {
        $clean = self::gifBytes($w, $h, 2);
        $head = 13 + strlen(self::GIF_PALETTE);
        $loop = "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";
        $frames = substr($clean, $head + strlen($loop), -1);

        $magicTrailer = "\x01".implode('', array_map('chr', range(255, 0)))."\x00";
        $metadata = "\x21\xFE".self::gifSubBlocks('Secret comment: 48.85N 2.35E')
            ."\x21\xFF\x0BXMP DataXMP".self::xmpPacket().$magicTrailer
            ."\x21\xFF\x0BICCRGBG1012".self::gifSubBlocks('icc-profile')
            ."\x21\xF9\x04\x00\x0A\x00\x00\x00"
            ."\x21\x01\x0C".pack('vvvvCCCC', 0, 0, $w, $h, 8, 8, 1, 0).self::gifSubBlocks('Secret comment');

        return substr($clean, 0, $head).$metadata
            ."\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x05\x02\x00\x10\x00\x00\x00"
            .$frames
            ."\x21\xFE".self::gifSubBlocks('Secret comment, after the frames')
            ."\x3B".'TRAILING-PAYLOAD'."\x21\xFE".self::gifSubBlocks('GPS 48.85N');
    }

    /** An animated GIF upload (what the uploader sends untouched). */
    protected function gifFile(string $name = 'animation.gif', int $w = 6, int $h = 4, int $frames = 2): UploadedFile
    {
        return $this->fileWith(self::gifBytes($w, $h, $frames), $name, 'image/gif');
    }

    /** Data as GIF sub-blocks (≤ 255 bytes each) with their terminator. */
    protected static function gifSubBlocks(string $data): string
    {
        $blocks = '';

        foreach (str_split($data, 255) as $block) {
            $blocks .= $block === '' ? '' : chr(strlen($block)).$block;
        }

        return $blocks."\x00";
    }

    /**
     * GIF's variable-width LZW (codes written least significant bit first), as browsers decode it:
     * the code width grows when the next code to assign needs one more bit, a clear code restarts a
     * full table.
     *
     * @param  list<int>  $pixels  colour indexes
     */
    protected static function lzw(array $pixels, int $minCodeSize): string
    {
        $clear = 1 << $minCodeSize;
        $end = $clear + 1;
        $next = $end + 1;
        $width = $minCodeSize + 1;
        $table = [];
        $bits = 0;
        $count = 0;
        $data = '';

        $write = function (int $code) use (&$bits, &$count, &$data, &$width): void {
            $bits |= $code << $count;
            $count += $width;

            while ($count >= 8) {
                $data .= chr($bits & 0xFF);
                $bits >>= 8;
                $count -= 8;
            }
        };
        $code = function (string $string) use (&$table): int {
            return strlen($string) === 1 ? ord($string) : $table[$string];
        };
        $grow = function () use (&$next, &$width): void {
            if ($next >= (1 << $width) && $width < 12) {
                $width++;
            }
        };

        $write($clear);
        $prefix = null;

        foreach ($pixels as $pixel) {
            $char = chr($pixel);

            if ($prefix === null || isset($table[$prefix.$char])) {
                $prefix = ($prefix ?? '').$char;

                continue;
            }

            $write($code($prefix));
            $grow();

            if ($next < 4096) {
                $table[$prefix.$char] = $next++;
            } else {
                $write($clear);
                [$table, $next, $width] = [[], $end + 1, $minCodeSize + 1];
            }

            $prefix = $char;
        }

        if ($prefix !== null) {
            $write($code($prefix));
            $grow();
        }

        $write($end);

        return $count > 0 ? $data.chr($bits & 0xFF) : $data;
    }
}
