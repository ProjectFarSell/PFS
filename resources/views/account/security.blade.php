@extends($user->role === \App\Enums\UserRole::Buyer ? 'layouts.app' : 'layouts.portal')
@section('title', 'Account security - FarSell')
@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <h1 class="text-2xl font-bold">Account security</h1>
    @if($errors->any())<div role="alert" class="rounded-xl border border-error p-4">{{ $errors->first() }}</div>@endif
    <section class="fs-card p-5 space-y-3">
        <h2 class="font-semibold">Confirm your identity</h2>
        @if($user->role === \App\Enums\UserRole::Admin && $user->mfa_confirmed_at)
            <p class="text-sm">Enter a current authenticator code or an unused recovery code. Each code can only be used once. @unless($mfaVerified)Complete this check to access the admin pages.@endunless</p>
            <form method="post" action="{{ route('security.confirm') }}" class="space-y-3">@csrf<label class="block text-sm">Authenticator or recovery code<input name="code" autocomplete="one-time-code" required maxlength="80" class="mt-1 block w-full rounded-lg border border-surface-border bg-surface p-3"></label><button class="btn-accent">Verify code</button></form>
        @else
            <p class="text-sm">Confirm before changing security settings. Confirmation lasts five minutes.</p>
            <form method="post" action="{{ route('security.confirm') }}" class="space-y-3">@csrf<label class="block text-sm">Current password<input type="password" name="password" autocomplete="current-password" required class="mt-1 block w-full rounded-lg border border-surface-border bg-surface p-3"></label><button class="btn-accent">Confirm password</button></form>
            @if($user->google_id)<form method="post" action="{{ route('security.google') }}">@csrf<button class="btn-outline">Confirm with your linked Google account</button></form>@endif
        @endif
        @if($confirmed)<p class="text-sm text-accent">Identity recently confirmed. Return to your previous page to submit your action.</p>@endif
    </section>
    @if($user->role === \App\Enums\UserRole::Admin)
    <section class="fs-card p-5 space-y-3">
        <h2 class="font-semibold">Admin two-factor authentication</h2>
        @if(!$user->mfa_confirmed_at)
            @if($setupUpdated)<p role="status" class="rounded-lg border border-accent p-3 text-sm">Authenticator setup has been updated. Start setup again and add the new FarSell QR code to your app. Your earlier unconfirmed entry will not match.</p>@endif
            <p class="text-sm">Admin access requires an authenticator app. First confirm your password or linked Google account, then start setup.</p>
            @if(!$secret)<form method="post" action="{{ route('security.mfa.setup') }}">@csrf<button class="btn-outline">Set up authenticator</button></form>
            @else
                <p class="text-sm">In Authy, Google Authenticator or another authenticator, choose Add account and scan this QR code. Use the new FarSell entry for <strong>{{ $user->email }}</strong>.</p>
                <img src="{{ $qrCode }}" alt="Scan this private FarSell authenticator setup QR code" width="320" height="320" class="max-w-full rounded-lg bg-white p-2">
                <details><summary class="cursor-pointer text-sm text-accent">Enter key manually instead</summary><p class="mt-2 text-sm">Time-based (TOTP), SHA-1, six digits, 30 seconds.</p><p class="mt-2 break-all rounded-lg bg-surface-muted p-3 font-mono text-sm" aria-label="Authenticator setup key">{{ $secret }}</p></details>
                <p class="text-xs text-text-muted">Keep this key private. It is only shown during setup, which expires after ten minutes.</p>
                <form method="post" action="{{ route('security.mfa.enable') }}" class="space-y-3">@csrf<input type="hidden" name="setup_id" value="{{ substr(hash('sha256', $secret), 0, 32) }}"><label class="block text-sm">Six-digit code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required class="mt-1 block rounded-lg border border-surface-border bg-surface p-3"></label><button class="btn-accent">Enable two-factor authentication</button></form>
            @endif
        @else
            <p class="text-sm">Enabled. {{ count($user->mfa_recovery_codes ?? []) }} recovery codes remain. Keep your server and phone clocks synchronized.</p>
            @if($mfaVerified)<form method="post" action="{{ route('security.mfa.recovery') }}">@csrf<button class="btn-outline">Replace recovery codes</button></form>@endif
        @endif
        @if($recoveryCodes)
            <div class="rounded-xl border border-accent p-4"><h3 class="font-bold">Save these recovery codes now</h3><p class="my-2 text-sm">Each works once. These codes will not be displayed again. Store them somewhere private, separate from your phone.</p><ul class="space-y-1 break-all font-mono text-sm">@foreach($recoveryCodes as $code)<li>{{ $code }}</li>@endforeach</ul></div>
        @endif
    </section>
    @endif
    <section class="fs-card p-5 space-y-3">
        <h2 class="font-semibold">Signed-in devices</h2>
        <p class="text-sm">Sign out other devices if you no longer recognize them. Remembered logins are also revoked.</p>
        @if(config('session.driver') !== 'database')<p class="text-sm text-text-muted">Device listing is available with database sessions. Signing out other devices still works.</p>@endif
        <ul class="space-y-3 text-sm">@foreach($sessions as $session)<li class="rounded-lg border border-surface-border p-3"><p class="break-words">{{ \Illuminate\Support\Str::limit($session->user_agent ?: 'Unknown browser', 180) }}</p><p class="text-xs text-text-muted">Last active {{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }} @if($session->id === request()->session()->getId()) · This device @endif</p></li>@endforeach</ul>
        <form method="post" action="{{ route('security.sessions.revoke') }}">@csrf<button class="btn-outline">Sign out other devices</button></form>
    </section>
    @if($user->role !== \App\Enums\UserRole::Admin || $mfaVerified)<a href="{{ route('account.profile') }}" class="inline-block text-sm text-accent underline">Back to my account</a>@endif
</div>
@endsection
