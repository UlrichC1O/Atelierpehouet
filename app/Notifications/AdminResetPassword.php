<?php

namespace App\Notifications;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The "Mot de passe oublié ?" e-mail of the admin CMS (docs/CMS.md §13 D19): a link to
 * admin.password.reset, worded by the admin lang group — in French unless another locale is given
 * (the language the admin was using when asking for it). Sent at once, never queued: the form says
 * whether it could be sent.
 */
final class AdminResetPassword extends Notification
{
    /**
     * @param  string  $token  the password broker's token
     * @param  string|null  $root  site address put in the link (default: this request's own)
     */
    public function __construct(
        public readonly string $token,
        public readonly ?string $root = null,
        ?string $locale = null,
    ) {
        $this->locale = $locale ?? SetLocale::defaultLocale();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $site = (string) config('atelier.name', 'Ateliers Pehouet');
        $name = $notifiable instanceof User && trim((string) $notifiable->name) !== '' ? (string) $notifiable->name : $site;

        return (new MailMessage)
            ->subject(__('admin.password.mail.subject', ['site' => $site]))
            ->view(['admin.auth.mail.reset-password', 'admin.auth.mail.reset-password-text'], [
                'url' => $this->url($notifiable),
                'name' => $name,
                'site' => $site,
                'minutes' => self::expireMinutes(),
            ]);
    }

    /** The reset page of the admin for this token and the account's e-mail address. */
    public function url(object $notifiable): string
    {
        $email = method_exists($notifiable, 'getEmailForPasswordReset') ? $notifiable->getEmailForPasswordReset() : null;
        $path = route('admin.password.reset', array_filter(['token' => $this->token, 'email' => $email]), false);

        return $this->root === null ? url($path) : rtrim($this->root, '/').$path;
    }

    /** Minutes a link stays valid (config/auth.php, the default password broker). */
    public static function expireMinutes(): int
    {
        return max(1, (int) config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60));
    }
}
