<?php

namespace App\Models;

use App\Artists\Concerns\FlushesArtists;
use App\Artists\Concerns\HasLocalizedText;
use App\Artists\Concerns\NormalisesAttributes;
use App\Artists\Http\EnsureArtistTables;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * An artist page (docs/ARTISTS.md §3): identity, biography (Markdown) and links, with the artist's
 * artworks and exhibitions. The public pages read them through App\Artists\ArtistDirectory; the
 * admin edits them through the Admin\{Artist,Artwork,Exhibition}Controller.
 *
 * url(), initials(), text() and photoId() are methods (`$artist->url()`): there are no such attributes.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $discipline_fr
 * @property string|null $discipline_en
 * @property string|null $location
 * @property string|null $statement_fr
 * @property string|null $statement_en
 * @property string|null $bio_fr Markdown
 * @property string|null $bio_en Markdown
 * @property string|null $meta_fr
 * @property string|null $meta_en
 * @property int|null $portrait_media_id
 * @property string $accent one of config('atelier.accents')
 * @property string|null $website https URL
 * @property string|null $instagram https URL
 * @property bool $is_published
 * @property bool $is_example the fictional reference artist (App\Artists\ExampleArtist)
 * @property int $position
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $artworks_count
 * @property-read int|null $exhibitions_count
 */
#[Fillable([
    'slug', 'name', 'discipline_fr', 'discipline_en', 'location', 'statement_fr', 'statement_en', 'bio_fr', 'bio_en',
    'meta_fr', 'meta_en', 'portrait_media_id', 'accent', 'website', 'instagram', 'is_published', 'is_example',
    'position', 'updated_by',
])]
class Artist extends Model
{
    use FlushesArtists, HasLocalizedText, NormalisesAttributes;

    /** @var list<string> boolean columns, written as PHP bools (NormalisesAttributes) */
    public const BOOLEAN_COLUMNS = ['is_published', 'is_example'];

    /** @var array<string, int|null> string columns ⇒ size in characters (null: text) */
    public const STRING_COLUMNS = [
        'slug' => 80, 'name' => 120, 'discipline_fr' => 120, 'discipline_en' => 120, 'location' => 120,
        'statement_fr' => 400, 'statement_en' => 400, 'bio_fr' => null, 'bio_en' => null, 'meta_fr' => 170,
        'meta_en' => 170, 'accent' => 10, 'website' => 255, 'instagram' => 255,
    ];

    /** Accent of a new artist (and of any invalid one on the public pages). */
    public const DEFAULT_ACCENT = 'yellow';

    /** Slugs: lower-case letters and digits in words joined by single hyphens (public URL /artistes/{slug}). */
    public const SLUG_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public const SLUG_MAX = 80;

    /** @var array<string, mixed> */
    protected $attributes = [
        'accent' => self::DEFAULT_ACCENT,
        'is_published' => false,
        'is_example' => false,
        'position' => 0,
    ];

    /**
     * Artworks in display order (position, id), hidden ones included.
     *
     * @return HasMany<Artwork, $this>
     */
    public function artworks(): HasMany
    {
        return $this->hasMany(Artwork::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Exhibitions, hidden ones included (the pages order them by status and dates).
     *
     * @return HasMany<Exhibition, $this>
     */
    public function exhibitions(): HasMany
    {
        return $this->hasMany(Exhibition::class);
    }

    /**
     * The portrait photo (a row of the CMS photo library).
     *
     * @return BelongsTo<Media, $this>
     */
    public function portrait(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'portrait_media_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Public URL of the page (/artistes/{slug}). */
    public function url(): string
    {
        return Route::has('artists.show') ? route('artists.show', $this->slug) : url('artistes/'.$this->slug);
    }

    /** "CD" for Camille Durand: see initialsOf(). */
    public function initials(): string
    {
        return self::initialsOf((string) $this->name);
    }

    /**
     * Id of the photo that represents the artist in lists: the portrait, else the photo of the first
     * artwork that has one (uses the artworks relation: eager load it for a list).
     */
    public function photoId(): ?int
    {
        if ($this->portrait_media_id !== null) {
            return (int) $this->portrait_media_id;
        }

        $artwork = $this->artworks->first(fn (Artwork $artwork): bool => $artwork->media_id !== null);

        return $artwork?->media_id === null ? null : (int) $artwork->media_id;
    }

    /**
     * Upper-case initials: the first letters of the first two words ("Camille Durand" ⇒ "CD"), or the
     * first two letters of a single word ("Pehouet" ⇒ "PE"). Multibyte-safe; punctuation is ignored.
     */
    public static function initialsOf(string $name): string
    {
        $words = [];

        foreach (preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $letters = (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $word);

            if ($letters !== '') {
                $words[] = $letters;
            }
        }

        if ($words === []) {
            return '';
        }

        $initials = count($words) === 1
            ? mb_substr($words[0], 0, 2)
            : mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1);

        return mb_strtoupper($initials);
    }

    /**
     * Route model binding of the admin pages ({artist}). Implicit bindings run before the controllers'
     * middleware, so while the artist tables are missing (migration pending) the owner is sent to the
     * maintenance page the way EnsureArtistTables does, instead of getting a server error.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null)
    {
        try {
            return parent::resolveRouteBinding($value, $field);
        } catch (QueryException $e) {
            if (EnsureArtistTables::ready()) {
                throw $e;
            }

            throw new HttpResponseException(EnsureArtistTables::unavailable(request()));
        }
    }

    /**
     * Admin order: position, then name.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name')->orderBy('id');
    }

    /**
     * Real booleans: with the Supabase pooler's emulated prepares an integer 1/0 cannot be written
     * to a boolean column (Laravel only rewrites true/false).
     *
     * @return Attribute<bool, mixed>
     */
    protected function isPublished(): Attribute
    {
        return Attribute::set(fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * @return Attribute<bool, mixed>
     */
    protected function isExample(): Attribute
    {
        return Attribute::set(fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_example' => 'boolean',
            'position' => 'integer',
            'portrait_media_id' => 'integer',
            'updated_by' => 'integer',
        ];
    }
}
