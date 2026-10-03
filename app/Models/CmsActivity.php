<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of the admin activity log (written by App\Cms\Activity::record()). It does not drop the
 * public CMS snapshot when saved (no FlushesCms): the log is not shown on the site.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action e.g. "auth.login", "media.upload"
 * @property string|null $subject e.g. "media:12"
 * @property string $summary
 * @property mixed $before state before the change, to restore a version (docs/CMS.md §13 F30)
 * @property mixed $after state after the change
 * @property Carbon|null $created_at
 */
#[Table('cms_activity')]
#[Fillable(['user_id', 'action', 'subject', 'summary', 'before', 'after'])]
class CmsActivity extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }
}
