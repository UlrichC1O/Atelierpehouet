<?php

namespace App\Cms\Media;

use InvalidArgumentException;

/**
 * Rebuilds a JPEG from an allow-list of its segments (docs/CMS.md §13 C8), without decoding it:
 * SOI; APP0 JFIF reduced to its header (an embedded thumbnail is dropped); a minimal Exif APP1 that
 * only says the Orientation, when it was not 1, so phone photos keep standing up; APP2 ICC colour
 * profiles; APP14 Adobe (colour transform of CMYK/YCCK files) reduced to its 12 bytes; the frame
 * (SOF0/1/2 Huffman, SOF9/10 arithmetic), tables (DQT, DHT, DAC, DRI) and every scan up to the first
 * EOI. Everything else is dropped: Exif (GPS, camera, dates), XMP, IPTC, MPF and other APPn, COM,
 * JFXX thumbnails, and whatever follows the end of the image (secondary MPF images, motion-photo
 * videos). A stream that does not hold one frame and its scans up to an EOI is refused.
 */
final class JpegSanitizer
{
    /** Start-of-frame markers browsers decode: baseline, extended and progressive, Huffman or arithmetic. */
    private const FRAMES = [0xC0, 0xC1, 0xC2, 0xC9, 0xCA];

    /** Tables copied as they are: DHT, DAC, DQT, DRI. */
    private const TABLES = [0xC4, 0xCC, 0xDB, 0xDD];

    /**
     * @throws InvalidArgumentException when the stream is not a well-formed JPEG
     */
    public static function strip(string $bytes): string
    {
        $length = strlen($bytes);

        if ($length < 4 || ! str_starts_with($bytes, "\xFF\xD8")) {
            throw new InvalidArgumentException('Not a JPEG stream.');
        }

        $jfif = '';
        $chunks = [];
        $orientation = null;
        $frame = false;
        $scans = 0;
        $pos = 2;

        while (true) {
            if ($pos >= $length) {
                throw new InvalidArgumentException('Truncated JPEG: no end-of-image marker.');
            }

            if ($bytes[$pos] !== "\xFF") {
                throw new InvalidArgumentException('Corrupt JPEG: marker expected at byte '.$pos.'.');
            }

            while ($pos < $length && $bytes[$pos] === "\xFF") {
                $pos++; // fill bytes
            }

            if ($pos >= $length) {
                continue;
            }

            $marker = ord($bytes[$pos++]);

            if ($marker === 0xD9) { // EOI: whatever follows is cut
                break;
            }

            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) { // TEM, stray RSTn: no length, dropped
                continue;
            }

            if ($marker === 0x00 || $marker === 0xD8 || $pos + 2 > $length) {
                throw new InvalidArgumentException('Corrupt JPEG: unexpected marker at byte '.($pos - 1).'.');
            }

            $size = unpack('n', $bytes, $pos)[1];

            if ($size < 2 || $pos + $size > $length) {
                throw new InvalidArgumentException('Corrupt JPEG: truncated segment at byte '.$pos.'.');
            }

            $payload = substr($bytes, $pos + 2, $size - 2);
            $pos += $size;

            if ($marker === 0xDA) { // SOS: entropy-coded data up to the next real marker
                if (! $frame || strlen($payload) !== 4 + 2 * ord($payload[0] ?? "\x00")) {
                    throw new InvalidArgumentException('Corrupt JPEG: scan without a frame, or malformed.');
                }

                $end = self::scanEnd($bytes, $pos, $length);
                $chunks[] = self::segment($marker, $payload).substr($bytes, $pos, $end - $pos);
                $pos = $end;
                $scans++;

                continue;
            }

            if (in_array($marker, self::FRAMES, true)) {
                if ($frame) {
                    throw new InvalidArgumentException('Corrupt JPEG: more than one frame.');
                }

                self::checkFrame($payload);
                $frame = true;
                $chunks[] = self::segment($marker, $payload);

                continue;
            }

            if ($marker >= 0xC0 && $marker <= 0xCF && ! in_array($marker, self::TABLES, true) && $marker !== 0xC8) {
                throw new InvalidArgumentException('Unsupported JPEG: lossless or hierarchical frame.');
            }

            if ($marker === 0xE0) { // JFIF header without its thumbnail, once, right after SOI
                if ($jfif === '' && str_starts_with($payload, "JFIF\x00") && strlen($payload) >= 14) {
                    $jfif = self::segment(0xE0, substr($payload, 0, 12)."\x00\x00");
                }

                continue;
            }

            if ($marker === 0xE1) { // Exif or XMP: only the orientation survives
                if (str_starts_with($payload, "Exif\x00\x00")) {
                    $orientation ??= ExifOrientation::read(substr($payload, 6));
                }

                continue;
            }

            $segment = self::kept($marker, $payload);

            if ($segment !== null) {
                $chunks[] = $segment;
            }
        }

        if (! $frame || $scans === 0) {
            throw new InvalidArgumentException('Corrupt JPEG: no frame or no scan.');
        }

        $exif = $orientation !== null && $orientation !== 1 ? self::orientationSegment($orientation) : '';

        return "\xFF\xD8".$jfif.$exif.implode('', $chunks)."\xFF\xD9";
    }

    /** Orientation of a JPEG (1–8) from its first Exif APP1 segment, or null. */
    public static function orientation(string $bytes): ?int
    {
        $length = strlen($bytes);
        $pos = 2;

        if (! str_starts_with($bytes, "\xFF\xD8")) {
            return null;
        }

        while ($pos + 4 <= $length && $bytes[$pos] === "\xFF") {
            $marker = ord($bytes[$pos + 1]);

            if ($marker === 0xFF) {
                $pos++;

                continue;
            }

            if ($marker === 0xDA || $marker === 0xD9) {
                return null;
            }

            $size = unpack('n', $bytes, $pos + 2)[1];

            if ($marker === 0xE1 && substr($bytes, $pos + 4, 6) === "Exif\x00\x00") {
                return ExifOrientation::read(substr($bytes, $pos + 10, max(0, $size - 8)));
            }

            $pos += 2 + $size;
        }

        return null;
    }

    /** The Exif APP1 segment that only says "Orientation = $orientation". */
    public static function orientationSegment(int $orientation): string
    {
        $payload = "Exif\x00\x00".ExifOrientation::tiff($orientation);

        return "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }

    /** The segment rebuilt when $marker is on the allow-list (tables, ICC profile, Adobe), else null. */
    private static function kept(int $marker, string $payload): ?string
    {
        return match (true) {
            in_array($marker, self::TABLES, true) => self::segment($marker, $payload),
            $marker === 0xE2 && str_starts_with($payload, "ICC_PROFILE\x00") => self::segment($marker, $payload),
            $marker === 0xEE && str_starts_with($payload, 'Adobe') && strlen($payload) >= 12 => self::segment($marker, substr($payload, 0, 12)),
            default => null, // other APPn (XMP, IPTC, MPF, JFXX…), COM, JPGn, DNL…: dropped
        };
    }

    private static function segment(int $marker, string $payload): string
    {
        return "\xFF".chr($marker).pack('n', strlen($payload) + 2).$payload;
    }

    /** An 8-bit frame of 1 to 4 components with a size (no DNL marker, no 12-bit samples: browsers decode neither). */
    private static function checkFrame(string $payload): void
    {
        $components = ord($payload[5] ?? "\x00");

        if (strlen($payload) !== 6 + 3 * $components || $components < 1 || $components > 4 || ord($payload[0]) !== 8
            || unpack('n', $payload, 1)[1] === 0 || unpack('n', $payload, 3)[1] === 0) {
            throw new InvalidArgumentException('Unsupported JPEG frame.');
        }
    }

    /** Offset of the marker that ends the entropy-coded data starting at $pos. */
    private static function scanEnd(string $bytes, int $pos, int $length): int
    {
        while (($ff = strpos($bytes, "\xFF", $pos)) !== false) {
            $next = $ff + 1;

            while ($next < $length && $bytes[$next] === "\xFF") {
                $next++;
            }

            if ($next >= $length) {
                return $length;
            }

            $byte = ord($bytes[$next]);

            if ($byte === 0x00 || ($byte >= 0xD0 && $byte <= 0xD7)) { // stuffed 0xFF or restart marker
                $pos = $next + 1;

                continue;
            }

            return $ff;
        }

        return $length;
    }
}
