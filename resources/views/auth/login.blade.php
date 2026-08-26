@extends('layouts.app')

@section('title', 'Log in · FarSell')

@section('content')
    <div class="max-w-sm mx-auto rounded-2xl bg-white border border-stone-200 p-5">
        <h1 class="text-lg font-semibold">Welcome back</h1>
        <p class="text-sm text-stone-500 mt-1">Or skip an account and keep shopping.</p>
        <form method="post" action="{{ route('guest.start') }}" class="mt-3">
            @csrf
            <button class="w-full rounded-full border border-orange-500 text-orange-600 text-sm py-2">Continue as guest</button>
        </form>
        <form method="post" action="{{ route('login') }}" class="mt-4 space-y-3">
            @csrf
            <input type="email" name="email" value="{{ old('email') }}" required placeholder="Email" class="w-full rounded-lg border-stone-200 text-sm">
            <input type="password" name="password" required placeholder="Password" class="w-full rounded-lg border-stone-200 text-sm">
            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" name="remember" class="rounded border-stone-300"> Remember me
            </label>
            @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <button class="w-full rounded-full bg-orange-500 text-white text-sm py-2.5">Log in</button>
        </form>
        <p class="text-sm text-center mt-4">New here? <a class="text-orange-600" href="{{ route('register') }}">Create account</a></p>
        <p class="text-xs text-stone-400 text-center mt-2">Demo: buyer@farsell.test / password</p>
    </div>
@endsection
