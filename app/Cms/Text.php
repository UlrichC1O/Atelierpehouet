<?php

namespace App\Cms;

use Throwable;

/**
 * Strings on their way into the database (docs/CMS.md §13 B7).
 *
 * Postgres refuses invalid UTF-8 and the NUL byte in text columns, and a varchar(n) refuses longer
 * values: every string a CMS write stores goes through column() first. Text searches build their
 * pattern with like() and query with whereLike($column, Text::like($term), caseSensitive: false).
 */
final class Text
{
    /** $value as valid UTF-8 without NUL bytes, cut to $max characters (null stays null). */
    public static function column(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace("\0", '', mb_scrub($value, 'UTF-8'));

        return mb_substr($value, 0, max(0, $max), 'UTF-8');
    }

    /**
     * Every string inside $value (arrays walked recursively, keys included) made storable: valid
     * UTF-8, no NUL byte. Other scalars are kept; objects become null. For JSON columns.
     */
    public static function clean(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace("\0", '', mb_scrub($value, 'UTF-8'));
        }

        if (is_array($value)) {
            $clean = [];

            foreach ($value as $key => $item) {
                $clean[is_string($key) ? self::clean($key) : $key] = self::clean($item);
            }

            return $clean;
        }

        return is_scalar($value) ? $value : null;
    }

    /**
     * LIKE pattern matching $term anywhere: % and _ (and the escape character itself) are escaped
     * with a backslash, the default escape character of Postgres and MySQL. SQLite's LIKE has no
     * escape character unless the query names one (whereLike() does not): there the term is kept
     * as is — a "_" or "%" in it then also matches itself, so "IMG_2041" still finds IMG_2041.jpg.
     *
     * @param  string|null  $driver  driver of the queried connection (default: the default connection's)
     */
    public static function like(string $term, ?string $driver = null): string
    {
        $term = str_replace("\0", '', mb_scrub($term, 'UTF-8'));

        return '%'.(($driver ?? self::driver()) === 'sqlite' ? $term : addcslashes($term, '\\%_')).'%';
    }

    /** Driver of the default database connection, read from the configuration (no connection opened). */
    private static function driver(): ?string
    {
        try {
            $driver = config('database.connections.'.config('database.default').'.driver');
        } catch (Throwable) {
            return null;
        }

        return is_string($driver) ? $driver : null;
    }
}
