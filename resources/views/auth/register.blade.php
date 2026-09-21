@extends('layouts.app')

@section('title', 'Create account · FarSell')

@section('content')
    <div class="max-w-sm mx-auto fs-card p-5">
        <h1 class="text-lg font-semibold text-text-base">{{ $checkoutIntent ? 'Create an account to checkout' : 'Join FarSell' }}</h1>
        @if($checkoutIntent)
            <p class="mt-1 text-sm text-text-muted">Your cart is saved. After signing up, add a delivery address to continue checkout.</p>
        @endif

        <form method="post" action="{{ route('register') }}" class="mt-4 space-y-3">
            @csrf
            <input name="name" value="{{ old('name') }}" required placeholder="Name" class="fs-input">
            <input type="email" name="email" value="{{ old('email') }}" required placeholder="Email" class="fs-input">
            <input type="password" name="password" required placeholder="Password" class="fs-input">
            <input type="password" name="password_confirmation" required placeholder="Confirm password" class="fs-input">

            @if($checkoutIntent)
                <input type="hidden" name="intent" value="buyer">
            @else
            <select name="intent" class="fs-input">
                <option value="buyer" class="bg-surface text-text-base">I want to shop</option>
                <option value="seller" @selected(old('intent', request('intent')) === 'seller') class="bg-surface text-text-base">I want to sell</option>
                <option value="rider" @selected(request('intent') === 'rider') class="bg-surface text-text-base">I want to deliver</option>
            </select>
            @endif

            @if ($errors->register->isNotEmpty())
                <ul role="alert" class="text-sm text-error list-disc pl-4">
                    @foreach ($errors->register->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($errors->any())
                <ul class="text-sm text-error list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <button class="w-full btn-accent rounded-full py-2.5">{{ $checkoutIntent ? 'Create account and continue' : 'Create account' }}</button>
        </form>

        <p class="text-sm text-center text-text-muted mt-4">
            Already have an account?
            <a class="text-accent hover:text-accent-hover font-medium" href="{{ route('login') }}">Log in</a>
        </p>
    </div>
@endsection
