<?php

namespace App\Cms\Media;

/**
 * Reads and writes the EXIF Orientation tag (0x0112) of a TIFF structure — the payload of a JPEG
 * "Exif\0\0" APP1 segment. Values 1–8 (1 = upright; 5–8 swap width and height on display).
 */
final class ExifOrientation
{
    private const TAG = 0x0112;

    /** Orientation stored in a TIFF structure, or null (absent, invalid, unreadable). */
    public static function read(string $tiff): ?int
    {
        $length = strlen($tiff);

        if ($length < 8) {
            return null;
        }

        [$short, $long] = match (substr($tiff, 0, 2)) {
            'II' => ['v', 'V'],
            'MM' => ['n', 'N'],
            default => [null, null],
        };

        if ($short === null || unpack($short, $tiff, 2)[1] !== 42) {
            return null;
        }

        $ifd = unpack($long, $tiff, 4)[1];

        if ($ifd < 8 || $ifd + 2 > $length) {
            return null;
        }

        $count = unpack($short, $tiff, $ifd)[1];

        for ($i = 0; $i < $count; $i++) {
            $entry = $ifd + 2 + 12 * $i;

            if ($entry + 12 > $length) {
                return null;
            }

            if (unpack($short, $tiff, $entry)[1] !== self::TAG) {
                continue;
            }

            $value = match (unpack($short, $tiff, $entry + 2)[1]) {
                3 => unpack($short, $tiff, $entry + 8)[1], // SHORT, left-justified in the value field
                4 => unpack($long, $tiff, $entry + 8)[1],  // LONG (non-standard but seen)
                default => null,
            };

            return is_int($value) && $value >= 1 && $value <= 8 ? $value : null;
        }

        return null;
    }

    /** A minimal big-endian TIFF structure holding only the Orientation tag (26 bytes). */
    public static function tiff(int $orientation): string
    {
        return "MM\x00\x2A".pack('N', 8)  // header, IFD0 at offset 8
            .pack('n', 1)                 // one entry
            .pack('nnN', self::TAG, 3, 1).pack('n', $orientation)."\x00\x00"
            .pack('N', 0);                // no next IFD
    }

    /** True when the orientation turns the image by 90° (width and height swap on display). */
    public static function swapsDimensions(?int $orientation): bool
    {
        return $orientation !== null && $orientation >= 5 && $orientation <= 8;
    }
}
