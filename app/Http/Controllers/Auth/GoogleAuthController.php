<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleAccounts;
use App\Services\Security\AccountSecurity;
use App\Services\Security\AuditTrail;
use App\Support\CheckoutIntent;
use App\Support\GuestSession;
use App\Support\PrivacyNotice;
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
        return $this->start($request);
    }

    public function start(Request $request): RedirectResponse
    {
        // Loopback aliases use different browser cookies. Start OAuth on the
        // configured callback origin so its state returns to the same session.
        $callback = parse_url((string) config('services.google.redirect'));
        $loopback = ['localhost', '127.0.0.1'];
        if (app()->environment('local', 'testing') &&
            in_array($request->getHost(), $loopback, true) &&
            in_array($callback['host'] ?? null, $loopback, true) &&
            in_array($callback['scheme'] ?? null, ['http', 'https'], true)) {
            $origin = $callback['scheme'].'://'.$callback['host'].(isset($callback['port']) ? ':'.$callback['port'] : '');
            if ($request->getSchemeAndHttpHost() !== $origin) {
                return redirect()->away($origin.route('auth.google.redirect', [], false));
            }
        }

        $request->session()->forget('google_reauth');
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'Google sign-in is not configured.');
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);
        $request->session()->put('google_privacy_consent', PrivacyNotice::VERSION);
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
        $reauth = $request->session()->pull('google_reauth');
        $state = $request->session()->pull('google_oauth_state');
        $consent = $request->session()->pull('google_privacy_consent');
        abort_unless(is_string($state) && hash_equals($state, (string) $request->query('state')), 419, 'Google sign-in expired. Please try again.');
        abort_unless($consent === PrivacyNotice::VERSION, 419, 'Please review the privacy notice before signing in with Google.');
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

        if ($reauth) {
            abort_unless($request->user() && $reauth['user_id'] === $request->user()->id && $reauth['expires'] > now()->timestamp, 419, 'Identity confirmation expired.');
            if (! hash_equals((string) $request->user()->google_id, (string) $profile['sub'])) {
                return to_route('security.index')->withErrors(['identity' => 'Choose the Google account linked to your current FarSell account.']);
            }
            app(AccountSecurity::class)->confirm($request);
            app(AuditTrail::class)->record('identity.google_confirmed', $request->user());

            return to_route('security.index')->with('status', 'Google identity confirmed. Admin authenticator verification is still required when enabled.');
        }
        abort_if($request->user() !== null, 409, 'Sign out before switching accounts.');

        $user = app(GoogleAccounts::class)->resolve($profile);

        if ($user->suspended_at) {
            return to_route('login')->withErrors(['google' => 'This account is suspended. Contact an administrator.']);
        }
        $user->forceFill(['privacy_accepted_at' => now(), 'privacy_notice_version' => PrivacyNotice::VERSION])->save();
        Auth::login($user, true);
        app(AccountSecurity::class)->start($request, $user);
        GuestSession::forget();

        if (CheckoutIntent::active() && $user->role === UserRole::Buyer) {
            return app(AccountSecurity::class)->afterPrimarySignIn($request, redirect()->intended(route('checkout.create')));
        }

        return app(AccountSecurity::class)->afterPrimarySignIn($request, redirect()->intended(match ($user->role) {
            UserRole::Admin => route('admin.dashboard'),
            UserRole::Rider => route('rider.dashboard'),
            UserRole::Seller => route('seller.dashboard'),
            default => route('home'),
        }));
    }

    public function reauthenticate(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->google_id, 403);
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'Google sign-in is not configured.');
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);
        $request->session()->put('google_privacy_consent', PrivacyNotice::VERSION);
        $request->session()->put('google_reauth', ['user_id' => $request->user()->id, 'expires' => now()->addMinutes(5)->timestamp]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'), 'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code', 'scope' => 'openid email profile', 'state' => $state, 'prompt' => 'select_account', 'max_age' => 0,
        ]));
    }
}
