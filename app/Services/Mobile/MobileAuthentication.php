<?php

namespace App\Services\Mobile;

use App\Models\MobileTicket;
use App\Models\User;
use App\Services\Security\AccountSecurity;
use App\Services\Security\SignInOtp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileAuthentication
{
    public function fingerprint(User $user): string
    {
        return hash_hmac('sha256', app(AccountSecurity::class)->otpFingerprint($user).'|'.$user->password, config('app.key'));
    }

    public function eligible(User $user, string $app): void
    {
        if ($user->suspended_at || ! in_array($app, ['buyer', 'rider'], true) || $user->role->value !== $app) {
            throw new ApiFailure('forbidden', 'This account cannot use the selected app.', 403);
        }
    }

    public function issue(User $user, string $app): array
    {
        return DB::transaction(function () use ($user, $app) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->eligible($user, $app);
            $expires = now()->addHours(config('mobile.token_hours'));
            $token = $user->createToken('mobile-'.$app, [$app.':*'], $expires);
            $token->accessToken->forceFill(['identity_hash' => $this->fingerprint($user), 'mobile_app' => $app, 'authenticated_at' => now()])->save();

            return ['token_type' => 'Bearer', 'access_token' => $token->plainTextToken, 'expires_at' => $expires->utc()->format('Y-m-d\TH:i:s\Z'), 'abilities' => [$app.':*'], 'user' => $this->session($user, $expires)];
        }, 3);
    }

    public function ticket(User $user, string $app, string $purpose = 'login', ?int $tokenId = null): array
    {
        $secret = Str::random(80);
        $ticket = MobileTicket::create(['user_id' => $user->id, 'token_id' => $tokenId, 'secret_hash' => hash('sha256', $secret), 'context_hash' => hash('sha256', Str::random(80)), 'identity_hash' => $this->fingerprint($user), 'app' => $app, 'purpose' => $purpose, 'expires_at' => now()->addMinutes($purpose === 'reauth' ? 5 : 10)]);

        return [$ticket, $secret];
    }

    public function ticketUser(string $secret, string $purpose): array
    {
        $ticket = MobileTicket::where('secret_hash', hash('sha256', $secret))->where('purpose', $purpose)->first();
        $user = $ticket ? User::find($ticket->user_id) : null;
        if (! $ticket || ! $user || $ticket->consumed_at || $ticket->expires_at->lte(now()) || ! hash_equals($ticket->identity_hash, $this->fingerprint($user))) {
            throw new ApiFailure('unauthenticated', 'This security check expired. Sign in again.', 401);
        }
        $this->eligible($user, $ticket->app);

        return [$ticket, $user];
    }

    public function challenge(User $user, string $app): array
    {
        [$ticket, $secret] = $this->ticket($user, $app);

        return ['challenge_token' => $secret, 'expires_at' => $ticket->expires_at->utc()->format('Y-m-d\TH:i:s\Z'), 'methods' => array_values(array_filter(['email', filled($user->phone) && app(SignInOtp::class)->phoneConfigured() ? 'phone' : null])), 'email_hint' => mb_substr($user->email, 0, 1).'***@'.Str::after($user->email, '@'), 'phone_hint' => $user->phone ? '***'.mb_substr($user->phone, -4) : null, 'requires_send' => true];
    }

    public function session(User $user, $expires): array
    {
        $user->loadMissing('riderProfile');

        return ['id' => PublicIds::id($user), 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'role' => $user->role->value, 'email_verified' => $user->email_verified_at !== null, 'phone_verified' => $user->phone_verified_at !== null, 'privacy_notice_version' => $user->privacy_notice_version, 'rider_approval' => $user->isRider() ? ($user->riderProfile?->status->value ?? 'missing') : null, 'token_expires_at' => $expires->utc()->format('Y-m-d\TH:i:s\Z')];
    }
}
