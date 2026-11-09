<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\AccountSecurity;
use App\Support\CheckoutIntent;
use App\Support\GuestSession;
use App\Support\PrivacyNotice;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('welcome', ['checkoutIntent' => CheckoutIntent::active(), 'initialTab' => 'register']);
    }

    public function store(Request $request): RedirectResponse
    {
        // Use a named 'register' error bag so the portal can distinguish
        // login errors from registration errors in the same view.
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'intent' => ['nullable', 'in:buyer,seller,rider'],
            'privacy_consent' => ['required', 'accepted'],
        ], ['privacy_consent.accepted' => 'Please read and agree to the privacy notice.', 'privacy_consent.required' => 'Please read and agree to the privacy notice.'])->validateWithBag('register');

        // Role stays Buyer at registration. Seller/Rider access is granted only
        // after the relevant application is actually approved, not on signup intent.
        $intent = $data['intent'] ?? 'buyer';

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Buyer,
        ]);

        $user->forceFill(['privacy_accepted_at' => now(), 'privacy_notice_version' => PrivacyNotice::VERSION])->save();

        event(new Registered($user));
        Auth::login($user);
        app(AccountSecurity::class)->start($request, $user);
        GuestSession::forget();

        // Keep the browser on its current local hostname so the new session cookie remains available.
        return app(AccountSecurity::class)->afterPrimarySignIn($request, redirect()->away('/account/verify'));
    }
}
