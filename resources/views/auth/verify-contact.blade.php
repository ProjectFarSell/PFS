@extends('layouts.app')

@section('title', 'Verify your contact details · FarSell')

@section('content')
    <div class="mx-auto max-w-lg fs-card p-6">
        <p class="section-eyebrow">Account verification</p>
        <h1 class="mt-2 text-2xl font-bold text-text-base">{{ $channel ? 'Verify your '.$channel : 'Verify your Account' }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-text-muted">{{ $channel === 'phone' ? 'Firebase will send a verification code by SMS after its security check.' : ($channel ? 'Enter the six-digit code sent to your chosen verification method. Codes expire after 10 minutes.' : 'Choose one method to receive your six-digit verification code.') }}</p>

        @if ($errors->any())
            <div class="alert-error mt-4" role="alert">{{ $errors->first() }}</div>
        @endif

        @if (! $channel)
            <form method="post" action="{{ route('contact-verification.choose') }}" class="mt-6 space-y-4" x-data="{ channel: 'email' }">
                @csrf
                <div><label for="verification_channel" class="fs-label">Verify my Account with</label><select id="verification_channel" name="channel" x-model="channel" class="fs-input"><option value="email">Email</option><option value="phone">Phone number</option></select></div>
                <div x-show="channel === 'phone'" x-cloak><label for="verification_phone" class="fs-label">Phone number</label><input id="verification_phone" type="tel" name="phone" autocomplete="tel" maxlength="24" :required="channel === 'phone'" placeholder="09123456789" class="fs-input"></div>
                <button class="btn-accent min-h-11 w-full">Continue</button>
            </form>
        @else
            @php($verifiedAt = $channel === 'email' ? $user->email_verified_at : $user->phone_verified_at)
            <section class="mt-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold capitalize text-text-base">{{ $channel }}</h2>
                    <p class="mt-1 text-sm text-text-muted">{{ $channel === 'email' ? $user->email : $user->phone }}</p>
                </div>
                @if ($verifiedAt)
                    <span class="text-sm font-medium text-success">Verified</span>
                @endif
            </div>
            @unless ($verifiedAt)
                @if ($channel === 'phone')
                    @if (blank($firebase['apiKey']) || blank($firebase['authDomain']) || blank($firebase['projectId']) || blank($firebase['appId']))
                        <p class="mt-3 text-sm text-error">Firebase Phone Auth has not been configured yet. Ask an administrator to add the Firebase settings.</p>
                    @else
                        <div class="mt-4 space-y-3" id="firebase-phone-verification" data-config='@json($firebase)' data-verify-url="{{ route('contact-verification.phone.firebase') }}">
                            <button type="button" id="firebase-send-code" class="btn-outline min-h-10 px-4 text-sm">Send SMS code</button>
                            <div id="firebase-recaptcha"></div>
                            <div id="firebase-code-entry" class="hidden flex gap-3">
                                <label class="sr-only" for="firebase_phone_code">Phone verification code</label>
                                <input id="firebase_phone_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" class="fs-input max-w-44">
                                <button type="button" id="firebase-verify-code" class="btn-accent min-h-10 px-4 text-sm">Verify</button>
                            </div>
                            <p id="firebase-phone-message" class="hidden text-sm" role="status"></p>
                        </div>
                    @endif
                @else
                    <form method="post" action="{{ route('contact-verification.send', $channel) }}" class="mt-3">
                        @csrf
                        <button class="btn-outline min-h-10 px-4 text-sm">Send code</button>
                    </form>
                    <form method="post" action="{{ route('contact-verification.verify', $channel) }}" class="mt-3 flex gap-3">
                        @csrf
                        <label class="sr-only" for="{{ $channel }}_code">{{ ucfirst($channel) }} verification code</label>
                        <input id="{{ $channel }}_code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="6-digit code" class="fs-input max-w-44">
                        <button class="btn-accent min-h-10 px-4 text-sm">Verify</button>
                    </form>
                @endif
            @endunless
            </section>
        @endif

        <a href="{{ route('home') }}" class="mt-6 inline-flex min-h-10 items-center text-sm font-semibold text-accent hover:underline">Continue to FarSell</a>
    </div>
@endsection

@push('scripts')
    @if ($channel === 'phone' && ! $verifiedAt && filled($firebase['apiKey']) && filled($firebase['authDomain']) && filled($firebase['projectId']) && filled($firebase['appId']))
        <script type="module">
            const container = document.getElementById('firebase-phone-verification');
            const sendButton = document.getElementById('firebase-send-code');
            const verifyButton = document.getElementById('firebase-verify-code');
            const codeEntry = document.getElementById('firebase-code-entry');
            const codeInput = document.getElementById('firebase_phone_code');
            const message = document.getElementById('firebase-phone-message');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            let confirmationResult;

            const setMessage = (text, error = false) => {
                message.textContent = text;
                message.className = `text-sm ${error ? 'text-error' : 'text-text-muted'}`;
            };

            try {
                const config = JSON.parse(container.dataset.config);
                const { initializeApp } = await import('https://www.gstatic.com/firebasejs/11.0.2/firebase-app.js');
                const { getAuth, RecaptchaVerifier, signInWithPhoneNumber } = await import('https://www.gstatic.com/firebasejs/11.0.2/firebase-auth.js');
                const auth = getAuth(initializeApp(config));
                const verifier = new RecaptchaVerifier(auth, 'firebase-recaptcha', { size: 'invisible' });

                sendButton.addEventListener('click', async () => {
                    sendButton.disabled = true;
                    setMessage('Sending SMS code...');
                    try {
                        confirmationResult = await signInWithPhoneNumber(auth, @json($firebasePhone), verifier);
                        codeEntry.classList.remove('hidden');
                        setMessage('SMS code sent.');
                        codeInput.focus();
                    } catch (error) {
                        const details = {
                            'auth/invalid-phone-number': 'Use a valid phone number, for example 09123456789.',
                            'auth/operation-not-allowed': 'Enable Phone as a sign-in provider in Firebase Authentication.',
                            'auth/billing-not-enabled': 'Firebase billing must be enabled before it can send real SMS messages.',
                            'auth/quota-exceeded': 'Firebase SMS quota has been reached. Try again later.',
                            'auth/too-many-requests': 'Firebase temporarily limited SMS requests. Wait a few minutes before trying again.',
                            'auth/captcha-check-failed': 'Firebase reCAPTCHA could not verify this browser. Confirm localhost is an authorized domain and try again.',
                            'auth/invalid-app-credential': 'Firebase reCAPTCHA could not verify this browser. Confirm localhost is an authorized domain and try again.',
                        };
                        setMessage(details[error.code] || 'Firebase could not send a code. Check the Firebase configuration and try again.', true);
                        verifier.render().then((widgetId) => window.grecaptcha?.reset(widgetId));
                    } finally {
                        sendButton.disabled = false;
                    }
                });

                verifyButton.addEventListener('click', async () => {
                    if (! confirmationResult || ! /^[0-9]{6}$/.test(codeInput.value)) {
                        setMessage('Enter the six-digit SMS code first.', true);
                        return;
                    }
                    verifyButton.disabled = true;
                    setMessage('Confirming your phone number...');
                    try {
                        const credential = await confirmationResult.confirm(codeInput.value);
                        const idToken = await credential.user.getIdToken();
                        const response = await fetch(container.dataset.verifyUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                            body: JSON.stringify({ id_token: idToken }),
                        });
                        if (! response.ok) throw new Error('Phone verification failed.');
                        window.location.reload();
                    } catch (_) {
                        setMessage('Firebase could not verify that code. Please try again.', true);
                    } finally {
                        verifyButton.disabled = false;
                    }
                });
            } catch (_) {
                setMessage('Firebase Phone Auth could not start. Check the Firebase settings.', true);
            }
        </script>
    @endif
@endpush
