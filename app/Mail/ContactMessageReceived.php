<?php

namespace App\Mail;

use App\Http\Middleware\SetLocale;
use App\Models\ContactMessage;
use App\Support\ServiceCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

/**
 * Notifies the atelier of a new contact / quote request. Written in the site's
 * default language; replying answers the visitor directly (Reply-To).
 */
final class ContactMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contactMessage)
    {
        // The atelier reads its mail in the site's default language.
        $this->locale(SetLocale::defaultLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.contact.subject', ['name' => $this->contactMessage->name]),
            replyTo: [new Address($this->contactMessage->email, $this->contactMessage->name)],
            tags: ['contact'],
        );
    }

    public function content(): Content
    {
        $message = $this->contactMessage;
        $service = $message->service !== null ? app(ServiceCatalog::class)->find($message->service) : null;
        $budgetLine = 'mail.contact.budgets.'.$message->budget;
        $localeName = (string) (config('atelier.locales.'.$message->locale) ?? $message->locale);

        return new Content(
            view: 'mail.contact-message',
            text: 'mail.contact-message-text',
            with: [
                'serviceTitle' => $service['title'] ?? $message->service,
                'serviceUrl' => $service['url'] ?? null,
                'budgetLabel' => $message->budget === null ? null : (Lang::has($budgetLine) ? __($budgetLine) : $message->budget),
                'localeName' => $localeName,
                'receivedAt' => ($message->created_at ?? now())->copy()->locale(app()->getLocale())->translatedFormat('j F Y, H:i'),
                'siteUrl' => route('home'),
            ],
        );
    }
}
