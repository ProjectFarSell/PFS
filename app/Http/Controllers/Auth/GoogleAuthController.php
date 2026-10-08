<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CheckoutIntent;
use App\Support\GuestSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'Google sign-in is not configured.');
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);
        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->session()->pull('google_oauth_state');
        abort_unless(is_string($state) && hash_equals($state, (string) $request->query('state')), 419, 'Google sign-in expired. Please try again.');
        if ($request->query('error') || ! $request->filled('code')) {
            return to_route('login')->withErrors(['google' => 'Google sign-in was cancelled.']);
        }

        $oauthStage = 'token exchange';
        try {
            $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $request->query('code'),
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
            ])->throw()->json();
            $oauthStage = 'profile lookup';
            $profile = Http::withToken($tokenResponse['access_token'] ?? '')->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
        } catch (Throwable $exception) {
            Log::warning('Google sign-in request failed.', [
                'stage' => $oauthStage,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return to_route('login')->withErrors(['google' => 'Google sign-in could not be completed. Please try again.']);
        }

        if (empty($profile['sub']) || empty($profile['email']) || ! filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN) || empty($profile['name'])) {
            return to_route('login')->withErrors(['google' => 'Google did not provide a verified email address for this account.']);
        }

        $user = User::query()->where('google_id', $profile['sub'])->first();
        if (! $user) {
            $normalizedEmail = mb_strtolower($profile['email']);
            $user = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();
            if ($user) {
                // Link only after Google has verified ownership of the matching email.
                $user->forceFill(['google_id' => $profile['sub'], 'email_verified_at' => $user->email_verified_at ?? now()])->save();
            } else {
                $user = User::query()->create([
                    'name' => mb_substr($profile['name'], 0, 120),
                    'email' => mb_strtolower($profile['email']),
                    'password' => Str::random(64),
                    'google_id' => $profile['sub'],
                    'email_verified_at' => now(),
                    'role' => UserRole::Buyer,
                ]);
            }
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        GuestSession::forget();

        if (CheckoutIntent::active() && $user->role === UserRole::Buyer) {
            return redirect()->intended(route('checkout.create'));
        }

        return redirect()->intended(match ($user->role) {
            UserRole::Admin => route('admin.dashboard'),
            UserRole::Rider => route('rider.dashboard'),
            UserRole::Seller => route('seller.dashboard'),
            default => route('home'),
        });
    }
}
