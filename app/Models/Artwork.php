<?php

namespace App\Models;

use App\Artists\Concerns\FlushesArtists;
use App\Artists\Concerns\HasLocalizedText;
use App\Artists\Concerns\NormalisesAttributes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Touches;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An artwork of an artist page (docs/ARTISTS.md §3): photo (CMS library, never cropped), title,
 * year, technique, dimensions, availability, description.
 *
 * Saving or deleting one touches its artist (the page's last modification, e.g. for the sitemap).
 *
 * @property int $id
 * @property int $artist_id
 * @property int|null $media_id
 * @property string $title_fr
 * @property string|null $title_en
 * @property string|null $year free text ("2025", "2019–2021"…)
 * @property string|null $medium_fr technique
 * @property string|null $medium_en
 * @property string|null $dimensions
 * @property string|null $description_fr
 * @property string|null $description_en
 * @property string $availability one of self::AVAILABILITIES
 * @property bool $is_published
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'artist_id', 'media_id', 'title_fr', 'title_en', 'year', 'medium_fr', 'medium_en', 'dimensions',
    'description_fr', 'description_en', 'availability', 'is_published', 'position',
])]
#[Touches(['artist'])]
class Artwork extends Model
{
    use FlushesArtists, HasLocalizedText, NormalisesAttributes;

    /** @var list<string> boolean columns, written as PHP bools (NormalisesAttributes) */
    public const BOOLEAN_COLUMNS = ['is_published'];

    /** @var array<string, int|null> string columns ⇒ size in characters (null: text) */
    public const STRING_COLUMNS = [
        'title_fr' => 160, 'title_en' => 160, 'year' => 20, 'medium_fr' => 160, 'medium_en' => 160,
        'dimensions' => 80, 'description_fr' => null, 'description_en' => null, 'availability' => 20,
    ];

    /** Availability of a work ("none" shows nothing; labels: artists.availability.{key}). */
    public const AVAILABILITIES = ['none', 'available', 'reserved', 'sold', 'collection', 'commission'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'availability' => 'none',
        'is_published' => true,
        'position' => 0,
    ];

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * The photo (a row of the CMS photo library).
     *
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
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
            'position' => 'integer',
        ];
    }
}
