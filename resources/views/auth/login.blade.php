@extends('layouts.app')

@section('title', 'Log in · FarSell')

@section('content')
    <div class="max-w-sm mx-auto fs-card p-5">
        <h1 class="text-lg font-semibold text-text-base">{{ $checkoutIntent ? 'Log in to checkout' : 'Welcome back' }}</h1>
        @if($checkoutIntent)
            <p class="text-sm text-text-muted mt-1">Sign in or create an account to place your order. Your cart will be kept.</p>
        @else
            <p class="text-sm text-text-muted mt-1">Sign in to manage your account and orders.</p>
            <a href="{{ route('welcome') }}" class="mt-3 inline-block text-sm text-accent underline">Back to browsing</a>
        @endif

        <form method="post" action="{{ route('login') }}" class="mt-4 space-y-3">
            @csrf
            <label for="login-identifier" class="fs-label">Email or admin username</label>
            <input id="login-identifier" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" name="email" value="{{ old('email') }}" required placeholder="Email or admin" class="fs-input">
            <input type="password" name="password" required placeholder="Password" class="fs-input">

            <label class="flex items-center gap-2 text-sm text-text-muted cursor-pointer">
                <input type="checkbox" name="remember" class="rounded border-surface-border bg-surface text-accent focus:ring-accent/40">
                Remember me
            </label>

            @error('email')
                <p class="text-sm text-error">{{ $message }}</p>
            @enderror

            @if ($errors->login->isNotEmpty())
                <p role="alert" class="text-sm text-error">{{ $errors->login->first() }}</p>
            @endif

            <button class="w-full btn-accent rounded-full py-2.5">{{ $checkoutIntent ? 'Log in to checkout' : 'Log in' }}</button>
        </form>

        @if($checkoutIntent)
            <a href="{{ route('register') }}" class="mt-3 w-full btn-outline rounded-full">Create an account</a>
            <a href="{{ route('cart.index') }}" class="block mt-4 text-center text-sm text-text-muted underline">Back to cart</a>
        @else
        <p class="text-sm text-center text-text-muted mt-4">
            New here?
            <a class="text-accent hover:text-accent-hover font-medium underline-offset-2 hover:underline" href="{{ route('register') }}">Create account</a>
        </p>
        @endif
        <p class="text-xs text-text-muted/70 text-center mt-2">Demo: buyer@farsell.test / password</p>
    </div>
@endsection
