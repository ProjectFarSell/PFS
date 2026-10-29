@extends('layouts.app')

@section('title', 'Verify your contact details · FarSell')

@section('content')
    <div class="mx-auto max-w-lg fs-card p-6">
        <p class="section-eyebrow">Account verification</p>
        <h1 class="mt-2 text-2xl font-bold text-text-base">{{ $channel ? 'Verify your '.$channel : 'Verify your Account' }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-text-muted">{{ $channel ? 'Enter the six-digit code sent to your chosen verification method. Codes expire after 10 minutes.' : 'Choose one method to receive your six-digit verification code.' }}</p>

        @if (session('status'))
            <div class="mt-4 rounded-lg border border-success/30 bg-success/10 px-4 py-3 text-sm text-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert-error mt-4" role="alert">{{ $errors->first() }}</div>
        @endif

        @if (! $channel)
            <form method="post" action="{{ route('contact-verification.choose') }}" class="mt-6 space-y-4" x-data="{ channel: 'email' }">
                @csrf
                <div><label for="verification_channel" class="fs-label">Verify my Account with</label><select id="verification_channel" name="channel" x-model="channel" class="fs-input"><option value="email">Email</option><option value="phone">Phone number</option></select></div>
                <div x-show="channel === 'phone'" x-cloak><label for="verification_phone" class="fs-label">Phone number</label><input id="verification_phone" type="tel" name="phone" autocomplete="tel" maxlength="24" :required="channel === 'phone'" placeholder="09123456789" class="fs-input"></div>
                <button class="btn-accent min-h-11 w-full">Send verification code</button>
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
            @endunless
            </section>
        @endif

        <a href="{{ route('home') }}" class="mt-6 inline-flex min-h-10 items-center text-sm font-semibold text-accent hover:underline">Continue to FarSell</a>
    </div>
@endsection
