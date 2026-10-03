<?php

namespace Tests\Feature\Admin\Concerns;

use App\Cms\DatabaseMigrator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * A migration of a "later release" (docs/CMS.md §13 E23/F30): a migration file the database has not
 * run yet, in a temporary directory registered with the migrator, removed after the test.
 */
trait AddsLaterMigrations
{
    /** The later release's migration; its up() creates the table later_release_notes. */
    protected const LATER = '2099_01_01_000000_create_later_release_notes_table';

    private ?string $laterMigrationsDirectory = null;

    /** Adds the pending migration. With $failure, its up() throws that message instead. */
    protected function laterMigration(?string $failure = null): void
    {
        $this->laterMigrationsDirectory = storage_path('framework/testing/migrations-'.Str::lower(Str::random(12)));
        File::ensureDirectoryExists($this->laterMigrationsDirectory);

        $up = $failure === null
            ? "Schema::create('later_release_notes', function (Blueprint \$table): void { \$table->id(); });"
            : 'throw new RuntimeException('.var_export($failure, true).');';

        File::put($this->laterMigrationsDirectory.'/'.self::LATER.'.php', <<<PHP
            <?php

            use Illuminate\\Database\\Migrations\\Migration;
            use Illuminate\\Database\\Schema\\Blueprint;
            use Illuminate\\Support\\Facades\\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    {$up}
                }

                public function down(): void
                {
                    Schema::dropIfExists('later_release_notes');
                }
            };
            PHP);

        app('migrator')->path($this->laterMigrationsDirectory);
        app(DatabaseMigrator::class)->reset();
    }

    protected function tearDownAddsLaterMigrations(): void
    {
        if ($this->laterMigrationsDirectory !== null) {
            File::deleteDirectory($this->laterMigrationsDirectory);
            $this->laterMigrationsDirectory = null;
        }
    }
}
