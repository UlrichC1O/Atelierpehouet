<?php

namespace App\Console\Commands;

use App\Models\ContactMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lists the latest quote / contact requests received through the website.
 */
final class MessagesCommand extends Command
{
    protected $signature = 'atelier:messages
        {--limit=20 : Number of messages to show}
        {--unread : Only show messages not marked as read}';

    protected $description = 'List the latest contact messages sent from the website';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));

        try {
            $query = ContactMessage::query()->latest()->latest('id');

            if ($this->option('unread')) {
                $query->unread();
            }

            $messages = $query->limit($limit)->get();
            $total = ContactMessage::query()->count();
            $unread = ContactMessage::query()->unread()->count();
        } catch (Throwable $e) {
            $this->components->error('Messages could not be read: '.$e->getMessage());
            $this->line('  Has the database been migrated? <comment>php artisan migrate</comment>');

            return self::FAILURE;
        }

        if ($messages->isEmpty()) {
            $this->components->info($this->option('unread') ? 'No unread contact message.' : 'No contact message yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Received', 'Name', 'E-mail', 'Phone', 'Service', 'Budget', 'Lang', 'Read', 'Message'],
            $messages->map(fn (ContactMessage $message): array => [
                $message->id,
                $message->created_at?->format('Y-m-d H:i') ?? '',
                Str::limit($message->name, 24),
                $message->email,
                $message->phone ?? '',
                $message->service ?? '',
                $message->budget ?? '',
                $message->locale,
                $message->isRead() ? 'yes' : '',
                Str::limit((string) preg_replace('/\s+/u', ' ', $message->message), 48),
            ])->all(),
        );

        $this->line(sprintf('  %d shown · %d in total · %d unread', $messages->count(), $total, $unread));

        return self::SUCCESS;
    }
}
