<?php

namespace App\Cms;

use App\Models\CmsActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The admin activity log (docs/CMS.md §4.6, §13 B7/F30), shown on the dashboard. Recording never
 * throws: a missing table or a database hiccup must not fail the action being logged.
 *
 * A best-effort write never runs inside a transaction (a failed INSERT aborts a whole Postgres
 * transaction, even when the exception is caught): inside one, the line is written after the
 * commit — and dropped on rollback, the action never happened. It skips the database while the
 * circuit breaker (DatabaseHealth) is open.
 */
final class Activity
{
    /** Largest JSON kept for "before" / "after" (a version too big is not kept, the line is). */
    private const MAX_VERSION_BYTES = 512 * 1024;

    /**
     * @param  string  $action  dotted verb, e.g. "auth.login", "media.upload", "texts.update"
     * @param  string  $summary  human sentence (already translated)
     * @param  string|null  $subject  what it is about, e.g. "media:12", "service:sculpture"
     * @param  mixed  $before  the state before the change (texts, service content, page, settings, photo
     *                         metadata…) — any JSON-encodable value; lets the admin restore a version
     * @param  mixed  $after  the state after the change
     */
    public static function record(string $action, string $summary, ?string $subject = null, mixed $before = null, mixed $after = null): void
    {
        try {
            if (! app(DatabaseHealth::class)->available()) {
                return;
            }

            $userId = rescue(fn () => Auth::id(), null, false);

            $attributes = [
                'user_id' => is_numeric($userId) ? (int) $userId : null,
                'action' => (string) Text::column($action, 60),
                'subject' => Text::column($subject, 160),
                'summary' => (string) Text::column($summary, 255),
                'before' => self::version($before, $action),
                'after' => self::version($after, $action),
            ];
        } catch (Throwable $e) {
            self::warn($action, $e);

            return;
        }

        $write = static fn () => self::write($attributes);

        try {
            DB::afterCommit($write); // runs at once when no transaction is open
        } catch (Throwable) {
            $write(); // no transaction manager (or no connection): write now, it never throws
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function write(array $attributes): void
    {
        $health = app(DatabaseHealth::class);

        if (! $health->available()) {
            return;
        }

        try {
            // Only the versions present: the line is still recorded on a database whose
            // before/after columns are not migrated yet.
            CmsActivity::query()->create(array_filter($attributes, fn (mixed $value, string $key): bool => $value !== null || ! in_array($key, ['before', 'after'], true), ARRAY_FILTER_USE_BOTH));
        } catch (Throwable $e) {
            $health->failed($e);
            self::warn((string) $attributes['action'], $e);
        }
    }

    /** A storable version (strings scrubbed, NUL bytes removed), or null when absent or too big. */
    private static function version(mixed $value, string $action): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = Text::clean($value);
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($json === false || strlen($json) > self::MAX_VERSION_BYTES) {
            Log::warning('Admin activity ('.$action.'): version not kept, '.($json === false ? 'not encodable' : strlen($json).' bytes').'.');

            return null;
        }

        return $value;
    }

    private static function warn(string $action, Throwable $e): void
    {
        rescue(fn () => Log::warning('Admin activity not recorded ('.$action.'): '.$e->getMessage()), null, false);
    }
}
