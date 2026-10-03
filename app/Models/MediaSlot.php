<?php

namespace App\Models;

use App\Models\Concerns\FlushesCms;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Which photo fills a photo spot of the pages (App\Cms\Slots: "home.feature", "service.{slug}.cover"…).
 *
 * @property int $id
 * @property string $slot
 * @property int $media_id
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slot', 'media_id', 'updated_by'])]
class MediaSlot extends Model
{
    use FlushesCms;

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_id' => 'integer',
        ];
    }
}
