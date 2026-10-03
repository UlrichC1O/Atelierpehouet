<?php

namespace Tests\Feature\Admin;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithCms;
use Tests\Feature\Admin\Concerns\AddsLaterMigrations;
use Tests\TestCase;

/**
 * The admin's home (docs/CMS.md §7.2, minimal version, §13 F30): greeting, the pending database
 * updates with their button, the unread messages and a card for every section.
 */
class DashboardTest extends TestCase
{
    use AddsLaterMigrations, InteractsWithCms, RefreshDatabase;

    public function test_it_greets_the_signed_in_user_by_name(): void
    {
        $this->admin(['name' => 'Awa Pehouet']);

        $this->travelTo(now()->setTime(9, 0));
        $this->get('/admin')->assertOk()
            ->assertSee(__('admin.dashboard.greeting.morning', ['name' => 'Awa Pehouet']))
            ->assertSee('<title>'.__('admin.dashboard.title'), false);

        $this->travelTo(now()->setTime(21, 0));
        $this->get('/admin')->assertOk()->assertSee(__('admin.dashboard.greeting.evening', ['name' => 'Awa Pehouet']));

        $this->get('/admin?lang=en')->assertOk()->assertSee(__('admin.dashboard.greeting.evening', ['name' => 'Awa Pehouet'], 'en'));
    }

    public function test_cards_link_to_every_admin_section(): void
    {
        $this->admin();

        $response = $this->get('/admin')->assertOk();

        foreach (['admin.texts.index', 'admin.services.index', 'admin.media.index', 'admin.gallery.index', 'admin.pages.index', 'admin.messages.index', 'admin.settings.edit', 'admin.account.edit', 'admin.maintenance'] as $route) {
            $response->assertSee('href="'.route($route).'"', false);
        }

        foreach (['texts', 'services', 'media', 'gallery', 'pages', 'messages', 'settings', 'account', 'maintenance'] as $section) {
            $response->assertSee('id="dashboard-'.$section.'"', false)->assertSee(__('admin.dashboard.sections.'.$section));
        }

        if (Route::has('admin.artists.index')) {
            $response->assertSee('id="dashboard-artists"', false)->assertSee(route('admin.artists.index'), false);
        }
    }

    public function test_unread_messages_are_counted(): void
    {
        $this->admin();
        $this->message();
        $this->message();
        $this->message(['read_at' => now()]);

        $this->get('/admin')->assertOk()
            ->assertSee(__('admin.dashboard.stats.unread'))
            ->assertSee(trans_choice('admin.nav.unread', 2, ['count' => 2]));
    }

    public function test_it_still_opens_when_the_messages_cannot_be_counted(): void
    {
        $this->admin();
        Schema::drop('contact_messages');

        $this->get('/admin')->assertOk()->assertSee(__('admin.dashboard.stats.unread'));
    }

    public function test_no_banner_while_the_database_is_up_to_date(): void
    {
        $this->admin();

        $this->get('/admin')->assertOk()
            ->assertDontSee('id="dashboard-pending"', false)
            ->assertDontSee(route('admin.maintenance.migrate'), false);
    }

    public function test_a_banner_offers_the_update_while_migrations_are_pending(): void
    {
        $this->admin();
        $this->laterMigration();

        $this->get('/admin')->assertOk()
            ->assertSee('id="dashboard-pending"', false)
            ->assertSee(trans_choice('admin.maintenance.migrations.pending', 1, ['count' => 1]))
            ->assertSee('action="'.route('admin.maintenance.migrate').'"', false)
            ->assertSee(__('admin.maintenance.migrations.run'));

        $this->from('/admin')->post('/admin/maintenance/base')->assertRedirect(route('admin.maintenance'));

        $this->assertTrue(Schema::hasTable('later_release_notes'));
        $this->get('/admin')->assertOk()->assertDontSee('id="dashboard-pending"', false);
    }

    /** @param  array<string, mixed>  $attributes */
    private function message(array $attributes = []): ContactMessage
    {
        return ContactMessage::query()->create($attributes + [
            'name' => 'Client', 'email' => 'client@pehouet.test', 'message' => 'Bonjour', 'locale' => 'fr',
        ]);
    }
}
