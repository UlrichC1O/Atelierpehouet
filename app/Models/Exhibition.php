<?php

namespace App\Models;

use App\Artists\Concerns\FlushesArtists;
use App\Artists\Concerns\HasLocalizedText;
use App\Artists\Concerns\NormalisesAttributes;
use App\Artists\Dates;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Touches;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An exhibition of an artist page (docs/ARTISTS.md §3): solo/group show, residency, fair… with its
 * venue, city, year and optional dates.
 *
 * status() and dates() are methods (`$exhibition->status()`): there are no such attributes.
 * Saving or deleting one touches its artist (the page's last modification).
 *
 * @property int $id
 * @property int $artist_id
 * @property int|null $media_id
 * @property string $title_fr
 * @property string|null $title_en
 * @property string $kind one of self::KINDS
 * @property string|null $venue
 * @property string|null $city
 * @property int $year the year of starts_on when there is one
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $description_fr
 * @property string|null $description_en
 * @property string|null $url https URL
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'artist_id', 'media_id', 'title_fr', 'title_en', 'kind', 'venue', 'city', 'year', 'starts_on', 'ends_on',
    'description_fr', 'description_en', 'url', 'is_published',
])]
#[Touches(['artist'])]
class Exhibition extends Model
{
    use FlushesArtists, HasLocalizedText, NormalisesAttributes;

    /** @var list<string> boolean columns, written as PHP bools (NormalisesAttributes) */
    public const BOOLEAN_COLUMNS = ['is_published'];

    /** @var array<string, int|null> string columns ⇒ size in characters (null: text) */
    public const STRING_COLUMNS = [
        'title_fr' => 160, 'title_en' => 160, 'kind' => 20, 'venue' => 160, 'city' => 120,
        'description_fr' => null, 'description_en' => null, 'url' => 255,
    ];

    /** Kinds of exhibition (labels: artists.kinds.{kind}). */
    public const KINDS = ['solo', 'group', 'residency', 'fair', 'other'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'kind' => 'group',
        'is_published' => true,
    ];

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * The visual (a row of the CMS photo library).
     *
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /** "upcoming", "current" or "past" on $today (default: today) — see App\Artists\Dates::status(). */
    public function status(?CarbonInterface $today = null): string
    {
        return Dates::status($this->starts_on?->format('Y-m-d'), $this->ends_on?->format('Y-m-d'), (int) $this->year, $today);
    }

    /** The dates as shown on the pages ("12 – 30 mars 2025", or the year) — see App\Artists\Dates::range(). */
    public function dates(?string $locale = null): string
    {
        return Dates::range($this->starts_on?->format('Y-m-d'), $this->ends_on?->format('Y-m-d'), (int) $this->year, $locale);
    }

    /**
     * Real booleans for Postgres with emulated prepares (see Artist::isPublished()).
     *
     * @return Attribute<bool, mixed>
     */
    protected function isPublished(): Attribute
    {
        return Attribute::set(fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'artist_id' => 'integer',
            'media_id' => 'integer',
            'is_published' => 'boolean',
            'year' => 'integer',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
        ];
    }
}
