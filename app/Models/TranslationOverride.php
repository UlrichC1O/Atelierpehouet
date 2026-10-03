<?php

namespace App\Models;

use App\Models\Concerns\FlushesCms;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A text of the site changed in the CMS: replaces the leaf `key` (dot notation) of
 * lang/{locale}/{group}.php — see App\Cms\OverridingTranslationLoader.
 *
 * @property int $id
 * @property string $locale
 * @property string $group
 * @property string $key dot path inside the group, e.g. "hero.title"
 * @property string $value
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['locale', 'group', 'key', 'value', 'updated_by'])]
class TranslationOverride extends Model
{
    use FlushesCms;

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
