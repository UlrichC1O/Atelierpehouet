<?php

namespace Tests\Feature\Cms;

use App\Cms\DatabaseHealth;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;
use Throwable;

/** The database circuit breaker (docs/CMS.md §13 A1): available() / failed() / attempt(). */
class DatabaseHealthTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    private function health(): DatabaseHealth
    {
        return app(DatabaseHealth::class);
    }

    /** The exception a query on an unreachable database throws. */
    private function unreachable(): Throwable
    {
        $this->breakDatabase();

        try {
            DB::table('settings')->count();
        } catch (Throwable $e) {
            return $e;
        } finally {
            config(['database.default' => 'sqlite']);
        }

        $this->fail('the broken database answered');
    }

    public function test_attempt_runs_the_callback_while_the_database_is_available(): void
    {
        $this->assertTrue($this->health()->available());
        $this->assertSame(3, $this->health()->attempt(fn () => DB::table('users')->count() + 3, 0));
        $this->assertSame('fallback', $this->health()->attempt(fn () => throw new QueryException('sqlite', 'select 1', [], new \PDOException('no such table: nope')), 'fallback'));
    }

    public function test_an_outage_opens_the_breaker_for_the_retry_window(): void
    {
        Log::spy();
        $calls = 0;

        $this->assertNull($this->health()->attempt(function () use (&$calls) {
            $calls++;

            throw new RuntimeException('could not connect to server: Connection refused');
        }));

        $this->assertFalse($this->health()->available());
        $this->assertTrue($this->health()->tripped());
        $this->assertSame([], $this->health()->attempt(function () use (&$calls) {
            $calls++;

            return ['rows'];
        }, []), 'skipped while open');
        $this->assertSame(1, $calls);

        // The next request: the in-request flag is gone, the cached marker keeps the breaker open.
        $this->health()->reset();
        $this->assertFalse($this->health()->tripped());
        $this->assertFalse($this->health()->available());

        $this->travel(config('cms.cache.retry') + 1)->seconds();
        $this->health()->reset();
        $this->assertTrue($this->health()->available(), 'half-open after the retry window');

        Log::shouldHaveReceived('notice')->withArgs(fn (string $message): bool => str_contains($message, 'circuit breaker'))->once();
    }

    public function test_errors_the_database_answered_do_not_open_it(): void
    {
        $this->assertSame('fallback', $this->health()->attempt(fn () => DB::table('table_qui_n_existe_pas')->get(), 'fallback'));
        $this->assertTrue($this->health()->available(), 'a missing table is not an outage');
        $this->assertNull(cache()->get(DatabaseHealth::KEY));

        $error = rescue(fn () => DB::table('table_qui_n_existe_pas')->get(), fn (Throwable $e) => $e, false);
        $this->assertFalse(DatabaseHealth::isOutage($error));
    }

    public function test_connection_failures_and_unknown_errors_count_as_outages(): void
    {
        $this->assertTrue(DatabaseHealth::isOutage($this->unreachable()), 'the connection could not be opened');
        $this->assertTrue(DatabaseHealth::isOutage(new RuntimeException('boom')), 'when in doubt');
        $this->assertTrue(DatabaseHealth::isOutage(new QueryException('sqlite', 'select 1', [], new \PDOException('SQLSTATE[08006] could not connect'))));

        $timeout = new \PDOException('SQLSTATE[57014]: canceling statement due to statement timeout');
        $timeout->errorInfo = ['57014', 7, 'canceling statement due to statement timeout'];
        $this->assertTrue(DatabaseHealth::isOutage(new QueryException('sqlite', 'select 1', [], $timeout)), 'statement timeout');
    }

    public function test_recovered_closes_it_and_the_marker_lives_in_the_cms_cache_store(): void
    {
        config(['cache.stores.cms_test' => ['driver' => 'array'], 'cms.cache.store' => 'cms_test']);

        $this->health()->failed(new RuntimeException('could not connect'));
        $this->assertSame(now()->getTimestamp() + config('cms.cache.retry'), cache()->store('cms_test')->get(DatabaseHealth::KEY));
        $this->assertNull(cache()->get(DatabaseHealth::KEY));

        $this->health()->recovered();

        $this->assertTrue($this->health()->available());
        $this->assertNull(cache()->store('cms_test')->get(DatabaseHealth::KEY));
    }

    public function test_a_long_running_process_tries_again_after_the_retry_window(): void
    {
        $this->health()->failed(new RuntimeException('could not connect'));
        $this->assertTrue($this->health()->tripped());

        $this->travel(config('cms.cache.retry') + 1)->seconds();

        $this->assertFalse($this->health()->tripped(), 'no new request needed');
        $this->assertTrue($this->health()->available());
        $this->assertSame('rows', $this->health()->attempt(fn () => 'rows'));
    }

    public function test_each_request_starts_with_a_fresh_flag(): void
    {
        $this->health()->failed(new RuntimeException('could not connect'));
        cache()->forget(DatabaseHealth::KEY); // e.g. expired, or another server

        $this->get('/robots.txt')->assertOk();

        $this->assertFalse($this->health()->tripped());
        $this->assertTrue($this->health()->available());
    }
}
