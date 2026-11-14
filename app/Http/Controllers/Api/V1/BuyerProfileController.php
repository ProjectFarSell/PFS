<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MobileRequest;
use App\Models\MobileTicket;
use App\Models\User;
use App\Services\Mobile\ApiFailure;
use App\Services\Mobile\MobileAuthentication;
use App\Services\Mobile\Replay;
use App\Services\Mobile\Reply;
use App\Services\Security\AuditTrail;
use App\Services\Security\SignInOtp;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BuyerProfileController extends Controller
{
    public function show(MobileRequest $request, MobileAuthentication $auth)
    {
        return Reply::data($auth->session($request->user(), $request->user()->currentAccessToken()->expires_at));
    }

    public function update(MobileRequest $request, MobileAuthentication $auth)
    {
        $data = array_intersect_key($request->validated(), array_flip(['name', 'bio', 'gender', 'birthday', 'email', 'phone', 'reauth_token']));
        $token = $request->user()->currentAccessToken();
        try {
            return DB::transaction(function () use ($request, $auth, $data, $token) {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $fields = array_diff_key($data, ['reauth_token' => true]);
                if (array_key_exists('phone', $fields) && $fields['phone'] !== null) {
                    $fields['phone'] = app(SignInOtp::class)->phone($fields['phone']);
                }
                $emailChanged = isset($fields['email']) && $fields['email'] !== $user->email;
                $phoneChanged = array_key_exists('phone', $fields) && $fields['phone'] !== $user->phone;
                if ($emailChanged || $phoneChanged) {
                    if (empty($data['reauth_token'])) {
                        throw ValidationException::withMessages(['reauth_token' => 'Confirm your identity before changing contact details.']);
                    }
                    [$ticket] = $auth->ticketUser($data['reauth_token'] ?? '', 'reauth');
                    $ticket = MobileTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
                    abort_unless($ticket->user_id === $user->id && $ticket->token_id === $token->id && ! $ticket->consumed_at && $ticket->expires_at->gt(now()) && hash_equals($ticket->identity_hash, $auth->fingerprint($user)), 403);
                    $ticket->update(['consumed_at' => now()]);
                }
                if ($emailChanged) {
                    if (User::whereRaw('LOWER(email) = ?', [$fields['email']])->whereKeyNot($user->id)->exists()) {
                        throw ValidationException::withMessages(['email' => 'This email address is already in use.']);
                    }
                    DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                    $user->email_verified_at = null;
                }
                if ($phoneChanged) {
                    $user->phone_verified_at = null;
                }
                $user->forceFill($fields)->save();
                if ($emailChanged || $phoneChanged) {
                    $token->forceFill(['identity_hash' => $auth->fingerprint($user)])->save();
                }
                app(AuditTrail::class)->record('buyer.profile_updated', $user, ['fields' => array_keys($fields)]);

                return Reply::data($auth->session($user, $token->expires_at));
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This email address is already in use.']);
        }
    }

    public function avatar(MobileRequest $request)
    {
        $path = $request->user()->avatar_path;
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return response(Storage::disk('public')->get($path))
            ->header('Content-Type', Storage::disk('public')->mimeType($path) ?: 'image/jpeg')
            ->header('Cache-Control', 'private, no-store');
    }

    public function uploadAvatar(MobileRequest $request, MobileAuthentication $auth, Replay $replay)
    {
        $data = $request->validated();
        $newPath = null;
        try {
            return $replay->run($request, $data, function () use ($request, $auth, &$newPath) {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $oldPath = $user->avatar_path;
                $newPath = $request->file('avatar')->store('avatars', 'public');
                if (! $newPath) {
                    throw new ApiFailure('service_unavailable', 'Your profile photo could not be saved. Please retry.', 503);
                }
                $user->forceFill(['avatar_path' => $newPath])->save();
                if ($oldPath && str_starts_with($oldPath, 'avatars/')) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete($oldPath));
                }

                return Reply::data($auth->session($user, $request->user()->currentAccessToken()->expires_at));
            });
        } catch (\Throwable $error) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            throw $error;
        }
    }
}
