@extends('layouts.app')

@section('title', 'Log in · FarSell')

@section('content')
    <div class="max-w-sm mx-auto fs-card p-5">
        <h1 class="text-lg font-semibold text-text-base">Welcome back</h1>
        <p class="text-sm text-text-muted mt-1">Or skip an account and keep shopping.</p>

        <form method="post" action="{{ route('guest.start') }}" class="mt-3">
            @csrf
            <button class="w-full btn-outline rounded-full py-2">Continue as guest</button>
        </form>

        <form method="post" action="{{ route('login') }}" class="mt-4 space-y-3">
            @csrf
            <input type="email" name="email" value="{{ old('email') }}" required placeholder="Email" class="fs-input">
            <input type="password" name="password" required placeholder="Password" class="fs-input">

            <label class="flex items-center gap-2 text-sm text-text-muted cursor-pointer">
                <input type="checkbox" name="remember" class="rounded border-surface-border bg-surface text-accent focus:ring-accent/40">
                Remember me
            </label>

            @error('email') 
                <p class="text-sm text-error">{{ $message }}</p> 
            @enderror

            <button class="w-full btn-accent rounded-full py-2.5">Log in</button>
        </form>

        <p class="text-sm text-center text-text-muted mt-4">
            New here? 
            <a class="text-accent hover:text-accent-hover font-medium underline-offset-2 hover:underline" href="{{ route('register') }}">Create account</a>
        </p>
        <p class="text-xs text-text-muted/70 text-center mt-2">Demo: buyer@farsell.test / password</p>
    </div>
@endsection
