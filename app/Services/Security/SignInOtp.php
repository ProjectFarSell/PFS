<?php

namespace App\Services\Security;

use App\Mail\SignInCodeMail;
use App\Models\SignInChallenge;
use App\Models\User;
use App\Services\Auth\FirebasePhoneVerificationService;
use App\Support\DemoAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignInOtp
{
    public function context(Request $request): string
    {
        // A trusted API controller supplies a durable server-side ticket context.
        // This attribute cannot be supplied through JSON, headers or query parameters.
        if ($context = $request->attributes->get('mobile.otp_context')) {
            return $context;
        }
        if (! $request->session()->has('security.otp_context')) {
            $request->session()->put('security.otp_context', Str::random(64));
        }

        return hash('sha256', $request->session()->get('security.otp_context'));
    }

    public function challenge(Request $request): ?SignInChallenge
    {
        return SignInChallenge::where('user_id', $request->user()->id)->where('context_hash', $this->context($request))->first();
    }

    public function issue(Request $request, string $channel): void
    {
        $context = $this->context($request);
        DB::transaction(function () use ($request, $context, $channel) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_unless((! DemoAccount::exempt($user) || $request->attributes->get('mobile.contact_verification')) && ! $user->usesAuthenticator() && ! $user->suspended_at, 403);
            if (! in_array($channel, ['email', 'phone'], true) || blank($user->{$channel})) {
                $this->invalid('Use an email address or phone number already saved on your account.');
            }
            if ($channel === 'phone' && ! $this->phoneConfigured($request->attributes->has('mobile.otp_context'))) {
                $this->invalid('Phone codes are not configured. Use email or contact support.');
            }
            if (SignInChallenge::where('user_id', $user->id)->where('sent_at', '>', now()->subMinute())->exists()) {
                $this->invalid('Wait one minute before requesting another sign-in code.');
            }
            $code = $channel === 'email' ? (string) random_int(100000, 999999) : null;
            SignInChallenge::updateOrCreate(['user_id' => $user->id, 'context_hash' => $context], [
                'identity_hash' => app(AccountSecurity::class)->otpFingerprint($user), 'channel' => $channel,
                'code_hash' => $code ? Hash::make($code) : null, 'attempts' => 0, 'consumed_at' => null, 'phone_proof_hash' => null,
                'sent_at' => now(), 'expires_at' => now()->addMinutes(10),
            ]);
            if ($code) {
                // No automatic retry: a failed mail send rolls back this new challenge.
                Mail::to($user->email)->send(new SignInCodeMail($code));
            }
        });
    }

    public function verifyEmail(Request $request, string $code): ?string
    {
        return $this->consume($request, 'email', fn (SignInChallenge $challenge) => Hash::check($code, $challenge->code_hash));
    }

    public function verifyPhone(Request $request, string $token): ?string
    {
        $challenge = $this->challenge($request);
        if (! $challenge || $challenge->channel !== 'phone' || $challenge->consumed_at || $challenge->expires_at->lte(now()) || $challenge->attempts >= 5) {
            return null;
        }
        try {
            $project = $request->attributes->has('mobile.otp_context') ? config('mobile.firebase_project_id') : null;
            $proof = app(FirebasePhoneVerificationService::class)->verifyForSignIn($token, $challenge->sent_at->timestamp, $project ?: null);
        } catch (\Throwable) {
            // Invalid/failed provider verification must not bypass the attempt limit.
            return $this->consume($request, 'phone', fn () => false);
        }

        return $this->consume($request, 'phone', function (SignInChallenge $current, User $user) use ($proof, $challenge) {
            if (! $current->sent_at->equalTo($challenge->sent_at) || $this->phone($user->phone) !== $this->phone($proof['phone'])
                || SignInChallenge::where('phone_proof_hash', $proof['hash'])->exists()) {
                return false;
            }
            $current->phone_proof_hash = $proof['hash'];

            return true;
        });
    }

    private function consume(Request $request, string $channel, callable $check): ?string
    {
        $context = $this->context($request);

        return DB::transaction(function () use ($request, $context, $channel, $check) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_unless((! DemoAccount::exempt($user) || $request->attributes->get('mobile.contact_verification')) && ! $user->usesAuthenticator() && ! $user->suspended_at, 403);
            $challenge = SignInChallenge::where('user_id', $user->id)->where('context_hash', $context)->lockForUpdate()->first();
            if (! $challenge || $challenge->channel !== $channel || $challenge->consumed_at || $challenge->expires_at->lte(now()) || $challenge->attempts >= 5
                || ! hash_equals(app(AccountSecurity::class)->otpFingerprint($user), $challenge->identity_hash)) {
                return null;
            }
            $challenge->attempts++;
            $valid = $check($challenge, $user);
            if ($valid) {
                $challenge->consumed_at = now();
                $user->forceFill([$channel.'_verified_at' => $user->{$channel.'_verified_at'} ?? now(), 'verification_channel' => $channel])->save();
            }
            $challenge->save();

            return $valid ? $challenge->identity_hash : null;
        });
    }

    public function phone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (str_starts_with($digits, '0')) {
            $digits = '63'.substr($digits, 1);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '63'.$digits;
        }

        return $digits === '' ? '' : '+'.$digits;
    }

    public function phoneConfigured(bool $mobile = false): bool
    {
        if ($mobile && filled(config('mobile.firebase_project_id'))) {
            return true;
        }

        return collect(['api_key', 'auth_domain', 'project_id', 'app_id'])->every(fn ($key) => filled(config('services.firebase.'.$key)));
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['code' => $message]);
    }
}
