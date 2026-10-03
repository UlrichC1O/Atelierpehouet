<?php

namespace Tests\Feature\Cms;

use App\Cms\DatabaseHealth;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Concerns\InteractsWithCms;
use Tests\TestCase;

/**
 * The quote form during database outages (docs/CMS.md §13 A6): the e-mail goes first, the request is
 * stored only while the circuit breaker is closed, and the notification address set in the CMS
 * survives in the last-good snapshot.
 */
class ContactResilienceTest extends TestCase
{
    use InteractsWithCms, RefreshDatabase;

    /** @return array<string, string> */
    private function request(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Awa Diallo',
            'email' => 'awa@pehouet.test',
            'message' => 'Bonjour, une fresque pour la cour de notre école, est-ce possible ?',
            'consent' => '1',
        ];
    }

    public function test_an_unreachable_database_still_sends_the_request_by_e_mail(): void
    {
        Log::spy();
        Mail::fake();
        config(['atelier.contact.notify' => 'atelier@pehouet.test']);
        $this->breakDatabase();

        $this->post('/contact', $this->request())
            ->assertRedirect(route('contact').'#contact-form')
            ->assertSessionHas('status', 'sent');

        Mail::assertSent(ContactMessageReceived::class, fn (ContactMessageReceived $mail): bool => $mail->hasTo('atelier@pehouet.test')
            && $mail->contactMessage->name === 'Awa Diallo');
    }

    public function test_the_request_is_not_stored_while_the_breaker_is_open(): void
    {
        Log::spy();
        Mail::fake();
        config(['atelier.contact.notify' => 'atelier@pehouet.test']);
        app(DatabaseHealth::class)->failed(new RuntimeException('could not connect to server'));

        $this->post('/contact', $this->request())->assertSessionHas('status', 'sent');

        Mail::assertSent(ContactMessageReceived::class);
        $this->assertSame(0, ContactMessage::query()->count(), 'the database was not touched');
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'circuit breaker open'))->once();
    }

    public function test_the_notify_address_set_in_the_cms_survives_an_outage(): void
    {
        Log::spy();
        Mail::fake();
        config(['atelier.contact.notify' => 'env@pehouet.test']);
        Setting::query()->create(['key' => 'contact.notify', 'value' => 'devis@pehouet.test']);
        $this->get('/contact')->assertOk(); // stores the snapshot and its last-good copy

        $this->breakDatabase();
        cache()->forget(config('cms.cache.key'));

        $this->post('/contact', $this->request())->assertSessionHas('status', 'sent');

        Mail::assertSent(ContactMessageReceived::class, fn (ContactMessageReceived $mail): bool => $mail->hasTo('devis@pehouet.test'));
    }

    public function test_requests_are_stored_cleaned_for_postgres(): void
    {
        Mail::fake();
        config(['atelier.contact.notify' => 'atelier@pehouet.test']);

        $this->withHeader('User-Agent', "Navigateur\0 ".str_repeat('x', 300))
            ->post('/contact', $this->request(['name' => "Awa\0 Diallo", 'message' => "Une fresque\0 pour l’école, merci !"]))
            ->assertSessionHas('status', 'sent');

        $message = ContactMessage::query()->sole();
        $this->assertSame('Awa Diallo', $message->name);
        $this->assertSame('Une fresque pour l’école, merci !', $message->message);
        $this->assertSame(255, mb_strlen($message->user_agent));
        $this->assertStringNotContainsString("\0", $message->user_agent);
    }
}
