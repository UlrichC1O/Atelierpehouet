<?php

namespace App\Models;

use App\Models\Concerns\FlushesCms;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A service as edited in the CMS (applied by App\Support\ServiceCatalog over the content files):
 * for a file service, the content leaves that differ from the file + meta/order/visibility overrides;
 * for a service created in the CMS (is_custom), everything.
 *
 * @property int $id
 * @property string $slug
 * @property bool $is_custom
 * @property bool $is_published
 * @property int|null $position display order (overrides the file's "order")
 * @property string|null $category
 * @property string|null $accent
 * @property string|null $icon
 * @property string|null $art_style
 * @property array{fr?: array<string, mixed>, en?: array<string, mixed>}|null $content
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'is_custom', 'is_published', 'position', 'category', 'accent', 'icon', 'art_style', 'content', 'updated_by'])]
class CmsService extends Model
{
    use FlushesCms;

    /**
     * Real booleans for Postgres with emulated prepares (see Media::inGallery()).
     *
     * @return Attribute<bool, mixed>
     */
    protected function isCustom(): Attribute
    {
        return Attribute::set(fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
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
            'is_custom' => 'boolean',
            'is_published' => 'boolean',
            'position' => 'integer',
            'content' => 'array',
        ];
    }
}
