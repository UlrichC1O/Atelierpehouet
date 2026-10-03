<?php

namespace App\Models;

use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A quote / contact request sent from the contact form.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $service service slug
 * @property string|null $budget key of config('atelier.budgets')
 * @property string $message
 * @property string $locale language the visitor wrote in
 * @property string|null $ip_hash HMAC-SHA256 of the visitor's IP (keyed with the app key)
 * @property string|null $user_agent
 * @property Carbon|null $read_at
 * @property string $status one of self::STATUSES (admin CRM pipeline; the database default is "new")
 * @property string|null $notes internal notes of the atelier (admin CRM)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'service', 'budget', 'message', 'locale', 'ip_hash', 'user_agent', 'read_at', 'status', 'notes'])]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    /**
     * Pipeline of a request in the admin CRM, in order (labels: admin.messages.statuses.{status}).
     * The contact form never writes the status: new rows get the database default, so the form
     * keeps working on a database where the CRM columns are not migrated yet.
     */
    public const STATUSES = ['new', 'in_progress', 'done', 'archived'];

    /**
     * Hash of a visitor's IP address: lets us spot abuse without storing the address.
     */
    public static function hashIp(?string $ip): ?string
    {
        return $ip === null || $ip === '' ? null : hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if (! $this->isRead()) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    public function markAsUnread(): void
    {
        if ($this->isRead()) {
            $this->forceFill(['read_at' => null])->save();
        }
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /**
     * Requests at one step of the pipeline: ContactMessage::query()->status('new').
     *
     * A classic scope method: a #[Scope] method named status() would shadow the "status" column
     * (Eloquent would take it for a relation when the column is not selected).
     *
     * @param  Builder<self>  $query
     */
    public function scopeStatus(Builder $query, string $status): void
    {
        $query->where('status', $status);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
