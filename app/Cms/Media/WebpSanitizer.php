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
            $body = self::chunk($first, $header); // a simple file: its image chunk only
        } elseif ($first === 'VP8X' && strlen($header) === 10) {
            $body = self::extended($header, array_slice($chunks, 1));
        } else {
            throw new InvalidArgumentException('Corrupt WebP: no image chunk.');
        }

        return 'RIFF'.pack('V', 4 + strlen($body)).'WEBP'.$body;
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
        $kept = '';
        $found = [];

        foreach ($chunks as [$fourcc, $data]) {
            if (! in_array($fourcc, $animated ? self::ANIMATED : self::STILL, true)) {
                continue;
            }

            $kept .= self::chunk($fourcc, $fourcc === 'ANMF' ? self::frame($data) : $data);
            $found[$fourcc] = true;
        }

        $image = $animated ? isset($found['ANIM'], $found['ANMF']) : isset($found['VP8 ']) || isset($found['VP8L']);

        if (! $image) {
            throw new InvalidArgumentException('Corrupt WebP: no image in the extended file.');
        }

        $flags = ($flags & (self::FLAG_ALPHA | self::FLAG_ANIMATION)) | (isset($found['ICCP']) ? self::FLAG_ICC : 0);

        // Flags, 3 reserved bytes, canvas width − 1 and height − 1 (24 bits each).
        return self::chunk('VP8X', chr($flags)."\x00\x00\x00".substr($header, 4, 6)).$kept;
    }

    /** An animation frame: its header (position, size, duration, flags) and its image chunks only. */
    private static function frame(string $data): string
    {
        if (strlen($data) < 16) {
            throw new InvalidArgumentException('Corrupt WebP: animation frame too short.');
        }

        $kept = '';
        $image = false;

        foreach (self::chunks($data, 16, strlen($data)) as [$fourcc, $chunk]) {
            if (in_array($fourcc, self::FRAME, true)) {
                $kept .= self::chunk($fourcc, $chunk);
                $image = $image || $fourcc !== 'ALPH';
            }
        }

        if (! $image) {
            throw new InvalidArgumentException('Corrupt WebP: animation frame without an image.');
        }

        return substr($data, 0, 16).$kept;
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
