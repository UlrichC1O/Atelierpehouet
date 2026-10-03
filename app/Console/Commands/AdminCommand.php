<?php

namespace App\Console\Commands;

use App\Cms\Text;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates or updates an administrator of the admin CMS (/admin), e.g. on the Codespace:
 *   php artisan atelier:admin owner@example.org --name="Atelier"
 * The account gets the explicit admin right (users.is_admin, docs/CMS.md §13 D18) once that
 * column exists.
 */
final class AdminCommand extends Command
{
    private const MIN_PASSWORD = 10;

    protected $signature = 'atelier:admin
        {email : E-mail address used to log in}
        {--name= : Display name (a new administrator defaults to the part before the @)}
        {--password= : Password, at least 10 characters (asked secretly when omitted)}';

    protected $description = 'Create or update an administrator of the admin CMS (/admin)';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
            $this->components->error('"'.$email.'" is not a valid e-mail address.');

            return self::FAILURE;
        }

        $password = $this->password();

        if ($password === null) {
            return self::FAILURE;
        }

        try {
            $user = User::query()->where('email', $email)->first();
            $created = $user === null;
            $user ??= new User(['email' => $email]);

            $name = trim((string) Text::column((string) $this->option('name'), 120));
            $user->name = $name !== '' ? $name : ($user->name ?: Str::before($email, '@'));
            $user->password = $password; // hashed by the model's cast

            if (Schema::hasColumn('users', 'is_admin')) {
                $user->is_admin = true;
            }

            $user->save();
        } catch (Throwable $e) {
            $this->components->error('The administrator could not be saved: '.$e->getMessage());
            $this->line('  Has the database been migrated? <comment>php artisan migrate</comment>');

            return self::FAILURE;
        }

        $this->components->info(($created ? 'Administrator created: ' : 'Administrator updated: ').$email);
        $this->line('  Log in at <comment>'.route('admin.login').'</comment>');

        return self::SUCCESS;
    }

    /** The --password option, or a secret prompt (typed twice); null after reporting a problem. */
    private function password(): ?string
    {
        $password = $this->option('password');

        if ($password === null) {
            if (! $this->input->isInteractive()) {
                $this->components->error('Pass --password=… (at least '.self::MIN_PASSWORD.' characters) when running non-interactively.');

                return null;
            }

            $password = (string) $this->secret('Password (at least '.self::MIN_PASSWORD.' characters)');

            if ($password !== (string) $this->secret('Repeat the password')) {
                $this->components->error('The two passwords differ.');

                return null;
            }
        }

        if (mb_strlen((string) $password) < self::MIN_PASSWORD) {
            $this->components->error('The password must be at least '.self::MIN_PASSWORD.' characters long.');

            return null;
        }

        return (string) $password;
    }
}
