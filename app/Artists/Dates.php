<?php

namespace App\Artists;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Dates of the exhibitions (docs/ARTISTS.md §4.3): a readable range in French or English, and the
 * status of an exhibition (upcoming, current, past) on a given day.
 */
final class Dates
{
    public const UPCOMING = 'upcoming';

    public const CURRENT = 'current';

    public const PAST = 'past';

    /** Every status, in the order of the pages: what is on now, what comes next, the CV. */
    public const STATUSES = [self::CURRENT, self::UPCOMING, self::PAST];

    /**
     * The dates of an exhibition as written on the pages:
     *   no date                ⇒ "2019"
     *   start only / same day  ⇒ "12 mars 2025"            · "12 March 2025"
     *   same month             ⇒ "12 – 30 mars 2025"       · "12–30 March 2025"
     *   same year              ⇒ "12 mars – 30 avril 2025" · "12 March – 30 April 2025"
     *   otherwise              ⇒ "12 décembre 2024 – 30 janvier 2025"
     * French writes the first day of a month "1er" ("1er – 15 mars 2025").
     *
     * @param  string|null  $start  Y-m-d (a longer database value is cut to its date)
     * @param  string|null  $end  Y-m-d; ignored without a start or when before the start
     * @param  string|null  $locale  default: the current locale
     */
    public static function range(?string $start, ?string $end, int $year, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $from = self::parse($start);

        if ($from === null) {
            return (string) $year;
        }

        $to = self::parse($end);

        if ($to === null || $to->lessThanOrEqualTo($from)) {
            return self::date($from, $locale);
        }

        if ($from->year === $to->year && $from->month === $to->month) {
            $separator = self::french($locale) ? ' – ' : '–';

            return self::day($from, $locale).$separator.self::day($to, $locale).' '.self::month($to, $locale).' '.$to->year;
        }

        if ($from->year === $to->year) {
            return self::day($from, $locale).' '.self::month($from, $locale).' – '.self::date($to, $locale);
        }

        return self::date($from, $locale).' – '.self::date($to, $locale);
    }

    /**
     * Status of an exhibition on $today (default: today).
     * With dates: start after today ⇒ upcoming; start ≤ today ≤ end (or start = today without an
     * end) ⇒ current; otherwise past. Year only: a year after the current one ⇒ upcoming, else past.
     *
     * @return string self::UPCOMING|self::CURRENT|self::PAST
     */
    public static function status(?string $start, ?string $end, int $year, ?CarbonInterface $today = null): string
    {
        $day = ($today ?? Carbon::now())->format('Y-m-d');
        $from = self::parse($start)?->format('Y-m-d');

        if ($from === null) {
            return $year > (int) substr($day, 0, 4) ? self::UPCOMING : self::PAST;
        }

        if ($from > $day) {
            return self::UPCOMING;
        }

        $to = self::parse($end)?->format('Y-m-d') ?? $from;

        return $to >= $day ? self::CURRENT : self::PAST;
    }

    /** A valid calendar date from "Y-m-d…" (time part ignored), else null. */
    public static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $parts) !== 1
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $parts[0]);
        } catch (Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable ? $date : null;
    }

    private static function date(CarbonImmutable $date, string $locale): string
    {
        return self::day($date, $locale).' '.self::month($date, $locale).' '.$date->year;
    }

    private static function day(CarbonImmutable $date, string $locale): string
    {
        return $date->day === 1 && self::french($locale) ? '1er' : (string) $date->day;
    }

    private static function month(CarbonImmutable $date, string $locale): string
    {
        return $date->locale($locale)->translatedFormat('F');
    }

    private static function french(string $locale): bool
    {
        return str_starts_with(strtolower($locale), 'fr');
    }
}
