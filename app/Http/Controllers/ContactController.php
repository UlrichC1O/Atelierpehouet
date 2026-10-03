<?php

namespace App\Http\Controllers;

use App\Cms\DatabaseHealth;
use App\Cms\Text;
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
 * The contact / quote form (docs/ARCHITECTURE.md §3, docs/CMS.md §13 A6).
 *
 * A request is kept as long as it is either e-mailed or stored: the e-mail goes first (the
 * recipient set in the CMS survives database outages in the last-good snapshot), then the row
 * is stored unless the database circuit breaker is open — an unreachable database costs the
 * visitor no timeout. A database or mail outage alone is only logged; only when both fail does
 * the visitor get an error and their input back. In production, a mailer that only records
 * messages or a placeholder recipient does not count as e-mailed.
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

        $input = $request->safe();
        $text = static fn (string $field, int $max): ?string => is_string($input[$field] ?? null) ? Text::column($input[$field], $max) : null;

        // Every string storable as is in Postgres (valid UTF-8, no NUL byte, column sizes).
        $message = new ContactMessage([
            'name' => $text('name', 120),
            'email' => $text('email', 190),
            'phone' => $text('phone', 40),
            'service' => $text('service', 80),
            'budget' => $text('budget', 40),
            'message' => $text('message', 5000),
            'locale' => Text::column(app()->getLocale(), 5),
            'ip_hash' => ContactMessage::hashIp($request->ip()),
            'user_agent' => Text::column($request->userAgent(), 255),
        ]);

        $mailed = $this->notify($message);
        $stored = $this->persist($message);

        if (! $stored && ! $mailed) {
            // The message itself is not flashed (see bootstrap/app.php): contact.js restores it.
            return redirect()->to(route('contact').'#contact-form')
                ->withInput($request->except([ContactRequest::HONEYPOT, 'message']))
                ->withErrors(['message' => __('mail.contact.failed')]);
        }

        return $this->sent();
    }

    /** Stores the request unless the database is known to be unreachable (circuit breaker). */
    private function persist(ContactMessage $message): bool
    {
        $health = app(DatabaseHealth::class);

        if (! $health->available()) {
            Log::warning('Contact message not stored: the database is unreachable (circuit breaker open).');

            return false;
        }

        try {
            return $message->save();
        } catch (Throwable $e) {
            $health->failed($e);
            Log::error('Contact message could not be stored: '.$e->getMessage());

            return false;
        }
    }

    /** E-mails the atelier (before storing: the e-mail must not wait for the database); a failure is logged, never shown. */
    private function notify(ContactMessage $message): bool
    {
        $recipient = (string) config('atelier.contact.notify');

        if ($recipient === '') {
            Log::warning('Contact message not e-mailed: atelier.contact.notify is empty.');

            return false;
        }

        if (app()->isProduction() && ($reason = $this->undeliverable($recipient)) !== null) {
            Log::warning('Contact message not e-mailed: '.$reason);

            return false;
        }

        try {
            Mail::to($recipient)->send(new ContactMessageReceived($message));
        } catch (Throwable $e) {
            Log::warning('Contact message could not be e-mailed: '.$e->getMessage());

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
