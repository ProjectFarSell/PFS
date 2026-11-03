<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateProductionAdmin extends Command
{
    protected $signature = 'ops:create-admin {email} {--name=} {--confirm-production}';

    protected $description = 'Provision a new administrator interactively, without default credentials or password arguments';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('confirm-production')) {
            $this->error('Use --confirm-production only after verifying the target environment and authorizing this account.');

            return self::FAILURE;
        }
        $email = mb_strtolower(trim($this->argument('email')));
        $name = $this->option('name') ?: $this->ask('Administrator name');
        $password = $this->secret('New admin password');
        $confirmation = $this->secret('Confirm admin password');
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], [
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::create(['name' => $name, 'email' => $email, 'password' => $password, 'role' => UserRole::Admin]);
        $this->info('Administrator created. The account holder can review the privacy notice after sign-in.');

        return self::SUCCESS;
    }
}
