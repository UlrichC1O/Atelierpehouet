<?php

namespace App\Artists;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Where the artist pages use a photo of the CMS library (docs/ARTISTS.md §4.2). The photo library
 * merges this into its "where is this photo used" lists, its delete dialog and its "Sans emploi"
 * filter (docs/CMS.md §7.6).
 *
 * Never throws: on any error or missing table the answer is [] (or false for deletable()).
 */
final class MediaUsage
{
    /** Artist tables and the column holding a photo id. */
    public const COLUMNS = [
        'artists' => 'portrait_media_id',
        'artworks' => 'media_id',
        'exhibitions' => 'media_id',
    ];

    /**
     * The places of the artist pages showing a photo, with the admin page that edits each of them.
     *
     * @return list<array{label: string, url: string}>
     */
    public static function for(int $mediaId): array
    {
        try {
            if (! Schema::hasTable('artists')) {
                return [];
            }

            $usage = [];
            $locale = app()->getLocale();

            $artists = DB::table('artists')->where('portrait_media_id', $mediaId)->orderBy('name')->orderBy('id')->get(['id', 'name']);

            foreach ($artists as $row) {
                $usage[] = [
                    'label' => self::line('portrait', ['name' => (string) $row->name]),
                    'url' => self::url('admin.artists.edit', [(int) $row->id], 'admin/artistes/'.$row->id),
                ];
            }

            foreach (['artworks' => 'oeuvres', 'exhibitions' => 'expositions'] as $table => $segment) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $rows = DB::table($table)
                    ->join('artists', 'artists.id', '=', $table.'.artist_id')
                    ->where($table.'.media_id', $mediaId)
                    ->orderBy('artists.name')
                    ->orderBy($table.'.id')
                    ->get([$table.'.id', $table.'.artist_id', $table.'.title_fr', $table.'.title_en', 'artists.name']);

                foreach ($rows as $row) {
                    $title = $locale !== 'fr' && is_string($row->title_en) && trim($row->title_en) !== '' ? $row->title_en : (string) $row->title_fr;
                    $usage[] = [
                        'label' => self::line($table === 'artworks' ? 'artwork' : 'exhibition', ['title' => $title, 'name' => (string) $row->name]),
                        'url' => self::url(
                            'admin.artists.'.$table.'.edit',
                            [(int) $row->artist_id, (int) $row->id],
                            'admin/artistes/'.$row->artist_id.'/'.$segment.'/'.$row->id,
                        ),
                    ];
                }
            }

            return $usage;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Every photo id the artist pages reference (portraits, artworks, exhibitions), ascending.
     *
     * @return list<int>
     */
    public static function usedIds(): array
    {
        $ids = [];

        foreach (self::COLUMNS as $table => $column) {
            try {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                foreach (DB::table($table)->whereNotNull($column)->distinct()->pluck($column) as $id) {
                    $ids[(int) $id] = true;
                }
            } catch (Throwable) {
                // An unreadable table adds nothing.
            }
        }

        $ids = array_keys($ids);
        sort($ids);

        return $ids;
    }

    /**
     * True when the photo may be deleted with what uses it: it exists, is not in the public gallery,
     * not attached to a service, not in a photo spot, not a free page's cover, and no artist row
     * references it except the ones in $ignore (the rows being deleted).
     *
     * @param  array{artists?: list<int>, artworks?: list<int>, exhibitions?: list<int>}  $ignore  row ids per table
     */
    public static function deletable(int $mediaId, array $ignore = []): bool
    {
        try {
            if (! Schema::hasTable('media')) {
                return false;
            }

            $media = DB::table('media')->where('id', $mediaId)->first(['id', 'in_gallery', 'service_slug']);

            if ($media === null || self::bool($media->in_gallery) || (is_string($media->service_slug) && trim($media->service_slug) !== '')) {
                return false;
            }

            if (Schema::hasTable('media_slots') && DB::table('media_slots')->where('media_id', $mediaId)->exists()) {
                return false;
            }

            if (Schema::hasTable('custom_pages') && DB::table('custom_pages')->where('cover_media_id', $mediaId)->exists()) {
                return false;
            }

            foreach (self::COLUMNS as $table => $column) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $query = DB::table($table)->where($column, $mediaId);
                $skip = array_values(array_filter(array_map('intval', (array) ($ignore[$table] ?? [])), fn (int $id): bool => $id > 0));

                if ($skip !== []) {
                    $query->whereNotIn('id', $skip);
                }

                if ($query->exists()) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The usage line (admin_artists.usage.{key}); before that language line exists, the bare names.
     *
     * @param  array<string, string>  $replace
     */
    private static function line(string $key, array $replace): string
    {
        $line = 'admin_artists.usage.'.$key;
        $text = Lang::has($line) ? __($line, $replace) : null;

        return is_string($text) ? $text : implode(' — ', array_reverse($replace));
    }

    /**
     * @param  list<int>  $parameters
     */
    private static function url(string $route, array $parameters, string $path): string
    {
        return Route::has($route) ? route($route, $parameters) : url($path);
    }

    /** Booleans as returned by SQLite (0/1), Postgres (true/false) or a stringifying driver ('t'/'f'). */
    private static function bool(mixed $value): bool
    {
        return is_string($value)
            ? in_array(strtolower($value), ['1', 't', 'true', 'y', 'yes', 'on'], true)
            : (bool) $value;
    }
}
