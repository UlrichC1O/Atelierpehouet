<?php

namespace App\Artists;

use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Choices of the no-JS `<select>` fallback of the photo fields (docs/ARTISTS.md §4.5, §6.2): the most
 * recent photos of the CMS library.
 */
final class MediaOptions
{
    /** Photos listed at most (plus the selected one when it is older). */
    public const LIMIT = 500;

    /**
     * id ⇒ "#12 · atelier.jpg (1600×1200)" for the most recent photos, newest first; $selected is
     * appended when it is older. [] when the library (class or table) is missing.
     *
     * @return array<int, string>
     */
    public static function list(?int $selected = null): array
    {
        if (! class_exists(Media::class)) {
            return [];
        }

        try {
            if (! Schema::hasTable('media')) {
                return [];
            }

            $columns = ['id', 'original_name', 'alt_fr', 'width', 'height'];
            $rows = DB::table('media')->orderByDesc('id')->limit(self::LIMIT)->get($columns)->all();

            if ($selected !== null && $selected > 0 && ! in_array($selected, array_map(fn (object $row): int => (int) $row->id, $rows), true)) {
                $row = DB::table('media')->where('id', $selected)->first($columns);

                if ($row !== null) {
                    $rows[] = $row;
                }
            }

            $options = [];

            foreach ($rows as $row) {
                $options[(int) $row->id] = self::label($row);
            }

            return $options;
        } catch (Throwable) {
            return [];
        }
    }

    private static function label(object $row): string
    {
        $name = null;

        foreach ([$row->original_name ?? null, $row->alt_fr ?? null] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $name = Str::limit(trim(preg_replace('/\s+/u', ' ', $candidate) ?? $candidate), 60, '…');

                break;
            }
        }

        $size = '('.(int) $row->width.'×'.(int) $row->height.')';

        return '#'.(int) $row->id.' · '.($name === null ? $size : $name.' '.$size);
    }
}
