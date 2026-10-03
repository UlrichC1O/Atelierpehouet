<?php

namespace App\Cms\Media;

use InvalidArgumentException;

/**
 * Rebuilds a PNG from an allow-list of its chunks (docs/CMS.md §13 C8): the image (IHDR, PLTE,
 * IDAT, IEND), transparency and colour (tRNS, gAMA, cHRM, sRGB, iCCP, sBIT, cICP), pixel size and
 * background (pHYs, bKGD) and the APNG animation (acTL, fcTL, fdAT). Everything else — tEXt, iTXt
 * (XMP), zTXt, eXIf (GPS), tIME, private chunks — and anything after IEND is dropped. A stream that
 * does not start with IHDR, has no IDAT, ends before IEND or carries a kept chunk with a wrong
 * CRC is refused.
 */
final class PngSanitizer
{
    private const SIGNATURE = "\x89PNG\r\n\x1A\n";

    private const KEPT = [
        'IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'gAMA', 'cHRM', 'sRGB', 'iCCP', 'sBIT', 'cICP', 'pHYs', 'bKGD',
        'acTL', 'fcTL', 'fdAT',
    ];

    /**
     * @throws InvalidArgumentException when the stream is not a well-formed PNG
     */
    public static function strip(string $bytes): string
    {
        if (! str_starts_with($bytes, self::SIGNATURE)) {
            throw new InvalidArgumentException('Not a PNG stream.');
        }

        $length = strlen($bytes);
        $out = self::SIGNATURE;
        $pos = strlen(self::SIGNATURE);
        $images = 0;

        while ($pos + 12 <= $length) {
            $size = unpack('N', $bytes, $pos)[1];
            $type = substr($bytes, $pos + 4, 4);

            if (preg_match('/^[A-Za-z]{4}$/', $type) !== 1 || $size > $length - $pos - 12) {
                throw new InvalidArgumentException('Corrupt PNG: bad chunk at byte '.$pos.'.');
            }

            if ($pos === strlen(self::SIGNATURE) && ($type !== 'IHDR' || $size !== 13)) {
                throw new InvalidArgumentException('Corrupt PNG: IHDR must come first.');
            }

            if (in_array($type, self::KEPT, true)) {
                if (crc32(substr($bytes, $pos + 4, $size + 4)) !== unpack('N', $bytes, $pos + 8 + $size)[1]) {
                    throw new InvalidArgumentException('Corrupt PNG: bad CRC of the '.$type.' chunk.');
                }

                $out .= substr($bytes, $pos, $size + 12);
                $images += $type === 'IDAT' ? 1 : 0;
            }

            $pos += $size + 12;

            if ($type === 'IEND') {
                if ($images === 0) {
                    throw new InvalidArgumentException('Corrupt PNG: no IDAT chunk.');
                }

                return $out;
            }
        }

        throw new InvalidArgumentException('Truncated PNG: no IEND chunk.');
    }
}
