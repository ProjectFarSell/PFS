@extends('layouts.auth')
@section('title', 'Continue with Google · FarSell')
@section('content')
<div class="mx-auto max-w-lg px-4 py-16">
    <x-ui.button variant="link" :href="route('login')" class="text-accent underline">Back to sign in</x-ui.button>
    <section class="fs-card mt-6 p-6">
        <h1 class="text-2xl font-bold">Continue with Google</h1>
        <p class="mt-3 text-sm text-text-muted">Google will share your name, email, and account identifier. FarSell uses these to sign you in or create your buyer account.</p>
        @if($errors->any())<p role="alert" class="mt-3 text-sm text-error">{{ $errors->first() }}</p>@endif
        <form method="post" action="{{ route('auth.google.start') }}" class="mt-5 space-y-4">@csrf
            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="privacy_consent" value="1" required class="mt-1"><span>I have read the <a href="{{ route('privacy') }}" target="_blank" rel="noopener" class="text-accent underline">privacy notice</a> and agree to the collection and use of my data for my FarSell account and requested services.</span></label>
            <x-ui.button variant="primary" size="lg" type="submit" class="w-full">Continue to Google</x-ui.button>
        </form>
    </section>
</div>
@endsection
