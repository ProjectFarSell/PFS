@extends('layouts.auth')
@section('title', 'Reset password - FarSell')
@section('content')
<div class="mx-auto max-w-lg px-4 py-12">
    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center text-sm text-accent underline">Back to login</a>
    <section class="fs-card mt-4 space-y-4 p-6">
        <p class="section-eyebrow">Account recovery</p>
        <h1 class="text-2xl font-bold">Choose a new password</h1>
        <p class="text-sm text-text-muted">Use a strong password you do not use on another site.</p>
        @if($errors->any())<p role="alert" class="alert-error">{{ $errors->first() }}</p>@endif
        <form method="post" action="{{ route('password.store') }}" class="space-y-4">@csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">
            <p class="break-words text-sm">Account: {{ $email }}</p>
            <div><label for="new-password" class="fs-label">New password</label><x-password-input id="new-password" name="password" autocomplete="new-password" placeholder="New password" /></div>
            <div><label for="new-password-confirmation" class="fs-label">Confirm new password</label><x-password-input id="new-password-confirmation" name="password_confirmation" autocomplete="new-password" placeholder="Repeat new password" /></div>
            <button class="btn-accent min-h-11 w-full">Reset password</button>
        </form>
        <a href="{{ route('password.request') }}" class="inline-flex min-h-11 items-center text-sm text-accent underline">Link expired? Request a new one</a>
    </section>
</div>
@endsection
