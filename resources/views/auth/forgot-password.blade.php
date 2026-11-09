@extends('layouts.auth')
@section('title', 'Forgot password - FarSell')
@section('content')
<div class="mx-auto max-w-lg px-4 py-12">
    <x-ui.button variant="link" :href="route('login')" class="inline-flex min-h-11 items-center text-sm text-accent underline">Back to login</x-ui.button>
    <section class="fs-card mt-4 space-y-4 p-6">
        <p class="section-eyebrow">Account recovery</p>
        <h1 class="text-2xl font-bold">Forgot your password?</h1>
        <p class="text-sm text-text-muted">Enter your account email. We will email a link to choose a new password.</p>
        @if(session('status'))<p role="status" class="rounded-lg border border-accent p-3 text-sm">{{ session('status') }}</p>@endif
        @if($errors->any())<p role="alert" class="alert-error">{{ $errors->first() }}</p>@endif
        <form method="post" action="{{ route('password.email') }}" class="space-y-4">@csrf
            <div><label for="reset-email" class="fs-label">Email address</label><input id="reset-email" type="email" name="email" value="{{ old('email') }}" maxlength="180" autocomplete="email" required class="fs-input"></div>
            <x-ui.button variant="primary" size="lg" type="submit" class="min-h-11 w-full">Send reset link</x-ui.button>
        </form>
        <p class="text-xs text-text-muted">Still cannot access your email? Contact support. Password recovery does not remove authenticator protection.</p>
    </section>
</div>
@endsection
