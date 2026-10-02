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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'service', 'budget', 'message', 'locale', 'ip_hash', 'user_agent', 'read_at'])]
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

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

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->whereNull('read_at');
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
