<?php

namespace App\Artists\Concerns;

use App\Artists\ArtistDirectory;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Every write of an artist, artwork or exhibition drops the cached public snapshot of the artist
 * pages (docs/ARTISTS.md §3), so visitors see the change at their next page.
 *
 * Query-builder writes (->update(), ->delete() on a query) and database cascades fire no model
 * event: call app(ArtistDirectory::class)->flush() after them.
 */
trait FlushesArtists
{
    public static function bootFlushesArtists(): void
    {
        static::saved(static fn () => self::flushArtistDirectory());
        static::deleted(static fn () => self::flushArtistDirectory());
    }

    protected static function flushArtistDirectory(): void
    {
        $directory = app(ArtistDirectory::class);
        $directory->flush();

        // A concurrent request may cache the old rows again before this transaction commits:
        // flush once more after the commit (runs at once when no transaction is open).
        try {
            if (DB::transactionLevel() > 0) {
                DB::afterCommit(static fn () => $directory->flush());
            }
        } catch (Throwable) {
            // No transaction manager: the immediate flush above is all we can do.
        }
    }
}
