<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use App\Models\User;

/*
 * Локальное создание первой учётной записи без открытой регистрации.
 */
#[Signature('panel:user')]
#[Description('Create an application user who can sign in to the protected panel.')]
final class CreatePanelUserCommand extends Command
{
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
