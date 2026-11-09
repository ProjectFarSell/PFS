<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Security\AccountSecurity;
use App\Services\Security\AuditTrail;
use App\Services\Security\SignInOtp;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SignInOtpController extends Controller
{
    public function show(Request $request, SignInOtp $otp, AccountSecurity $security)
    {
        abort_if($request->user()->usesAuthenticator(), 403);
        if ($security->otpVerified($request)) {
            return $security->returnAfterChallenge($request);
        }

        return view('auth.login-otp', ['user' => $request->user(), 'challenge' => $otp->challenge($request),
            'phoneConfigured' => $otp->phoneConfigured(), 'firebasePhone' => $otp->phone($request->user()->phone),
            'firebase' => ['apiKey' => config('services.firebase.api_key'), 'authDomain' => config('services.firebase.auth_domain'),
                'projectId' => config('services.firebase.project_id'), 'appId' => config('services.firebase.app_id')]]);
    }

    public function send(Request $request, SignInOtp $otp)
    {
        abort_if($request->user()->usesAuthenticator(), 403);
        $data = $request->validate(['channel' => ['required', 'in:email,phone']]);
        try {
            $otp->issue($request, $data['channel']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::warning('Sign-in code delivery failed.', ['exception' => $exception::class]);
            throw ValidationException::withMessages(['code' => 'We could not send a code. Try again or contact support.']);
        }

        return to_route('security.otp.show')->with('status', $data['channel'] === 'email' ? 'Sign-in code sent to your account email.' : 'Continue with the SMS security check.');
    }

    public function verify(Request $request, SignInOtp $otp, AccountSecurity $security)
    {
        abort_if($request->user()->usesAuthenticator(), 403);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $identity = $otp->verifyEmail($request, $data['code']);
        if (! $identity) {
            throw ValidationException::withMessages(['code' => 'Code invalid, expired, already used, or attempt limit reached. Request a new code.']);
        }

        return $this->complete($request, $security, $identity);
    }

    public function phone(Request $request, SignInOtp $otp, AccountSecurity $security)
    {
        abort_if($request->user()->usesAuthenticator(), 403);
        $data = $request->validate(['id_token' => ['required', 'string', 'max:10000']]);
        try {
            $identity = $otp->verifyPhone($request, $data['id_token']);
        } catch (UniqueConstraintViolationException) {
            $identity = null;
        }
        if (! $identity) {
            throw ValidationException::withMessages(['code' => 'SMS verification failed. Request a fresh code for this sign-in and use your saved account phone.']);
        }
        $redirect = $this->complete($request, $security, $identity);

        return response()->json(['verified' => true, 'url' => $redirect->getTargetUrl()]);
    }

    private function complete(Request $request, AccountSecurity $security, string $identity)
    {
        $request->user()->refresh();
        if ($request->user()->usesAuthenticator() || $request->user()->suspended_at || ! hash_equals($identity, $security->otpFingerprint($request->user()))) {
            throw ValidationException::withMessages(['code' => 'Account details changed. Request a new sign-in code.']);
        }
        $request->session()->put('security.otp', $identity);
        $request->session()->forget('security.otp_context');
        $security->confirm($request);
        app(AuditTrail::class)->record('identity.otp_confirmed', $request->user());

        return $security->returnAfterChallenge($request);
    }
}
