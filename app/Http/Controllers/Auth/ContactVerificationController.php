<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\VerificationChallenge;
use App\Services\Auth\ContactVerificationService;
use Illuminate\Http\RedirectResponse;
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

    private function validateChannel(string $channel): void
    {
        validator(['channel' => $channel], [
            'channel' => [Rule::in([VerificationChallenge::EMAIL, VerificationChallenge::PHONE])],
        ])->validate();
    }
}
