<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Manager\AulaManagerUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateManagerUser extends Command
{
    protected $signature = 'manager:create-user';

    protected $description = 'Create an aula Manager panel admin user';

    public function handle(): int
    {
        $this->info('=== Create Manager User ===');

        do {
            $name = $this->ask('Name');
        } while (empty($name));

        do {
            $email = $this->ask('Email');
            if (AulaManagerUser::firstWhere('email', $email)) {
                $this->warn('A user with that email already exists.');
                $email = null;
            }
        } while (empty($email));

        do {
            $password = $this->secret('Password (min 8 characters)');
            if (strlen($password) < 8) {
                $this->warn('Password must be at least 8 characters.');
                $password = null;
            }
        } while (empty($password));

        AulaManagerUser::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("Manager user '{$email}' created. Visit /manager to log in.");

        return self::SUCCESS;
    }
}
