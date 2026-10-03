<?php

namespace App\Cms\Media;

use InvalidArgumentException;

/**
 * Rebuilds a WebP from an allow-list of its chunks (docs/CMS.md §13 C8). A simple file keeps its one
 * image chunk (VP8 or VP8L). An extended file keeps VP8X, ICCP and either its still image (ALPH,
 * VP8, VP8L) or its animation (ANIM, ANMF frames reduced to their ALPH/VP8/VP8L chunks); EXIF (GPS,
 * camera…), XMP and unknown chunks are dropped, the VP8X flags are rewritten to match what is left,
 * the RIFF size is recomputed and anything after the RIFF chunk is cut. Phone uploads normally arrive
 * re-encoded by the admin's uploader (no metadata); this covers WebP files sent as they are.
 *
 * Files browsers refuse to decode (libwebp's demuxer) are refused too, instead of being stored as a
 * broken image: an image chunk whose header is unreadable, a still image whose size differs from the
 * canvas, a frame reaching outside the canvas (it would also escape the pixel limit, which is checked
 * on the canvas), two images in one still or frame, an ALPH chunk before a lossless (VP8L) image.
 */
final class WebpSanitizer
{
    /** VP8X flag bits kept as they are: alpha channel, animation. ICC is set from the chunks kept. */
    private const FLAG_ALPHA = 0x10;

    private const FLAG_ANIMATION = 0x02;

    private const FLAG_ICC = 0x20;

    /** Chunks an extended (VP8X) file keeps, in their order: a still image, an animation. */
    private const STILL = ['ICCP', 'ALPH', 'VP8 ', 'VP8L'];

    private const ANIMATED = ['ICCP', 'ANIM', 'ANMF'];

    /** Chunks an animation frame (ANMF) keeps after its 16-byte header. */
    private const FRAME = ['ALPH', 'VP8 ', 'VP8L'];

    /**
     * @throws InvalidArgumentException when the stream is not a well-formed WebP
     */
    public static function strip(string $bytes): string
    {
        $length = strlen($bytes);

        if ($length < 20 || ! str_starts_with($bytes, 'RIFF') || substr($bytes, 8, 4) !== 'WEBP') {
            throw new InvalidArgumentException('Not a WebP stream.');
        }

        $riff = unpack('V', $bytes, 4)[1];

        if ($riff < 12 || $riff > $length - 8) {
            throw new InvalidArgumentException('Truncated WebP: the RIFF chunk is larger than the file.');
        }

        $chunks = self::chunks($bytes, 12, 8 + $riff);
        [$first, $header] = $chunks[0];

        if ($first === 'VP8 ' || $first === 'VP8L') {
            self::size($first, $header); // a simple file: its image chunk only
            $body = self::chunk($first, $header);
        } elseif ($first === 'VP8X' && strlen($header) === 10) {
            $body = self::extended($header, array_slice($chunks, 1));
        } else {
            throw new InvalidArgumentException('Corrupt WebP: no image chunk.');
        }

        return 'RIFF'.pack('V', 4 + strlen($body)).'WEBP'.$body;
    }

    /** Whether a WebP rebuilt by strip() is an animation (VP8X with the animation flag). */
    public static function animated(string $webp): bool
    {
        return substr($webp, 12, 4) === 'VP8X' && strlen($webp) > 20 && (ord($webp[20]) & self::FLAG_ANIMATION) !== 0;
    }

    /**
     * VP8X with its flags rewritten, then the kept chunks.
     *
     * @param  list<array{string, string}>  $chunks
     */
    private static function extended(string $header, array $chunks): string
    {
        $flags = ord($header[0]);
        $animated = ($flags & self::FLAG_ANIMATION) !== 0;
        $canvas = [self::uint24($header, 4) + 1, self::uint24($header, 7) + 1];
        $kept = '';
        $found = [];
        $still = [];

        foreach ($chunks as [$fourcc, $data]) {
            if (! in_array($fourcc, $animated ? self::ANIMATED : self::STILL, true)) {
                continue;
            }

            if ($fourcc === 'ANMF') {
                $data = self::frame($data, $canvas);
            } elseif ($fourcc !== 'ICCP') {
                $still[] = [$fourcc, $data];
            }

            $kept .= self::chunk($fourcc, $data);
            $found[$fourcc] = true;
        }

        if ($animated && ! isset($found['ANIM'], $found['ANMF'])) {
            throw new InvalidArgumentException('Corrupt WebP: no image in the extended file.');
        }

        if (! $animated && self::image($still) !== $canvas) {
            throw new InvalidArgumentException('Corrupt WebP: the image is not the size of the canvas.');
        }

        $flags = ($flags & (self::FLAG_ALPHA | self::FLAG_ANIMATION)) | (isset($found['ICCP']) ? self::FLAG_ICC : 0);

        // Flags, 3 reserved bytes, canvas width − 1 and height − 1 (24 bits each).
        return self::chunk('VP8X', chr($flags)."\x00\x00\x00".substr($header, 4, 6)).$kept;
    }

    /**
     * An animation frame: its header (position, size, duration, flags) and its image chunks only.
     * The image, placed at the frame's offset, must fit in the canvas.
     *
     * @param  array{int, int}  $canvas
     */
    private static function frame(string $data, array $canvas): string
    {
        if (strlen($data) < 16) {
            throw new InvalidArgumentException('Corrupt WebP: animation frame too short.');
        }

        $kept = '';
        $image = [];

        foreach (self::chunks($data, 16, strlen($data)) as [$fourcc, $chunk]) {
            if (in_array($fourcc, self::FRAME, true)) {
                $kept .= self::chunk($fourcc, $chunk);
                $image[] = [$fourcc, $chunk];
            }
        }

        [$width, $height] = self::image($image);

        if (2 * self::uint24($data, 0) + $width > $canvas[0] || 2 * self::uint24($data, 3) + $height > $canvas[1]) {
            throw new InvalidArgumentException('Corrupt WebP: animation frame outside the canvas.');
        }

        return substr($data, 0, 16).$kept;
    }

    /**
     * Size of the one image of a still or a frame: its chunks are an optional ALPH, then a VP8 (a
     * lossless VP8L carries its own alpha).
     *
     * @param  list<array{string, string}>  $chunks  ALPH, VP8 and VP8L chunks, in their order
     * @return array{int, int}
     */
    private static function image(array $chunks): array
    {
        $fourccs = array_column($chunks, 0);

        if (! in_array($fourccs, [['VP8 '], ['VP8L'], ['ALPH', 'VP8 ']], true)) {
            throw new InvalidArgumentException('Corrupt WebP: not one image (optionally with its alpha) per still or frame.');
        }

        return self::size(...end($chunks));
    }

    /**
     * Width and height written in the header of a VP8 (lossy) or VP8L (lossless) bitstream, read as
     * libwebp does.
     *
     * @return array{int, int}
     */
    private static function size(string $fourcc, string $data): array
    {
        if ($fourcc === 'VP8 ') {
            // Frame tag (key frame, profile ≤ 3, shown, first partition inside the chunk), start code, 14-bit sizes.
            $tag = strlen($data) >= 10 ? ord($data[0]) | ord($data[1]) << 8 | ord($data[2]) << 16 : 1;
            $size = ($tag & 1) === 0 && (($tag >> 1) & 7) <= 3 && (($tag >> 4) & 1) === 1 && ($tag >> 5) < strlen($data)
                && substr($data, 3, 3) === "\x9D\x01\x2A" ? [unpack('v', $data, 6)[1] & 0x3FFF, unpack('v', $data, 8)[1] & 0x3FFF] : [0, 0];
        } else {
            // Signature, then 14 bits width − 1, 14 bits height − 1, 1 bit alpha, 3 bits version (0).
            $bits = strlen($data) >= 5 && $data[0] === "\x2F" ? unpack('V', $data, 1)[1] : 0xFFFFFFFF;
            $size = ($bits >> 29) === 0 ? [($bits & 0x3FFF) + 1, (($bits >> 14) & 0x3FFF) + 1] : [0, 0];
        }

        if ($size[0] < 1 || $size[1] < 1) {
            throw new InvalidArgumentException('Corrupt WebP: unreadable '.trim($fourcc).' image header.');
        }

        return $size;
    }

    /** A little-endian 24-bit number. */
    private static function uint24(string $bytes, int $pos): int
    {
        return unpack('V', substr($bytes, $pos, 3)."\x00")[1];
    }

    /**
     * The chunks between $pos and $end (a missing padding byte after the last one is tolerated).
     *
     * @return non-empty-list<array{string, string}> fourcc, data
     */
    private static function chunks(string $bytes, int $pos, int $end): array
    {
        $chunks = [];

        while ($pos < $end) {
            if ($pos + 8 > $end) {
                throw new InvalidArgumentException('Truncated WebP: chunk header at byte '.$pos.'.');
            }

            $size = unpack('V', $bytes, $pos + 4)[1];

            if ($size > $end - $pos - 8) {
                throw new InvalidArgumentException('Corrupt WebP: truncated chunk at byte '.$pos.'.');
            }

            $chunks[] = [substr($bytes, $pos, 4), substr($bytes, $pos + 8, $size)];
            $pos += 8 + $size + ($size & 1);
        }

        if ($chunks === []) {
            throw new InvalidArgumentException('Corrupt WebP: no chunk.');
        }

        return $chunks;
    }

    private static function chunk(string $fourcc, string $data): string
    {
        $size = strlen($data);

        return $fourcc.pack('V', $size).$data.($size & 1 ? "\x00" : '');
    }
}
