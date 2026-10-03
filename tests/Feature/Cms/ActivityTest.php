<?php

namespace Tests\Feature\Cms;

use App\Cms\Activity;
use App\Cms\DatabaseHealth;
use App\Models\CmsActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/** The admin activity log never fails the action it records (docs/CMS.md §4.6, §13 B7/F30). */
class ActivityTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    public function test_actions_are_recorded_with_the_current_administrator(): void
    {
        $user = $this->admin();

        Activity::record('media.upload', 'Photo « atelier.webp » ajoutée', 'media:12');

        $line = CmsActivity::query()->sole();
        $this->assertSame([$user->id, 'media.upload', 'media:12', 'Photo « atelier.webp » ajoutée'], [$line->user_id, $line->action, $line->subject, $line->summary]);
        $this->assertNotNull($line->created_at);
    }

    public function test_long_values_are_cut_and_guests_are_anonymous(): void
    {
        Activity::record(str_repeat('a', 80), str_repeat('é', 300), str_repeat('s', 200));

        $line = CmsActivity::query()->sole();
        $this->assertNull($line->user_id);
        $this->assertSame([60, 255, 160], [mb_strlen($line->action), mb_strlen($line->summary), mb_strlen($line->subject)]);
    }

    public function test_recording_never_throws(): void
    {
        Log::spy();
        Schema::drop('cms_activity');

        Activity::record('auth.login', 'Connexion');

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'auth.login'))->once();
    }

    public function test_versions_before_and_after_are_kept_as_json(): void
    {
        $this->admin();

        Activity::record('texts.update', 'Textes « Accueil » modifiés', 'texts:home',
            before: ['fr' => ['hero.title' => "L’ancien\0 titre"]],
            after: ['fr' => ['hero.title' => 'Le nouveau titre']],
        );
        Activity::record('settings.update', 'Réglages', after: 'texte seul');

        [$texts, $settings] = CmsActivity::query()->orderBy('id')->get()->all();
        $this->assertSame(['fr' => ['hero.title' => 'L’ancien titre']], $texts->before);
        $this->assertSame(['fr' => ['hero.title' => 'Le nouveau titre']], $texts->after);
        $this->assertNull($settings->before);
        $this->assertSame('texte seul', $settings->after);
    }

    public function test_a_version_too_big_is_dropped_but_the_line_is_kept(): void
    {
        Log::spy();

        Activity::record('pages.update', 'Page modifiée', before: ['body' => str_repeat('a', 600 * 1024)]);

        $line = CmsActivity::query()->sole();
        $this->assertNull($line->before);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'version not kept'))->once();
    }

    public function test_strings_are_cleaned_for_postgres(): void
    {
        Activity::record("media\0.upload", "Photo\0 « \xC3\x28 » ajoutée", "media:\0".'12');

        $line = CmsActivity::query()->sole();
        $this->assertSame(['media.upload', 'Photo « ?( » ajoutée', 'media:12'], [$line->action, $line->summary, $line->subject]);
    }

    public function test_inside_a_transaction_the_line_waits_for_the_commit(): void
    {
        DB::transaction(function (): void {
            Activity::record('services.reorder', 'Ordre des services');
            $this->assertSame(0, CmsActivity::query()->count(), 'not written inside the transaction');
        });
        $this->assertSame(1, CmsActivity::query()->count());

        rescue(fn () => DB::transaction(function (): void {
            Activity::record('services.reorder', 'Annulé');

            throw new RuntimeException('rollback');
        }), null, false);
        $this->assertSame(1, CmsActivity::query()->count(), 'a rolled back action is not logged');
    }

    public function test_nothing_is_written_while_the_database_is_unreachable(): void
    {
        app(DatabaseHealth::class)->failed(new RuntimeException('could not connect'));

        Activity::record('auth.login', 'Connexion');

        $this->assertSame(0, CmsActivity::query()->count());
    }
}
