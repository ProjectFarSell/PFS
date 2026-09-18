<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Support\CheckoutIntent;
use App\Support\GuestSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login', ['checkoutIntent' => CheckoutIntent::active()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $identifier = $request->input('email');
        $isAdminAlias = is_string($identifier) && strcasecmp(trim($identifier), 'admin') === 0;
        $credentials = $request->validate([
            'email' => $isAdminAlias ? ['required', 'string'] : ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // The demo alias still requires the account's real password and admin role.
        if ($isAdminAlias) {
            $credentials['email'] = 'admin@farsell.test';
            $credentials['role'] = UserRole::Admin->value;
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Use a named error bag so portal form can distinguish login vs register errors.
            return back()
                ->withErrors(['email' => 'Those credentials do not match our records.'], 'login')
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        GuestSession::forget();

        return redirect()->intended(route($request->user()->role === UserRole::Admin ? 'admin.dashboard' : 'home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
