@extends('layouts.app')
@section('title', 'Finish signing in - FarSell')
@section('content')
<div class="fs-card mx-auto max-w-lg space-y-5 p-6">
    <p class="section-eyebrow">Sign-in verification</p>
    <h1 class="text-2xl font-bold">Finish signing in</h1>
    <p class="text-sm text-text-muted">Enter a fresh code from your account email or saved phone number. This check is required after password or Google sign-in.</p>
    @if($errors->any())<div role="alert" class="alert-error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('security.otp.send') }}" class="space-y-3">@csrf
        <label class="block text-sm" for="sign-in-channel">Receive your code by</label>
        <select name="channel" id="sign-in-channel" class="fs-input">
            <option value="email" @selected(($challenge?->channel ?? $user->verification_channel) !== 'phone')>Email: {{ $user->email }}</option>
            @if($user->phone && $phoneConfigured)<option value="phone" @selected(($challenge?->channel ?? $user->verification_channel) === 'phone')>Phone: {{ $user->phone }}</option>@endif
        </select>
        <x-ui.button variant="secondary" type="submit">{{ $challenge ? 'Request a new code' : 'Continue' }}</x-ui.button>
        <p class="text-xs text-text-muted">Codes expire after ten minutes. Wait one minute between requests. Phone verification is available when a phone is saved and SMS is configured.</p>
    </form>
    @if($challenge && !$challenge->consumed_at && $challenge->expires_at->isFuture())
        @if($challenge->channel === 'email')
            <form method="post" action="{{ route('security.otp.verify') }}" class="space-y-3">@csrf
                <label for="sign-in-code" class="block text-sm">Six-digit email code</label>
                <input id="sign-in-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="fs-input">
                <x-ui.button variant="primary" size="lg" type="submit">Verify and continue</x-ui.button>
            </form>
        @elseif($phoneConfigured)
            <div class="space-y-3" id="sign-in-phone" data-config='@json($firebase)' data-phone="{{ $firebasePhone }}" data-verify-url="{{ route('security.otp.phone') }}">
                <x-ui.button variant="secondary" type="button" id="sign-in-send-sms">Send SMS code</x-ui.button>
                <div id="sign-in-recaptcha"></div>
                <div id="sign-in-phone-entry" class="hidden space-y-3">
                    <label for="sign-in-sms-code" class="block text-sm">Six-digit SMS code</label>
                    <input id="sign-in-sms-code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="fs-input">
                    <x-ui.button variant="primary" size="lg" type="button" id="sign-in-verify-sms">Verify and continue</x-ui.button>
                </div>
                <p id="sign-in-phone-message" role="status" class="text-sm"></p>
            </div>
        @endif
    @elseif($challenge)
        <p class="text-sm text-error">This challenge expired or was used. Request a new code to continue.</p>
    @endif
    <p class="text-xs text-text-muted">Cannot access either contact? Contact support. A new destination cannot be substituted during this sign-in.</p>
    <form method="post" action="{{ route('logout') }}">@csrf<x-ui.button variant="tertiary" type="submit" class="text-sm text-accent underline">Sign out / use another account</x-ui.button></form>
</div>
@endsection
@push('scripts')
@if($challenge?->channel === 'phone' && $phoneConfigured && !$challenge->consumed_at && $challenge->expires_at->isFuture())
<script type="module">
const container = document.getElementById('sign-in-phone');
const send = document.getElementById('sign-in-send-sms');
const verify = document.getElementById('sign-in-verify-sms');
const message = document.getElementById('sign-in-phone-message');
const code = document.getElementById('sign-in-sms-code');
let confirmation, sending = false;
try {
    const { initializeApp } = await import('https://www.gstatic.com/firebasejs/11.0.2/firebase-app.js');
    const { getAuth, RecaptchaVerifier, signInWithPhoneNumber, inMemoryPersistence, setPersistence, signOut } = await import('https://www.gstatic.com/firebasejs/11.0.2/firebase-auth.js');
    const auth = getAuth(initializeApp(JSON.parse(container.dataset.config), 'farsell-sign-in'));
    await setPersistence(auth, inMemoryPersistence);
    const captcha = new RecaptchaVerifier(auth, 'sign-in-recaptcha', { size: 'invisible' });
    send.addEventListener('click', async () => {
        if (sending) return;
        sending = true; send.disabled = true; message.textContent = 'Sending SMS code…';
        try {
            confirmation = await signInWithPhoneNumber(auth, container.dataset.phone, captcha);
            document.getElementById('sign-in-phone-entry').classList.remove('hidden');
            message.textContent = 'SMS code sent. Enter it below.'; code.focus();
        } catch (_) {
            message.textContent = 'SMS could not be sent. Check your connection or use email. Contact support if it continues.';
            captcha.render().then(id => window.grecaptcha?.reset(id)).catch(() => {});
        } finally { sending = false; send.disabled = false; }
    });
    verify.addEventListener('click', async () => {
        if (!confirmation || !/^[0-9]{6}$/.test(code.value)) { message.textContent = 'Enter your six-digit SMS code.'; return; }
        verify.disabled = true; message.textContent = 'Checking code…';
        try {
            const credential = await confirmation.confirm(code.value);
            const response = await fetch(container.dataset.verifyUrl, { method: 'POST', headers: {
                'Content-Type': 'application/json', Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }, body: JSON.stringify({ id_token: await credential.user.getIdToken(true) }) });
            const result = await response.json();
            if (!response.ok || !result.verified) throw new Error('Verification failed');
            await signOut(auth);
            location.assign(result.url);
        } catch (_) { message.textContent = 'Code could not be verified. Request a fresh code and try again.'; }
        finally { verify.disabled = false; }
    });
} catch (_) { message.textContent = 'SMS verification could not start. Use email or contact support.'; send.disabled = true; }
</script>
@endif
@endpush
