<?php

namespace App\Models;

use App\Models\Concerns\FlushesCms;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A site setting edited in the CMS (contact.*, socials.*, announcement.*): a row present wins over
 * the .env value, even when empty (read through cms()->setting()).
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'value', 'updated_by'])]
class Setting extends Model
{
    use FlushesCms;
}
