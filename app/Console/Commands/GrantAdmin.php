<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantAdmin extends Command
{
    protected $signature = 'app:grant-admin {email : Email of an existing user}';

    protected $description = 'Grant an existing user access to the Filament admin panel';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('User not found. Create an account with make:filament-user first.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->info('Admin access granted.');

        return self::SUCCESS;
    }
}
