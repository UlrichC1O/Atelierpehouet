<?php

namespace App\Cms;

/**
 * Guards the texts editor (docs/CMS.md §4.6): an edited translation must keep the placeholders of the
 * original (":count", "{name}") and, for pluralised lines, the same number of "|" segments.
 */
final class Placeholders
{
    /** ":name" placeholders (not "::", "http://", "12:30"). */
    private const COLON = '/(?<![\w:]):([A-Za-z][A-Za-z_]*)/';

    /** "{name}" placeholders (not the "{0}" of pluralised lines). */
    private const BRACES = '/\{([A-Za-z][A-Za-z_]*)\}/';

    /**
     * Placeholders of $default missing from $value (as written in $default), plus "|" when the
     * number of pluralisation segments differs. Matching ignores case: Laravel replaces ":Name"
     * and ":NAME" with the same value as ":name".
     *
     * @return list<string>
     */
    public static function missing(string $default, string $value): array
    {
        $present = array_map('strtolower', self::tokens($value));
        $missing = [];

        foreach (self::tokens($default) as $token) {
            if (! in_array(strtolower($token), $present, true) && ! in_array($token, $missing, true)) {
                $missing[] = $token;
            }
        }

        if (substr_count($default, '|') !== substr_count($value, '|')) {
            $missing[] = '|';
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $text): array
    {
        preg_match_all(self::COLON, $text, $colons);
        preg_match_all(self::BRACES, $text, $braces);

        return array_values(array_unique([...$colons[0], ...$braces[0]]));
    }
}
