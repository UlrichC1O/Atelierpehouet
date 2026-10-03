<?php

namespace App\Cms\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Files of replaced or deleted photos that stay servable for a while (docs/CMS.md §13 C12).
 *
 * Pages rendered before a replacement keep pointing at the old URLs as long as the cached CMS
 * snapshot lives (cms.cache.ttl, per server). MediaManager therefore retires the old keys — a row
 * (key, driver, retired_at) in media_retired — instead of deleting their files at once, GET
 * /media/{key} keeps serving a retired key for grace() seconds, and the next photo write purges the
 * entries older than that, files included.
 *
 * Before the media_retired migration has run (production right after a deploy), ready() is false and
 * MediaManager deletes the old files at once, as it did before.
 */
final class RetiredFiles
{
    public const TABLE = 'media_retired';

    public function __construct(private readonly MediaStorageManager $storages) {}

    /** Seconds a retired key stays servable: twice the snapshot lifetime, at least two minutes. */
    public static function grace(): int
    {
        return max(120, 2 * (int) config('cms.cache.ttl', 600));
    }

    /** Whether the media_retired table exists (a catalogue read: safe inside a transaction). */
    public function ready(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    /**
     * Records the keys of $driver as retired now. Meant for the transaction that replaces or deletes
     * the photo: errors are thrown, never swallowed (Postgres aborts the transaction on any error).
     *
     * @param  list<string>  $keys
     */
    public function retire(string $driver, array $keys): void
    {
        $now = now();
        $rows = array_map(fn (string $key): array => ['key' => $key, 'driver' => $driver, 'retired_at' => $now], array_values(array_unique($keys)));

        if ($rows !== []) {
            DB::table(self::TABLE)->insertOrIgnore($rows);
        }
    }

    /** Driver holding $key when it was retired less than grace() seconds ago, else null. Throws on database errors. */
    public function driverOf(string $key): ?string
    {
        $driver = DB::table(self::TABLE)
            ->where('key', $key)
            ->where('retired_at', '>=', now()->subSeconds(self::grace()))
            ->value('driver');

        return is_string($driver) ? $driver : null;
    }

    /**
     * Deletes the files retired more than grace() seconds ago, then their entries (an entry whose
     * file cannot be deleted stays for the next purge). Never throws.
     *
     * @return int entries purged
     */
    public function purge(): int
    {
        try {
            if (! $this->ready()) {
                return 0;
            }

            $expired = DB::table(self::TABLE)
                ->where('retired_at', '<', now()->subSeconds(self::grace()))
                ->orderBy('id')
                ->limit(500)
                ->get(['id', 'key', 'driver']);
        } catch (Throwable $e) {
            Log::warning('Retired photo files not purged: '.$e->getMessage());

            return 0;
        }

        $purged = [];

        foreach ($expired as $entry) {
            try {
                $storage = $this->storages->driver((string) $entry->driver);
            } catch (Throwable $e) {
                // A driver this server does not know (any more): its file is out of reach, the entry goes.
                Log::warning('Retired photo file '.$entry->key.' left on an unknown storage: '.$e->getMessage());
                $purged[] = $entry->id;

                continue;
            }

            try {
                $storage->delete((string) $entry->key);
                $purged[] = $entry->id;
            } catch (Throwable $e) {
                Log::warning('Retired photo file '.$entry->key.' not deleted: '.$e->getMessage());
            }
        }

        try {
            foreach (array_chunk($purged, 100) as $ids) {
                DB::table(self::TABLE)->whereIn('id', $ids)->delete();
            }
        } catch (Throwable $e) {
            Log::warning('Retired photo entries not purged: '.$e->getMessage());
        }

        return count($purged);
    }
}
