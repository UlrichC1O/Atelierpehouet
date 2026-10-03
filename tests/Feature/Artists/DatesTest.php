<?php

namespace Tests\Feature\Artists;

use App\Artists\Dates;
use App\Models\Exhibition;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Dates of the exhibitions (docs/ARTISTS.md §4.3): readable ranges in both languages, and the status. */
class DatesTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: int, 3: string, 4: string}>
     */
    public static function ranges(): array
    {
        return [
            'year only' => [null, null, 2019, '2019', '2019'],
            'year only, end ignored' => [null, '2019-05-02', 2019, '2019', '2019'],
            'start only' => ['2025-03-12', null, 2025, '12 mars 2025', '12 March 2025'],
            'same day' => ['2025-03-12', '2025-03-12', 2025, '12 mars 2025', '12 March 2025'],
            'same month' => ['2025-03-12', '2025-03-30', 2025, '12 – 30 mars 2025', '12–30 March 2025'],
            'same year' => ['2025-03-12', '2025-04-30', 2025, '12 mars – 30 avril 2025', '12 March – 30 April 2025'],
            'across years' => ['2024-12-12', '2025-01-30', 2024, '12 décembre 2024 – 30 janvier 2025', '12 December 2024 – 30 January 2025'],
            'first of the month' => ['2025-02-01', null, 2025, '1er février 2025', '1 February 2025'],
            'first day starts a month range' => ['2025-03-01', '2025-03-15', 2025, '1er – 15 mars 2025', '1–15 March 2025'],
            'first days in a year range' => ['2025-03-01', '2025-04-01', 2025, '1er mars – 1er avril 2025', '1 March – 1 April 2025'],
            'end before start' => ['2025-03-12', '2025-03-02', 2025, '12 mars 2025', '12 March 2025'],
            'database datetime' => ['2025-06-21 00:00:00', '2025-06-28T00:00:00.000000Z', 2025, '21 – 28 juin 2025', '21–28 June 2025'],
            'impossible date' => ['2025-02-30', null, 2025, '2025', '2025'],
            'not a date' => ['bientôt', null, 2026, '2026', '2026'],
        ];
    }

    #[DataProvider('ranges')]
    public function test_range_in_french_and_english(?string $start, ?string $end, int $year, string $fr, string $en): void
    {
        $this->assertSame($fr, Dates::range($start, $end, $year, 'fr'));
        $this->assertSame($en, Dates::range($start, $end, $year, 'en'));
    }

    public function test_range_follows_the_current_locale_by_default(): void
    {
        app()->setLocale('en');
        $this->assertSame('12 March – 30 April 2025', Dates::range('2025-03-12', '2025-04-30', 2025));

        app()->setLocale('fr');
        $this->assertSame('12 mars – 30 avril 2025', Dates::range('2025-03-12', '2025-04-30', 2025));
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: int, 3: string}>
     */
    public static function statuses(): array
    {
        // Today is Saturday 15 March 2025.
        return [
            'starts later' => ['2025-03-20', '2025-04-20', 2025, 'upcoming'],
            'starts tomorrow, no end' => ['2025-03-16', null, 2025, 'upcoming'],
            'running' => ['2025-03-10', '2025-03-20', 2025, 'current'],
            'opens today' => ['2025-03-15', null, 2025, 'current'],
            'opens today with an end' => ['2025-03-15', '2025-03-30', 2025, 'current'],
            'closes today' => ['2025-03-01', '2025-03-15', 2025, 'current'],
            'closed yesterday' => ['2025-03-01', '2025-03-14', 2025, 'past'],
            'one day, earlier' => ['2025-03-10', null, 2025, 'past'],
            'year only, next year' => [null, null, 2026, 'upcoming'],
            'year only, this year' => [null, null, 2025, 'past'],
            'year only, long ago' => [null, null, 2019, 'past'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_status_on_a_given_day(?string $start, ?string $end, int $year, string $status): void
    {
        $this->assertSame($status, Dates::status($start, $end, $year, Carbon::create(2025, 3, 15, 18, 30)));
    }

    public function test_status_uses_today_by_default(): void
    {
        $this->travelTo(Carbon::create(2025, 3, 15, 9));

        $this->assertSame('current', Dates::status('2025-03-01', '2025-03-15', 2025));
        $this->assertSame('past', Dates::status('2025-03-01', '2025-03-14', 2025));

        $this->travelTo(Carbon::create(2025, 3, 16, 0, 0, 1));

        $this->assertSame('past', Dates::status('2025-03-01', '2025-03-15', 2025));
    }

    public function test_the_exhibition_model_delegates_to_dates(): void
    {
        $exhibition = new Exhibition(['year' => 2025, 'starts_on' => '2025-03-10', 'ends_on' => '2025-03-20']);

        $this->assertSame('current', $exhibition->status(Carbon::create(2025, 3, 15)));
        $this->assertSame('upcoming', $exhibition->status(Carbon::create(2025, 3, 1)));
        $this->assertSame('past', $exhibition->status(Carbon::create(2025, 3, 21)));
        $this->assertSame('10 – 20 mars 2025', $exhibition->dates('fr'));
        $this->assertSame('10–20 March 2025', $exhibition->dates('en'));

        $yearOnly = new Exhibition(['year' => 2019]);

        $this->assertSame('past', $yearOnly->status(Carbon::create(2025, 3, 15)));
        $this->assertSame('2019', $yearOnly->dates('fr'));
    }

    public function test_parse_keeps_valid_calendar_dates_only(): void
    {
        $this->assertSame('2024-02-29', Dates::parse('2024-02-29')?->format('Y-m-d'));
        $this->assertSame('2025-06-21', Dates::parse('2025-06-21 13:45:00')?->format('Y-m-d'));
        $this->assertNull(Dates::parse('2025-02-29'));
        $this->assertNull(Dates::parse('21/06/2025'));
        $this->assertNull(Dates::parse(''));
        $this->assertNull(Dates::parse(null));
    }
}
