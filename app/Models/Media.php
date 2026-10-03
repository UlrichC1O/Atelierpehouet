<?php

namespace App\Models;

use App\Cms\MediaItem;
use App\Models\Concerns\FlushesCms;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A photo of the CMS library. Its files live in the media storage named by `driver`
 * (App\Cms\Media\MediaStorageManager) under keys() and are served by GET /media/{key}.
 * Created, replaced and deleted only through App\Cms\Media\MediaManager.
 *
 * @property int $id
 * @property string $ulid lower-case; changes when the file is replaced (new URLs)
 * @property string $driver database|filesystem
 * @property string $mime
 * @property string $extension
 * @property int $width displayed width (EXIF orientation applied)
 * @property int $height
 * @property int $size bytes of the main file
 * @property string|null $original_name
 * @property list<array{width: int, height: int, size: int, key: string}> $variants ascending, main excluded
 * @property string|null $alt_fr
 * @property string|null $alt_en
 * @property string|null $caption_fr
 * @property string|null $caption_en
 * @property int $focal_x 0–100 (%)
 * @property int $focal_y 0–100 (%)
 * @property string|null $service_slug
 * @property bool $in_gallery
 * @property int $position
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('media')]
#[Fillable(['alt_fr', 'alt_en', 'caption_fr', 'caption_en', 'focal_x', 'focal_y', 'service_slug', 'in_gallery', 'position', 'original_name'])]
class Media extends Model
{
    use FlushesCms;

    /** @var array<string, mixed> */
    protected $attributes = [
        'variants' => '[]',
        'focal_x' => 50,
        'focal_y' => 50,
        'in_gallery' => false,
        'position' => 0,
    ];

    /** Storage key of the main file: {ulid}.{extension}. */
    public function key(): string
    {
        return $this->ulid.'.'.$this->extension;
    }

    /**
     * Storage keys of the main file and of every variant.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [$this->key()];

        foreach ((array) $this->variants as $variant) {
            if (is_array($variant) && is_string($variant['key'] ?? null)) {
                $keys[] = $variant['key'];
            }
        }

        return $keys;
    }

    /** The public, JSON-safe view of this photo (URLs, srcset, texts). */
    public function item(): MediaItem
    {
        return MediaItem::fromModel($this);
    }

    /**
     * @return HasMany<MediaSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(MediaSlot::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Real booleans: with the Supabase pooler's emulated prepares an integer 1/0 cannot be
     * written to a boolean column (Laravel only rewrites true/false).
     *
     * @return Attribute<bool, mixed>
     */
    protected function inGallery(): Attribute
    {
        return Attribute::set(fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'in_gallery' => 'boolean',
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
            'focal_x' => 'integer',
            'focal_y' => 'integer',
            'position' => 'integer',
        ];
    }
}
