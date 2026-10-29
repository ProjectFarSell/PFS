<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\VerificationChallenge;
use App\Services\Auth\ContactVerificationService;
use App\Services\Auth\FirebasePhoneVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ContactVerificationController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('auth.verify-contact', [
            'user' => $user,
            'channel' => $user->verification_channel,
            'firebase' => [
                'apiKey' => config('services.firebase.api_key'),
                'authDomain' => config('services.firebase.auth_domain'),
                'projectId' => config('services.firebase.project_id'),
                'appId' => config('services.firebase.app_id'),
            ],
            'firebasePhone' => $user->verification_channel === VerificationChallenge::PHONE
                ? $this->phoneForFirebase($user->phone)
                : null,
        ]);
    }

    public function choose(Request $request, ContactVerificationService $verification): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in([VerificationChallenge::EMAIL, VerificationChallenge::PHONE])],
            'phone' => ['nullable', 'required_if:channel,phone', 'string', 'max:24', 'regex:/^\\+?[0-9][0-9\\s()\\-]{6,22}$/'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'verification_channel' => $data['channel'],
            'phone' => $data['channel'] === VerificationChallenge::PHONE ? trim($data['phone']) : $user->phone,
        ])->save();

        if ($data['channel'] === VerificationChallenge::PHONE) {
            return to_route('contact-verification.show')->with('status', 'Continue with Firebase to verify your phone number.');
        }

        try {
            $verification->send($user, $data['channel']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Unable to send chosen verification code.', ['channel' => $data['channel'], 'exception' => $exception::class]);

            return to_route('contact-verification.show')->withErrors(['code' => 'We could not send a code right now. Please try again.']);
        }

        return to_route('contact-verification.show')->with('status', ucfirst($data['channel']).' verification code sent.');
    }

    public function send(Request $request, string $channel, ContactVerificationService $verification): RedirectResponse
    {
        $this->validateChannel($channel);
        abort_unless($channel === $request->user()->verification_channel, 404);

        try {
            $verification->send($request->user(), $channel);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Unable to send verification code.', ['channel' => $channel, 'exception' => $exception::class]);

            return to_route('contact-verification.show')->withErrors(['code' => 'We could not send a code right now. Please try again.']);
        }

        return to_route('contact-verification.show')->with('status', ucfirst($channel).' verification code sent.');
    }

    public function verify(Request $request, string $channel, ContactVerificationService $verification): RedirectResponse
    {
        $this->validateChannel($channel);
        abort_unless($channel === $request->user()->verification_channel, 404);
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        if (! $verification->verify($request->user(), $channel, $data['code'])) {
            return to_route('contact-verification.show')->withErrors(['code' => 'That code is invalid, expired, or has already been used.']);
        }

        return to_route('contact-verification.show')->with('status', ucfirst($channel).' verified.');
    }

    public function verifyFirebasePhone(Request $request, FirebasePhoneVerificationService $firebase): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->verification_channel === VerificationChallenge::PHONE, 404);
        $data = $request->validate(['id_token' => ['required', 'string', 'max:10000']]);
        try {
            $firebasePhone = $firebase->verify($data['id_token']);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Firebase phone verification failed.', 'errors' => $exception->errors()], 422);
            }

            return to_route('contact-verification.show')->withErrors($exception->errors());
        }
        $user = $request->user();
        if ($this->phoneForComparison($user->phone) !== $this->phoneForComparison($firebasePhone)) {
            $errors = ['code' => 'The Firebase phone number does not match the phone number selected for this account.'];

            return $request->expectsJson()
                ? response()->json(['message' => 'Firebase phone verification failed.', 'errors' => $errors], 422)
                : to_route('contact-verification.show')->withErrors($errors);
        }

        $user->forceFill(['phone_verified_at' => now()])->save();

        return $request->expectsJson()
            ? response()->json(['verified' => true])
            : to_route('contact-verification.show')->with('status', 'Phone verified.');
    }

    private function validateChannel(string $channel): void
    {
        validator(['channel' => $channel], [
            'channel' => [Rule::in([VerificationChallenge::EMAIL, VerificationChallenge::PHONE])],
        ])->validate();
    }

    private function phoneForComparison(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (str_starts_with($digits, '0')) {
            $digits = '63'.substr($digits, 1);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '63'.$digits;
        }

        return $digits;
    }

    private function phoneForFirebase(?string $phone): string
    {
        $digits = $this->phoneForComparison($phone);

        return $digits === '' ? '' : '+'.$digits;
    }
}
