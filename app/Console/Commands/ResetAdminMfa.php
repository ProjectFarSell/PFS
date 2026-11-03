<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Security\AccountSecurity;
use App\Services\Security\AuditTrail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResetAdminMfa extends Command
{
    protected $signature = 'ops:reset-admin-mfa {email} {--confirm-production}';

    protected $description = 'Reset an admin authenticator after operator identity verification, revoking all sessions';

    public function handle(): int
    {
        if (app()->environment('production', 'staging') && ! $this->option('confirm-production')) {
            $this->error('Verify the administrator identity and environment, then use --confirm-production.');

            return self::FAILURE;
        }
        $email = mb_strtolower(trim($this->argument('email')));
        $user = User::query()->where('email', $email)->where('role', UserRole::Admin)->first();
        if (! $user || ! $user->mfa_confirmed_at) {
            $this->error('No enrolled administrator matches this email.');

            return self::FAILURE;
        }
        if ($this->ask('Type RESET '.$email.' after independently verifying this administrator') !== 'RESET '.$email) {
            $this->error('Reset cancelled.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->forceFill(['mfa_secret' => null, 'mfa_confirmed_at' => null, 'mfa_recovery_codes' => null, 'mfa_last_step' => null, 'security_version' => $user->security_version + 1, 'remember_token' => Str::random(60)])->save();
            app(AccountSecurity::class)->deleteSessions($user);
            app(AuditTrail::class)->record('mfa.reset_by_operator', $user);
        }, 3);
        $this->info('Authenticator reset. All devices revoked. The admin must sign in and enroll again.');

        return self::SUCCESS;
    }
}
