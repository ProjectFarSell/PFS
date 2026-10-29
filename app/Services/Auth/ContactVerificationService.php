<?php

namespace App\Services\Auth;

use App\Mail\OneTimePasscodeMail;
use App\Models\User;
use App\Models\VerificationChallenge;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ContactVerificationService
{
    public const CODE_LIFETIME_MINUTES = 10;

    public const RESEND_DELAY_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public function send(User $user, string $channel): void
    {
        $this->assertChannel($channel);
        $this->assertDestination($user, $channel);

        $challenge = $user->verificationChallenges()->where('channel', $channel)->first();
        if ($challenge?->sent_at?->addSeconds(self::RESEND_DELAY_SECONDS)->isFuture()) {
            throw ValidationException::withMessages([
                'code' => 'Please wait a minute before requesting another code.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        $attributes = [
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_LIFETIME_MINUTES),
            'sent_at' => now(),
            'verified_at' => null,
            'attempts' => 0,
        ];

        if ($channel === VerificationChallenge::EMAIL) {
            Mail::to($user->email)->send(new OneTimePasscodeMail($code));
        } else {
            $this->sendSms($user->phone, $code);
        }

        $user->verificationChallenges()->updateOrCreate(['channel' => $channel], $attributes);
    }

    public function verify(User $user, string $channel, string $code): bool
    {
        $this->assertChannel($channel);

        $challenge = $user->verificationChallenges()
            ->where('channel', $channel)
            ->whereNull('verified_at')
            ->first();

        if (! $challenge || $challenge->expires_at->isPast() || $challenge->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $challenge->increment('attempts');

        if (! Hash::check($code, $challenge->code_hash)) {
            return false;
        }

        $challenge->forceFill(['verified_at' => now()])->save();
        $user->forceFill([$channel === VerificationChallenge::EMAIL ? 'email_verified_at' : 'phone_verified_at' => now()])->save();

        return true;
    }

    private function sendSms(?string $phone, string $code): void
    {
        $driver = config('services.otp.sms_driver', 'log');

        if ($driver === 'log') {
            Log::info('Local SMS OTP generated.', ['phone' => $phone, 'code' => $code]);

            return;
        }

        if ($driver !== 'semaphore') {
            throw new \RuntimeException('The configured OTP SMS driver is not supported.');
        }

        $apiKey = config('services.otp.semaphore.api_key');
        if (! $apiKey) {
            throw new \RuntimeException('Semaphore OTP settings are incomplete.');
        }

        Http::asForm()
            ->post('https://api.semaphore.co/api/v4/otp', [
                'apikey' => $apiKey,
                'number' => $this->toSemaphoreNumber($phone),
                'message' => 'Your FarSell verification code is {otp}. It expires in 10 minutes.',
                'code' => $code,
            ])
            ->throw();
    }

    private function assertChannel(string $channel): void
    {
        if (! in_array($channel, [VerificationChallenge::EMAIL, VerificationChallenge::PHONE], true)) {
            throw ValidationException::withMessages(['channel' => 'That verification channel is not available.']);
        }
    }

    private function assertDestination(User $user, string $channel): void
    {
        if ($channel === VerificationChallenge::EMAIL && blank($user->email)) {
            throw ValidationException::withMessages(['channel' => 'Add an email address before requesting a code.']);
        }

        if ($channel === VerificationChallenge::PHONE && blank($user->phone)) {
            throw ValidationException::withMessages(['channel' => 'Add a phone number before requesting a code.']);
        }
    }

    private function toSemaphoreNumber(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (str_starts_with($digits, '09') && strlen($digits) === 11) {
            return '63'.substr($digits, 1);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '63'.$digits;
        }

        return $digits;
    }
}
