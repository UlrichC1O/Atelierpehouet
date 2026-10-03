<?php

namespace App\Artists\Concerns;

/**
 * Text fields stored in pairs, `{field}_fr` and `{field}_en` (docs/ARTISTS.md §3): French is the
 * main language, English falls back to it.
 */
trait HasLocalizedText
{
    /**
     * The `{field}_{locale}` value when it is filled, else the French one; null when both are blank.
     *
     * Call it as a method: `$artist->text('bio')` (there is no `text` attribute).
     */
    public function text(string $field, ?string $locale = null): ?string
    {
        $attributes = $this->getAttributes();

        foreach (array_unique([$locale ?? app()->getLocale(), 'fr']) as $candidate) {
            $value = $attributes[$field.'_'.$candidate] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
