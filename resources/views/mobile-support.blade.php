@extends('layouts.app')
@section('title', 'Mobile support · FarSell')
@section('content')
<section class="mx-auto max-w-2xl rounded-2xl border border-surface-border bg-surface p-6">
    <h1 class="text-2xl font-semibold">FarSell mobile support</h1>
    <p class="mt-4">For help with an order, contact the seller from your order or contact the marketplace administrator through your group's agreed support channel.</p>
    @if(config('mobile.support_email'))<p class="mt-3">Support email: <a href="mailto:{{ config('mobile.support_email') }}" class="underline">{{ config('mobile.support_email') }}</a></p>@endif
    <h2 class="mt-6 text-lg font-semibold">Request account deletion</h2>
    <p class="mt-3">Sign in on the website, open My Account → Edit profile → Privacy, confirm your identity and submit an account deletion request. Mobile clients can submit the same reviewed request after recent authentication.</p>
    <p class="mt-3">Submitting a request does not immediately erase your account. Administrators review account information and whether order, delivery, financial or dispute records must be retained. The final retention and anonymization policy must be published before store release.</p>
    <p class="mt-4"><a href="{{ route('privacy') }}" class="underline">Privacy notice</a></p>
</section>
@endsection
