<?php

namespace App\Services\Security;

use App\Models\User;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use OTPHP\InternalClock;
use OTPHP\TOTP;

class AdminTwoFactor
{
    public function generate(): string
    {
        // 160 random bits, encoded as 32 Base32 characters for SHA-1 TOTP interoperability.
        return TOTP::generate(new InternalClock, 20)->getSecret();
    }

    public function provisioningUri(string $secret, string $email): string
    {
        return TOTP::createFromSecret($secret, new InternalClock)
            ->withLabel($email)->withIssuer('FarSell')->getProvisioningUri();
    }

    public function qrDataUri(string $secret, string $email): string
    {
        $png = (new Writer(new GDLibRenderer(320)))->writeString($this->provisioningUri($secret, $email));

        return 'data:image/png;base64,'.base64_encode($png);
    }

    public function step(string $secret, string $code, ?int $lastStep = null): ?int
    {
        if (! preg_match('/^\d{6}$/D', $code)) {
            return null;
        }
        $otp = TOTP::createFromSecret($secret, new InternalClock);
        $now = intdiv(now()->timestamp, 30);
        foreach ([$now, $now - 1, $now + 1] as $step) {
            if ($step > ($lastStep ?? -1) && hash_equals($otp->at($step * 30), $code)) {
                return $step;
            }
        }

        return null;
    }

    public function verify(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if (! $user->mfa_confirmed_at || $user->suspended_at) {
                return false;
            }
            $step = $this->step($user->mfa_secret, $code, $user->mfa_last_step);
            if ($step !== null) {
                $user->forceFill(['mfa_last_step' => $step])->save();

                return true;
            }
            $hash = hash('sha256', strtolower(str_replace('-', '', trim($code))));
            $codes = $user->mfa_recovery_codes ?? [];
            foreach ($codes as $index => $saved) {
                if (hash_equals($saved, $hash)) {
                    unset($codes[$index]);
                    $user->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();
                    app(AuditTrail::class)->record('mfa.recovery_code_used', $user);

                    return true;
                }
            }

            return false;
        }, 3);
    }

    public function recoveryCodes(): array
    {
        $plain = array_map(fn () => implode('-', str_split(bin2hex(random_bytes(16)), 8)), range(1, 8));

        return [$plain, array_map(fn ($code) => hash('sha256', str_replace('-', '', $code)), $plain)];
    }
}
