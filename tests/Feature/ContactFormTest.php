<?php

namespace Tests\Feature;

use App\Mail\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The contact form only confirms a request that was stored or really e-mailed, and
 * never copies the visitor's details into the logs (ContactController).
 */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private const VISITOR = [
        'name' => 'Jeanne Testeuse',
        'email' => 'jeanne.testeuse@example.org',
        'phone' => '+33 6 12 34 56 78',
        'message' => 'Bonjour, je souhaite une fresque pour notre école.',
        'consent' => '1',
    ];

    public function test_a_stored_request_is_confirmed_and_e_mailed(): void
    {
        Mail::fake();
        config(['atelier.contact.notify' => 'atelier@pehouet.fr']);

        $this->post(route('contact.store'), self::VISITOR)
            ->assertRedirect(route('contact').'#contact-form')
            ->assertSessionHas('status', 'sent');

        $this->assertDatabaseHas('contact_messages', ['email' => self::VISITOR['email']]);
        Mail::assertSent(ContactMessageReceived::class);
    }

    public function test_in_production_a_request_neither_stored_nor_delivered_is_not_confirmed(): void
    {
        $this->inProduction();
        config(['mail.default' => 'log', 'atelier.contact.notify' => 'atelier@pehouet.fr']);
        Schema::drop('contact_messages');
        Log::spy();

        $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post(route('contact.store'), self::VISITOR)
            ->assertRedirect(route('contact').'#contact-form')
            ->assertSessionHasErrors('message')
            ->assertSessionHasInput('name', self::VISITOR['name'])
            ->assertSessionMissing('_old_input.message')
            ->assertSessionMissing('status');

        Log::shouldHaveReceived('warning')->withArgs(fn (string $line) => str_contains($line, 'only records messages'));
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $line) => str_contains($line, 'could not be stored')
            && ! str_contains($line, self::VISITOR['name'])
            && ! str_contains($line, self::VISITOR['email'])
            && ! str_contains($line, self::VISITOR['message']));
    }

    public function test_in_production_a_placeholder_recipient_is_not_e_mailed(): void
    {
        $this->inProduction();
        Mail::fake();
        config(['mail.default' => 'smtp', 'atelier.contact.notify' => 'contact@example.com']);

        $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->post(route('contact.store'), self::VISITOR)
            ->assertSessionHas('status', 'sent');

        $this->assertDatabaseHas('contact_messages', ['email' => self::VISITOR['email']]);
        Mail::assertNothingSent();
    }

    public function test_a_long_message_sent_back_with_errors_fits_in_the_session_cookie(): void
    {
        config(['session.driver' => 'cookie']);
        $visitor = ['message' => mb_substr(str_repeat('Une fresque été à l’école, déco éclatante. ', 120), 0, 5000)]
            + array_diff_key(self::VISITOR, ['consent' => true]);

        $response = $this->post(route('contact.store'), $visitor)
            ->assertSessionHasErrors('consent')
            ->assertSessionHasInput('email', self::VISITOR['email'])
            ->assertSessionMissing('_old_input.message');

        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertLessThan(4096, strlen($cookie->getName().'='.$cookie->getValue()), $cookie->getName().' cookie is too big');
        }
    }

    private function inProduction(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
    }
}
