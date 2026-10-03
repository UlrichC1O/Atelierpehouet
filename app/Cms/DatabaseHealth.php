<?php

namespace App\Cms;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

/**
 * Circuit breaker of the database (docs/CMS.md §13 A1), a container singleton.
 *
 * An unreachable database (Supabase paused, network down) costs every query a connect timeout
 * (DB_TIMEOUT × resolved addresses). The first such failure in a request opens the breaker — an
 * in-request flag plus a cached marker (its expiry time), both kept cms.cache.retry seconds — during
 * which every public database touchpoint (CMS snapshot, ApplyCms, contact form, activity log,
 * free-page lookups, the artist pages…) skips the database and serves its fallback (last-good copy,
 * file defaults). Then the next touchpoint tries the database again.
 *
 * Only outages open it: an error the database server answered (a missing table, a broken query) is
 * not an outage, and cutting the whole site off from a working database would hide it.
 *
 * API (the artist pages code against it — keep it): available(), failed(), attempt().
 */
final class DatabaseHealth
{
    use DetectsLostConnections;

    /** Cache key of the "unreachable" marker, in config('cms.cache.store'). */
    public const KEY = 'cms.database.unreachable';

    /** SQLSTATE classes that mean "unusable" even on an established connection (connection, resources, operator intervention, system). */
    private const OUTAGE_STATES = ['08', '53', '57', '58'];

    /** Seconds a "closed" reading of the cached marker is trusted before reading it again. */
    private const RECHECK_SECONDS = 5;

    /** Until when (timestamp) the outage met by this process keeps the breaker open, or null. */
    private ?int $trippedUntil = null;

    /** Until when (timestamp) the cached marker says the breaker is open, or null. */
    private ?int $openUntil = null;

    /** When the cached marker was last read and found closed, or null. */
    private ?int $closedAt = null;

    /** False while the breaker is open (an outage met here, or one cached by any request, within the retry window). */
    public function available(): bool
    {
        $now = self::now();

        if ($this->tripped() || ($this->openUntil !== null && $now < $this->openUntil)) {
            return false;
        }

        if ($this->closedAt !== null && $now - $this->closedAt < self::RECHECK_SECONDS) {
            return true;
        }

        try {
            $until = $this->store()?->get(self::KEY);
        } catch (Throwable) {
            $until = null;
        }

        if (is_int($until) && $until > $now) {
            $this->openUntil = $until;

            return false;
        }

        $this->openUntil = null;
        $this->closedAt = $now;

        return true;
    }

    /** Opens the breaker for cms.cache.retry seconds after a database outage (errors the server answered are ignored). */
    public function failed(Throwable $e): void
    {
        if ($this->tripped() || ! self::isOutage($e)) {
            return;
        }

        $retry = max(1, (int) config('cms.cache.retry', 30));
        $this->trippedUntil = $this->openUntil = self::now() + $retry;
        $this->closedAt = null;

        try {
            $opened = $this->store()?->add(self::KEY, $this->openUntil, $retry) ?? true;
        } catch (Throwable) {
            $opened = true; // the cache is down as well: log every time rather than never
        }

        // A notice: the reader that met the outage logs its own warning (CMS data, artist pages…).
        if ($opened) {
            Log::notice('Database unreachable, skipped for '.$retry.' s (circuit breaker): '.$e->getMessage());
        }
    }

    /**
     * Runs $callback unless the breaker is open; on any Throwable reports it to failed() and
     * returns $fallback (also returned while the breaker is open).
     */
    public function attempt(callable $callback, mixed $fallback = null): mixed
    {
        if (! $this->available()) {
            return $fallback;
        }

        try {
            return $callback();
        } catch (Throwable $e) {
            $this->failed($e);

            return $fallback;
        }
    }

    /** True when this request (process) met a database outage within the retry window, whatever the cache says. */
    public function tripped(): bool
    {
        return $this->trippedUntil !== null && self::now() < $this->trippedUntil;
    }

    /** Closes the breaker: the database answered again (an admin read, a migration). Never throws. */
    public function recovered(): void
    {
        $this->trippedUntil = $this->openUntil = null;
        $this->closedAt = self::now();

        try {
            $this->store()?->forget(self::KEY);
        } catch (Throwable) {
            // The marker expires by itself.
        }
    }

    /** Forgets what belongs to the previous request (the cached marker is read again). */
    public function reset(): void
    {
        $this->trippedUntil = $this->openUntil = $this->closedAt = null;
    }

    /**
     * Whether $e means the database cannot be used right now (unreachable, lost, overloaded) rather
     * than an error answered by a working server. Unknown errors count as outages: when in doubt,
     * protect the visitors from waiting on timeouts.
     */
    public static function isOutage(Throwable $e): bool
    {
        return ! (new self)->answered($e);
    }

    /** The database server received the query and answered with an error. */
    private function answered(Throwable $e): bool
    {
        if (! $e instanceof QueryException || $this->causedByLostConnection($e)) {
            return false;
        }

        $state = (string) ($e->errorInfo[0] ?? $e->getCode());

        if (preg_match('/^[0-9A-Z]{5}$/', $state) !== 1 && preg_match('/SQLSTATE\[([0-9A-Z]{5})\]/', $e->getMessage(), $match) === 1) {
            $state = $match[1];
        }

        if (in_array(substr($state, 0, 2), self::OUTAGE_STATES, true)) {
            return false;
        }

        // The connection was established: the failure happened in the query, not while connecting.
        try {
            $connection = DB::connection($e->getConnectionName());

            return $connection->getRawPdo() instanceof PDO || $connection->getRawReadPdo() instanceof PDO;
        } catch (Throwable) {
            return false;
        }
    }

    /** Timestamp of the application clock (tests travel in time). */
    private static function now(): int
    {
        return Carbon::now()->getTimestamp();
    }

    private function store(): ?Repository
    {
        try {
            return Cache::store(config('cms.cache.store') ?: null);
        } catch (Throwable) {
            return null;
        }
    }
}
