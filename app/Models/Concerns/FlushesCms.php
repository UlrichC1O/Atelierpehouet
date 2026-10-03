<?php

namespace App\Models\Concerns;

use App\Cms\Cms;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Every write of a model the public snapshot is made of — TranslationOverride, Media, MediaSlot,
 * CmsService, Setting, CustomPage (docs/CMS.md §3, §13 A4) — drops the cached snapshot and the
 * in-process memos (cms()->flush()), so visitors see the change at their next page (on this server;
 * other servers within cms.cache.ttl seconds). Inside a transaction it flushes again after the
 * commit: a concurrent request may have cached the old rows in between.
 *
 * Query-builder writes (upserts, ->update() / ->delete() on a query, reorders) fire no model event:
 * call cms()->flush() after them (DB::afterCommit(fn () => cms()->flush()) inside a transaction).
 */
trait FlushesCms
{
    public static function bootFlushesCms(): void
    {
        static::saved(static fn () => self::flushCmsSnapshot());
        static::deleted(static fn () => self::flushCmsSnapshot());
    }

    protected static function flushCmsSnapshot(): void
    {
        $cms = app(Cms::class);
        $cms->flush();

        // A concurrent request may cache the old rows again before this transaction commits:
        // flush once more after the commit (runs at once when no transaction is open).
        try {
            if (DB::transactionLevel() > 0) {
                DB::afterCommit(static fn () => $cms->flush());
            }
        } catch (Throwable) {
            // No transaction manager: the immediate flush above is all we can do.
        }
    }
}
