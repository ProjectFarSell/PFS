<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CheckoutIntent;
use App\Support\GuestSession;
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
        ])->validateWithBag('register');

        // Role stays Buyer at registration. Seller/Rider access is granted only
        // after the relevant application is actually approved, not on signup intent.
        $intent = $data['intent'] ?? 'buyer';

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Buyer,
        ]);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        GuestSession::forget();

        if (CheckoutIntent::active()) {
            return redirect()->intended(route('checkout.create'));
        }

        if ($intent === 'rider') {
            return redirect()->route('rider.register');
        }

        if ($intent === 'seller') {
            return redirect()->route('seller.apply');
        }

        return redirect()->route('home');
    }
}
