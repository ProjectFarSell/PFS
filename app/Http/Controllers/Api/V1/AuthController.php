<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileRequest;
use App\Models\MobileTicket;
use App\Models\User;
use App\Services\Mobile\ApiFailure;
use App\Services\Mobile\GoogleIdentity;
use App\Services\Mobile\MobileAuthentication;
use App\Services\Mobile\MobileMailSafety;
use App\Services\Mobile\Reply;
use App\Services\Security\AccountSecurity;
use App\Services\Security\SignInOtp;
use App\Support\PrivacyNotice;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(MobileRequest $request, MobileAuthentication $auth)
    {
        $data = $request->validated();
        $user = User::whereRaw('LOWER(email) = ?', [$data['email']])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw new ApiFailure('unauthenticated', 'Those credentials do not match our records.', 401);
        }
        $auth->eligible($user, $data['app']);

        return app(AccountSecurity::class)->requiresOtp($user)
            ? Reply::data($auth->challenge($user, $data['app']), 202)
            : Reply::data($auth->issue($user, $data['app']));
    }

    public function register(MobileRequest $request, MobileAuthentication $auth)
    {
        $data = $request->validated();
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => UserRole::Buyer]);
        $user->forceFill(['privacy_accepted_at' => now(), 'privacy_notice_version' => PrivacyNotice::VERSION])->save();
        event(new Registered($user));

        return app(AccountSecurity::class)->requiresOtp($user) ? Reply::data($auth->challenge($user, 'buyer'), 202) : Reply::data($auth->issue($user, 'buyer'));
    }

    public function send(MobileRequest $request, MobileAuthentication $auth, SignInOtp $otp)
    {
        $data = $request->validated();
        [$ticket, $user] = $auth->ticketUser($data['challenge_token'], 'login');
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('mobile.otp_context', $ticket->context_hash);
        try {
            if ($data['channel'] === 'email') {
                app(MobileMailSafety::class)->ensure();
            }
            $otp->issue($request, $data['channel']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new ApiFailure('service_unavailable', 'The code could not be sent. Please retry.', 503);
        }
        $challenge = $otp->challenge($request);

        return Reply::data(['channel' => $challenge->channel, 'expires_at' => $challenge->expires_at->utc()->format('Y-m-d\TH:i:s\Z'), 'resend_after' => $challenge->sent_at->addMinute()->utc()->format('Y-m-d\TH:i:s\Z'), 'phone' => $challenge->channel === 'phone' ? $otp->phone($user->phone) : null]);
    }

    public function verify(MobileRequest $request, MobileAuthentication $auth, SignInOtp $otp)
    {
        $data = $request->validated();
        [$ticket, $user] = $auth->ticketUser($data['challenge_token'], 'login');
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('mobile.otp_context', $ticket->context_hash);
        try {
            $identity = $data['channel'] === 'email' ? $otp->verifyEmail($request, $data['code']) : $otp->verifyPhone($request, $data['id_token']);
        } catch (UniqueConstraintViolationException) {
            $identity = null;
        }
        if (! $identity) {
            throw ValidationException::withMessages(['code' => 'Code invalid, expired, already used or attempt limit reached.']);
        }

        // Failed proof attempts have already committed; never roll them back here.
        return DB::transaction(function () use ($ticket, $user, $auth, $identity) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $ticket = MobileTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($ticket->consumed_at || $ticket->expires_at->lte(now()) || ! hash_equals($ticket->identity_hash, $auth->fingerprint($user)) || ! hash_equals($identity, app(AccountSecurity::class)->otpFingerprint($user))) {
                throw new ApiFailure('unauthenticated', 'This sign-in check is no longer valid.', 401);
            }
            $ticket->update(['consumed_at' => now()]);

            return Reply::data($auth->issue($user, $ticket->app));
        }, 3);
    }

    public function session(MobileRequest $request, MobileAuthentication $auth)
    {
        return Reply::data($auth->session($request->user(), $request->user()->currentAccessToken()->expires_at));
    }

    public function logout(MobileRequest $request)
    {
        // FK cascade removes only bindings still owned by this exact token.
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function reauthenticate(MobileRequest $request, MobileAuthentication $auth)
    {
        $data = $request->validated();
        if (isset($data['id_token'])) {
            $identity = app(GoogleIdentity::class)->verify($data['id_token'], 300);
            if (! $request->user()->google_id || ! hash_equals($request->user()->google_id, $identity['sub'])) {
                throw ValidationException::withMessages(['id_token' => 'Confirm with the Google account linked to this FarSell account.']);
            }
        } elseif (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'The password did not match.']);
        }
        [$ticket, $secret] = $auth->ticket($request->user(), $request->user()->role->value, 'reauth', $request->user()->currentAccessToken()->id);

        return Reply::data(['reauth_token' => $secret, 'expires_at' => $ticket->expires_at->utc()->format('Y-m-d\TH:i:s\Z')]);
    }
}
