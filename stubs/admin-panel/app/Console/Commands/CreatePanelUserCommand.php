<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Локальное создание первой учётной записи без открытой регистрации.
 */
final class CreatePanelUserCommand extends Command
{
    protected $signature = 'panel:user';

    protected $description = 'Create an application user who can sign in to the protected panel.';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Name'));
        $email = strtolower(trim((string) $this->ask('Email')));
        $password = $this->secret('Password (at least 12 characters)');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !is_string($password) || mb_strlen($password) < 12) {
            $this->error('Provide a name, valid email and password of at least 12 characters.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('An account with this email already exists.');

            return self::FAILURE;
        }

        $user = new User;
        $user->name = $name;
        $user->email = $email;
        // User's hashed cast handles the password without exposing it to logs.
        $user->password = $password;
        $user->save();

        $this->info('Panel account created.');

        return self::SUCCESS;
    }
}
