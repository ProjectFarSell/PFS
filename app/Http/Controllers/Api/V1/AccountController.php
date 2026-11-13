<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileRequest;
use App\Models\MobileDevice;
use App\Models\MobileTicket;
use App\Models\User;
use App\Services\Mobile\ApiFailure;
use App\Services\Mobile\MobileAuthentication;
use App\Services\Mobile\MobileMailSafety;
use App\Services\Mobile\MobileViews;
use App\Services\Mobile\PublicIds;
use App\Services\Mobile\Reply;
use App\Services\Orders\DeletionRequest;
use App\Services\Security\SignInOtp;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function contactSend(MobileRequest $request, MobileAuthentication $auth, SignInOtp $otp)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($request, $auth, $otp, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            [$ticket] = $auth->ticket($request->user(), $request->user()->role->value, 'contact', $request->user()->currentAccessToken()->id);
            $request->attributes->set('mobile.otp_context', $ticket->context_hash);
            $request->attributes->set('mobile.contact_verification', true);
            try {
                if ($data['channel'] === 'email') {
                    app(MobileMailSafety::class)->ensure();
                }
                $otp->issue($request, $data['channel']);
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (\Throwable) {
                throw new ApiFailure('service_unavailable', 'The verification code could not be sent.', 503);
            }
            $challenge = $otp->challenge($request);

            return Reply::data(['channel' => $challenge->channel, 'expires_at' => MobileViews::time($challenge->expires_at), 'resend_after' => MobileViews::time($challenge->sent_at->copy()->addMinute()), 'phone' => $challenge->channel === 'phone' ? $otp->phone($request->user()->phone) : null]);
        }, 3);
    }

    public function contactVerify(MobileRequest $request, MobileAuthentication $auth, SignInOtp $otp)
    {
        $data = $request->validated();
        $ticket = MobileTicket::where('token_id', $request->user()->currentAccessToken()->id)->where('purpose', 'contact')->whereNull('consumed_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $ticket || ! hash_equals($ticket->identity_hash, $auth->fingerprint($request->user()))) {
            throw new ApiFailure('unauthenticated', 'Request a new contact verification code.', 401);
        }
        $request->attributes->set('mobile.otp_context', $ticket->context_hash);
        $request->attributes->set('mobile.contact_verification', true);
        try {
            $valid = $data['channel'] === 'email' ? $otp->verifyEmail($request, $data['code']) : $otp->verifyPhone($request, $data['id_token']);
        } catch (UniqueConstraintViolationException) {
            $valid = null;
        }
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'This verification code is invalid or expired.']);
        }
        $ticket->update(['consumed_at' => now()]);

        return Reply::data($auth->session($request->user()->fresh(), $request->user()->currentAccessToken()->expires_at));
    }

    public function deletion(MobileRequest $request, MobileAuthentication $auth)
    {
        $data = $request->validated();
        [$ticket] = $auth->ticketUser($data['reauth_token'], 'reauth');

        return DB::transaction(function () use ($request, $ticket, $auth) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $ticket = MobileTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless($ticket->user_id === $user->id && $ticket->token_id === $request->user()->currentAccessToken()->id && ! $ticket->consumed_at && $ticket->expires_at->gt(now()) && hash_equals($ticket->identity_hash, $auth->fingerprint($user)) && ! $user->suspended_at, 403);
            $ticket->update(['consumed_at' => now()]);
            $deletion = app(DeletionRequest::class)->submit($request->user());

            return Reply::data(['id' => PublicIds::id($deletion), 'status' => 'pending', 'message' => 'Request submitted for review. Your account has not been deleted.'], 202);
        }, 3);
    }

    public function device(MobileRequest $request, string $installation_id)
    {
        $data = $request->validated();
        abort_unless($data['installation_id'] === $installation_id && $data['app'] === $request->user()->role->value, 403);

        return DB::transaction(function () use ($request, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', $data['fcm_token']);
            // One provider token cannot notify two accounts/installations.
            MobileDevice::where('token_hash', $hash)->where('installation_id', '!=', $data['installation_id'])->delete();
            $device = MobileDevice::updateOrCreate(['installation_id' => $data['installation_id']], ['user_id' => $request->user()->id, 'token_id' => $request->user()->currentAccessToken()->id, 'platform' => $data['platform'], 'app' => $data['app'], 'fcm_token' => $data['fcm_token'], 'token_hash' => $hash, 'enabled_events' => $data['enabled_events'], 'enabled' => true, 'binding_version' => (string) Str::uuid()]);

            return Reply::data(['installation_id' => $device->installation_id, 'platform' => $device->platform, 'app' => $device->app, 'enabled_events' => $device->enabled_events, 'updated_at' => MobileViews::time($device->updated_at)]);
        }, 3);
    }

    public function removeDevice(MobileRequest $request, string $installation_id)
    {
        MobileDevice::where('installation_id', $installation_id)->where('user_id', $request->user()->id)->where('token_id', $request->user()->currentAccessToken()->id)->firstOrFail()->delete();

        return response()->noContent();
    }
}
