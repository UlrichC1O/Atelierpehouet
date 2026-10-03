<?php

namespace App\Models;

use App\Models\Concerns\FlushesCms;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A free page created in the CMS, served at /{slug} (body in Markdown, App\Cms\Markdown).
 *
 * @property int $id
 * @property string $slug
 * @property string $title_fr
 * @property string|null $title_en
 * @property string|null $body_fr
 * @property string|null $body_en
 * @property string|null $meta_fr
 * @property string|null $meta_en
 * @property int|null $cover_media_id
 * @property bool $is_published
 * @property bool $in_footer
 * @property int $position
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'title_fr', 'title_en', 'body_fr', 'body_en', 'meta_fr', 'meta_en', 'cover_media_id', 'is_published', 'in_footer', 'position', 'updated_by'])]
class CustomPage extends Model
{
    use FlushesCms;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_published' => false,
        'in_footer' => true,
        'position' => 0,
    ];

    /**
     * @return BelongsTo<Media, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /** Title in the given language (French when the translation is empty). */
    public function title(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $title = $locale === 'fr' ? null : $this->getAttribute('title_'.$locale);

        return is_string($title) && trim($title) !== '' ? $title : $this->title_fr;
    }

    /**
     * Real booleans for Postgres with emulated prepares (see Media::inGallery()).
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
    protected function inFooter(): Attribute
    {
        return Attribute::set(fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cover_media_id' => 'integer',
            'is_published' => 'boolean',
            'in_footer' => 'boolean',
            'position' => 'integer',
        ];
    }
}
