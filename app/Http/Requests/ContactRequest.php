<?php

namespace App\Http\Requests;

use App\Support\ServiceCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The quote / contact form (docs/ARCHITECTURE.md §3).
 *
 * "website" is a honeypot that humans never see: when a bot fills it, validation is
 * skipped entirely so the bot receives the usual success redirect and learns nothing;
 * ContactController discards the submission (see isSpam()).
 */
final class ContactRequest extends FormRequest
{
    public const HONEYPOT = 'website';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'service' => ['nullable', 'string', Rule::in(app(ServiceCatalog::class)->slugs())],
            'budget' => ['nullable', 'string', Rule::in((array) config('atelier.budgets', []))],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'consent' => ['accepted'],
        ];
    }

    /** True when the honeypot field was filled in (a bot). */
    public function isSpam(): bool
    {
        return filled($this->input(self::HONEYPOT));
    }

    /**
     * Honeypot submissions are not validated: they are silently "accepted" and dropped.
     */
    public function validateResolved(): void
    {
        if ($this->isSpam()) {
            return;
        }

        parent::validateResolved();
    }
}
