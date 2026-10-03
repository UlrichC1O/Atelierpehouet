<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One stored file of the "database" media driver (App\Cms\Media\DatabaseMediaStorage). Saving it does
 * not drop the public CMS snapshot (no FlushesCms): the Media row written with it does.
 *
 * @property int $id
 * @property string $key e.g. 01j9…xyz.webp or 01j9…xyz-480.webp
 * @property string $mime
 * @property int $size bytes (decoded)
 * @property string $contents base64
 * @property Carbon|null $created_at
 */
#[Fillable(['key', 'mime', 'size', 'contents'])]
#[Hidden(['contents'])]
class MediaFile extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
