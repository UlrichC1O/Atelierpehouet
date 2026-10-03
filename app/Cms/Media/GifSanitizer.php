<?php

namespace App\Cms\Media;

use InvalidArgumentException;

/**
 * Rebuilds a GIF from an allow-list of its blocks (docs/CMS.md §13 C8): header, logical screen and
 * colour tables, images (frames), their graphic control extensions (timing, transparency) and the
 * loop count of animations (NETSCAPE2.0 / ANIMEXTS1.0, reduced to that sub-block). Comments, XMP,
 * ICC and every other application extension, plain-text extensions (no browser draws them) and
 * anything after the trailer are dropped. The image data is copied as it is: no GD needed.
 *
 * A frame reaching outside the logical screen is refused: browsers grow the canvas to fit it, which
 * would bypass the pixel limit checked on the screen size.
 */
final class GifSanitizer
{
    /** Application extensions kept (their loop-count sub-block only). */
    private const LOOPS = ['NETSCAPE2.0', 'ANIMEXTS1.0'];

    /**
     * @throws InvalidArgumentException when the stream is not a well-formed GIF
     */
    public static function strip(string $bytes): string
    {
        $length = strlen($bytes);

        if ($length < 13 || ! in_array(substr($bytes, 0, 6), ['GIF87a', 'GIF89a'], true)) {
            throw new InvalidArgumentException('Not a GIF stream.');
        }

        ['width' => $width, 'height' => $height, 'packed' => $packed] = unpack('vwidth/vheight/Cpacked', $bytes, 6);
        $pos = 13 + self::colourTable($packed);

        if ($pos > $length) {
            throw new InvalidArgumentException('Truncated GIF: global colour table.');
        }

        $out = substr($bytes, 0, $pos);
        $control = '';  // graphic control extension waiting for its image
        $images = 0;

        // A missing trailer after complete blocks is tolerated (the rebuilt file gets one).
        while ($pos < $length && $bytes[$pos] !== "\x3B") {
            $introducer = $bytes[$pos];

            if ($introducer === "\x2C") {
                [$block, $pos] = self::image($bytes, $pos, $width, $height);
                $out .= $control.$block;
                $control = '';
                $images++;

                continue;
            }

            if ($introducer !== "\x21" || $pos + 2 > $length) {
                throw new InvalidArgumentException('Corrupt GIF: unexpected byte at '.$pos.'.');
            }

            $label = ord($bytes[$pos + 1]);
            $blocks = self::subBlocks($bytes, $pos + 2);
            $pos = $blocks['end'];

            match ($label) {
                0xF9 => $control = self::control($blocks['data']),
                0xFF => $out .= self::loop($blocks['data']),
                0x01 => $control = '', // plain text: dropped, with the control extension that applied to it
                default => null,       // comment (0xFE) and unknown extensions: dropped
            };
        }

        if ($images === 0) {
            throw new InvalidArgumentException('Corrupt GIF: no image.');
        }

        return $out."\x3B";
    }

    /**
     * One image: descriptor, local colour table, LZW minimum code size and data sub-blocks.
     *
     * @return array{string, int} the block as it is, the position after it
     */
    private static function image(string $bytes, int $pos, int $width, int $height): array
    {
        $length = strlen($bytes);

        if ($pos + 10 > $length) {
            throw new InvalidArgumentException('Truncated GIF: image descriptor at '.$pos.'.');
        }

        ['left' => $left, 'top' => $top, 'w' => $w, 'h' => $h, 'packed' => $packed] = unpack('vleft/vtop/vw/vh/Cpacked', $bytes, $pos + 1);

        if ($w < 1 || $h < 1 || $left + $w > $width || $top + $h > $height) {
            throw new InvalidArgumentException('Corrupt GIF: frame outside the logical screen.');
        }

        $codeSize = $pos + 10 + self::colourTable($packed);

        if ($codeSize >= $length || ord($bytes[$codeSize]) < 1 || ord($bytes[$codeSize]) > 8) {
            throw new InvalidArgumentException('Corrupt GIF: bad LZW code size at '.$codeSize.'.');
        }

        $end = self::subBlocks($bytes, $codeSize + 1)['end'];

        return [substr($bytes, $pos, $end - $pos), $end];
    }

    /**
     * The data sub-blocks starting at $pos (each: size byte + data; a 0 size ends them).
     *
     * @return array{data: list<string>, end: int}
     */
    private static function subBlocks(string $bytes, int $pos): array
    {
        $length = strlen($bytes);
        $data = [];

        while (true) {
            if ($pos >= $length) {
                throw new InvalidArgumentException('Truncated GIF: unterminated data sub-blocks.');
            }

            $size = ord($bytes[$pos++]);

            if ($size === 0) {
                return ['data' => $data, 'end' => $pos];
            }

            if ($pos + $size > $length) {
                throw new InvalidArgumentException('Truncated GIF: data sub-block at '.($pos - 1).'.');
            }

            $data[] = substr($bytes, $pos, $size);
            $pos += $size;
        }
    }

    /**
     * A graphic control extension rebuilt with its 4 bytes only (disposal, delay, transparency).
     *
     * @param  list<string>  $data
     */
    private static function control(array $data): string
    {
        return strlen($data[0] ?? '') >= 4 ? "\x21\xF9\x04".substr($data[0], 0, 4)."\x00" : '';
    }

    /**
     * The loop count of an animation, rebuilt alone; any other application extension is dropped.
     *
     * @param  list<string>  $data
     */
    private static function loop(array $data): string
    {
        $application = array_shift($data);

        if (! is_string($application) || ! in_array($application, self::LOOPS, true)) {
            return '';
        }

        foreach ($data as $block) {
            if (strlen($block) === 3 && $block[0] === "\x01") {
                return "\x21\xFF\x0B".$application."\x03".$block."\x00";
            }
        }

        return '';
    }

    /** Bytes of the colour table announced by a packed field (none when its top bit is clear). */
    private static function colourTable(int $packed): int
    {
        return ($packed & 0x80) !== 0 ? 3 * (2 << ($packed & 0x07)) : 0;
    }
}
