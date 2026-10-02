<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Support\ServiceCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * The contact / quote form (docs/ARCHITECTURE.md §3).
 *
 * A request is kept as long as it is either stored or e-mailed: a missing database
 * (e.g. a read-only serverless deployment) or a mail outage alone is only logged.
 * Only when both fail does the visitor get an error and their input back. In
 * production, a mailer that only records messages or a placeholder recipient does
 * not count as e-mailed.
 */
final class ContactController extends Controller
{
    /** Mailers that only record messages (log file, memory): nothing reaches an inbox. */
    private const RECORDING_MAILERS = ['log', 'array'];

    /** Reserved placeholder domains (RFC 2606, RFC 6761): mail sent there is lost. */
    private const PLACEHOLDER_DOMAINS = '/(^|\.)(example\.(com|net|org)|example|invalid|localhost|test)$/i';

    public function __construct(private readonly ServiceCatalog $catalog) {}

    public function show(Request $request): View
    {
        $selected = $request->query('service');

        return view('pages.contact', [
            'services' => $this->catalog->all(),
            'selected' => is_string($selected) && $this->catalog->has($selected) ? $selected : null,
            'budgets' => array_values((array) config('atelier.budgets', [])),
        ]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        // Honeypot filled in: pretend everything went well, keep nothing.
        if ($request->isSpam()) {
            return $this->sent();
        }

        $userAgent = $request->userAgent();
        $message = new ContactMessage($request->safe()->only(['name', 'email', 'phone', 'service', 'budget', 'message']) + [
            'locale' => app()->getLocale(),
            'ip_hash' => ContactMessage::hashIp($request->ip()),
            'user_agent' => $userAgent === null ? null : mb_substr(mb_scrub($userAgent, 'UTF-8'), 0, 255),
        ]);

        $stored = $this->persist($message);
        $mailed = $this->notify($message);

        if (! $stored && ! $mailed) {
            // The message itself is not flashed (see bootstrap/app.php): contact.js restores it.
            return redirect()->to(route('contact').'#contact-form')
                ->withInput($request->except([ContactRequest::HONEYPOT, 'message']))
                ->withErrors(['message' => __('mail.contact.failed')]);
        }

        return $this->sent();
    }

    private function persist(ContactMessage $message): bool
    {
        try {
            return $message->save();
        } catch (Throwable $e) {
            Log::error('Contact message could not be stored: '.$e->getMessage());

            return false;
        }
    }

    /** E-mails the atelier; a failure is logged, never shown to the visitor. */
    private function notify(ContactMessage $message): bool
    {
        $recipient = (string) config('atelier.contact.notify');
        $reference = $message->exists ? '#'.$message->id : '(not stored)';

        if ($recipient === '') {
            Log::warning('Contact message '.$reference.' not e-mailed: atelier.contact.notify is empty.');

            return false;
        }

        if (app()->isProduction() && ($reason = $this->undeliverable($recipient)) !== null) {
            Log::warning('Contact message '.$reference.' not e-mailed: '.$reason);

            return false;
        }

        try {
            Mail::to($recipient)->send(new ContactMessageReceived($message));
        } catch (Throwable $e) {
            Log::warning('Contact message '.$reference.' could not be e-mailed: '.$e->getMessage());

            return false;
        }

        return true;
    }

    /** Why an e-mail could never reach an inbox, or null when it can be sent. */
    private function undeliverable(string $recipient): ?string
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, self::RECORDING_MAILERS, true)) {
            return 'the "'.$mailer.'" mailer only records messages (set MAIL_MAILER).';
        }

        if (preg_match(self::PLACEHOLDER_DOMAINS, Str::afterLast($recipient, '@')) === 1) {
            return 'atelier.contact.notify is a placeholder address (set ATELIER_NOTIFY_EMAIL).';
        }

        return null;
    }

    private function sent(): RedirectResponse
    {
        return redirect()->to(route('contact').'#contact-form')->with('status', 'sent');
    }
}
