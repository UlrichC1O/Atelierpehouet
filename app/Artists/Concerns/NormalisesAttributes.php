<?php

namespace App\Artists\Concerns;

/**
 * Values the artist models write are safe on every database (docs/ARTISTS.md §12.5, docs/CMS.md §13 B7):
 * before each save, boolean columns hold PHP bools (Postgres behind the Supabase pooler runs with
 * emulated prepares and refuses 0/1 for a boolean column), and every string is valid UTF-8 without
 * NUL bytes, cut to its column size (strings longer than a varchar column fail on Postgres).
 *
 * The model lists its columns in two constants: BOOLEAN_COLUMNS (list<string>) and STRING_COLUMNS
 * (column ⇒ maximum length in characters, null for text columns).
 */
trait NormalisesAttributes
{
    protected static function bootNormalisesAttributes(): void
    {
        static::saving(function (self $model): void {
            $model->normaliseAttributes();
        });
    }

    /** Normalises the attributes about to be written (see the trait's description). */
    public function normaliseAttributes(): void
    {
        foreach (static::BOOLEAN_COLUMNS as $column) {
            if (array_key_exists($column, $this->attributes) && ! is_bool($this->attributes[$column]) && $this->attributes[$column] !== null) {
                $this->attributes[$column] = filter_var($this->attributes[$column], FILTER_VALIDATE_BOOLEAN);
            }
        }

        foreach (static::STRING_COLUMNS as $column => $length) {
            $value = $this->attributes[$column] ?? null;

            if (is_string($value)) {
                $value = str_replace("\0", '', mb_scrub($value, 'UTF-8'));
                $this->attributes[$column] = $length === null ? $value : mb_substr($value, 0, $length);
            }
        }
    }
}
