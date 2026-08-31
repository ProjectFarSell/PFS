@extends('layouts.portal')

@section('title', 'FarSell — Auction surplus. Everyday prices.')

@section('content')
{{--
    Portal entry page — split layout
    Left  : hero / brand showcase (hidden on mobile, shows above the card on sm)
    Right : auth card with Login / Register tab switcher (Alpine.js)
--}}
@php
    // Open the register tab automatically when register errors are present,
    // or when the form was explicitly the register form.
    $defaultTab = ($errors->register->isNotEmpty() || old('_form') === 'register')
        ? 'register'
        : 'login';
@endphp
<div
    class="min-h-screen flex flex-col lg:flex-row"
    x-data="{
        tab: '{{ $defaultTab }}',
        loginLoading: false,
        registerLoading: false
    }"
>

    {{-- ── LEFT · Hero panel ──────────────────────────────────────────────── --}}
    <div class="relative hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col justify-between
                bg-gradient-to-br from-orange-500 via-amber-500 to-orange-600
                p-10 overflow-hidden text-white">

        {{-- Decorative blobs --}}
        <div class="pointer-events-none absolute -top-24 -left-24 h-96 w-96 rounded-full
                    bg-white/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -right-16 h-80 w-80 rounded-full
                    bg-amber-300/20 blur-3xl"></div>
        <div class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                    h-64 w-64 rounded-full bg-white/5 blur-2xl"></div>

        {{-- Logo --}}
        <div class="relative z-10">
            <a href="{{ route('welcome') }}"
               class="inline-flex items-center gap-2 text-2xl font-bold tracking-tight">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20
                             backdrop-blur-sm text-white font-black text-lg shadow-inner">F</span>
                FarSell
            </a>
        </div>

        {{-- Hero copy --}}
        <div class="relative z-10 space-y-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-orange-100 mb-2">
                    Doorzo-style lots · Shopee-fast checkout
                </p>
                <h1 class="text-4xl xl:text-5xl font-bold leading-tight">
                    Auction surplus.<br>Everyday prices.
                </h1>
            </div>
            <p class="text-base text-orange-50 max-w-sm leading-relaxed">
                Browse Japan auction lots, buy in minutes, or grow your business by selling
                on FarSell. Riders earn on every delivery in your city.
            </p>

            {{-- Feature pills --}}
            <div class="flex flex-wrap gap-2">
                @foreach (['🛍 Shop Japan surplus', '🏪 Open your store', '🛵 Deliver & earn', '👤 Guest checkout'] as $pill)
                    <span class="rounded-full border border-white/30 bg-white/10 backdrop-blur-sm
                                 px-3 py-1.5 text-xs font-medium text-white">
                        {{ $pill }}
                    </span>
                @endforeach
            </div>
        </div>

        {{-- Bottom quote --}}
        <div class="relative z-10">
            <p class="text-xs text-orange-200">
                Trusted by buyers, sellers, and riders across the Philippines.
            </p>
        </div>
    </div>

    {{-- ── RIGHT · Auth card ──────────────────────────────────────────────── --}}
    <div class="flex flex-1 flex-col items-center justify-center
                bg-stone-50 px-5 py-10 sm:px-10 lg:px-16 xl:px-24">

        {{-- Mobile logo --}}
        <div class="mb-8 flex flex-col items-center lg:hidden">
            <a href="{{ route('welcome') }}"
               class="flex h-12 w-12 items-center justify-center rounded-2xl
                      bg-gradient-to-br from-orange-500 to-amber-500 shadow-lg
                      text-white font-black text-xl mb-3">
                F
            </a>
            <p class="text-xl font-bold text-stone-900">FarSell</p>
            <p class="text-sm text-stone-500 mt-1">Auction surplus. Everyday prices.</p>
        </div>

        {{-- Card --}}
        <div class="w-full max-w-md">

            {{-- Glassmorphism card shell --}}
            <div class="rounded-3xl border border-stone-200/80 bg-white/80
                        backdrop-blur-xl shadow-xl shadow-stone-900/5 overflow-hidden">

                {{-- Tab switcher --}}
                <div class="flex border-b border-stone-100">
                    <button
                        type="button"
                        @click="tab = 'login'"
                        :class="tab === 'login'
                            ? 'border-b-2 border-orange-500 text-orange-600 font-semibold'
                            : 'text-stone-500 hover:text-stone-700'"
                        class="flex-1 py-4 text-sm transition-colors duration-150 focus:outline-none"
                        aria-label="Switch to login tab"
                    >
                        Log in
                    </button>
                    <button
                        type="button"
                        @click="tab = 'register'"
                        :class="tab === 'register'
                            ? 'border-b-2 border-orange-500 text-orange-600 font-semibold'
                            : 'text-stone-500 hover:text-stone-700'"
                        class="flex-1 py-4 text-sm transition-colors duration-150 focus:outline-none"
                        aria-label="Switch to register tab"
                    >
                        Create account
                    </button>
                </div>

                <div class="p-7 sm:p-8">

                    {{-- ── LOGIN TAB ──────────────────────────────────── --}}
                    <div x-show="tab === 'login'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1">

                        <div class="mb-6">
                            <h2 class="text-xl font-bold text-stone-900">Welcome back</h2>
                            <p class="text-sm text-stone-500 mt-1">Sign in to your FarSell account.</p>
                        </div>

                        {{-- Server-side login errors --}}
                        @if ($errors->login->isNotEmpty())
                            <div class="mb-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3"
                                 role="alert">
                                <p class="text-sm font-medium text-red-700">
                                    {{ $errors->login->first() }}
                                </p>
                            </div>
                        @elseif ($errors->any() && old('_form') !== 'register')
                            <div class="mb-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3"
                                 role="alert">
                                <p class="text-sm font-medium text-red-700">{{ $errors->first() }}</p>
                            </div>
                        @endif

                        <form
                            method="POST"
                            action="{{ route('login') }}"
                            @submit="loginLoading = true"
                            class="space-y-4"
                            novalidate
                        >
                            @csrf
                            {{-- Hidden sentinel so the controller can tell which form failed --}}
                            <input type="hidden" name="_form" value="login">

                            <div>
                                <label for="login_email"
                                       class="block text-xs font-semibold text-stone-600 mb-1.5">
                                    Email address
                                </label>
                                <input
                                    id="login_email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    autocomplete="email"
                                    placeholder="you@example.com"
                                    class="w-full rounded-xl border-stone-200 bg-stone-50 px-4 py-3
                                           text-sm text-stone-900 placeholder:text-stone-400
                                           transition focus:border-orange-400 focus:ring-orange-300
                                           @error('email') border-red-400 bg-red-50 @enderror"
                                >
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label for="login_password"
                                           class="block text-xs font-semibold text-stone-600">
                                        Password
                                    </label>
                                    {{-- Placeholder for future forgot-password flow --}}
                                    <span class="text-xs text-stone-400 cursor-not-allowed"
                                          title="Password reset coming soon">
                                        Forgot password?
                                    </span>
                                </div>
                                <input
                                    id="login_password"
                                    type="password"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="••••••••"
                                    class="w-full rounded-xl border-stone-200 bg-stone-50 px-4 py-3
                                           text-sm text-stone-900 placeholder:text-stone-400
                                           transition focus:border-orange-400 focus:ring-orange-300"
                                >
                            </div>

                            <div class="flex items-center justify-between">
                                <label class="flex items-center gap-2 text-sm text-stone-600 cursor-pointer select-none">
                                    <input type="checkbox" name="remember"
                                           class="rounded border-stone-300 text-orange-500
                                                  focus:ring-orange-300">
                                    Remember me
                                </label>
                            </div>

                            <button
                                type="submit"
                                class="relative w-full rounded-xl bg-gradient-to-r from-orange-500 to-amber-500
                                       px-4 py-3 text-sm font-semibold text-white shadow-md
                                       shadow-orange-500/30 transition
                                       hover:from-orange-600 hover:to-amber-600 hover:shadow-orange-500/40
                                       focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2
                                       active:scale-[0.98] disabled:opacity-60"
                                :disabled="loginLoading"
                            >
                                <span x-show="!loginLoading">Log in</span>
                                <span x-show="loginLoading" class="flex items-center justify-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"
                                         aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                                stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor"
                                              d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                    </svg>
                                    Signing in…
                                </span>
                            </button>
                        </form>

                        {{-- Divider --}}
                        <div class="my-5 flex items-center gap-3">
                            <div class="h-px flex-1 bg-stone-200"></div>
                            <span class="text-xs text-stone-400">or</span>
                            <div class="h-px flex-1 bg-stone-200"></div>
                        </div>

                        {{-- Guest CTA --}}
                        <form method="POST" action="{{ route('guest.start') }}">
                            @csrf
                            <button
                                type="submit"
                                class="w-full rounded-xl border border-stone-200 bg-white px-4 py-3
                                       text-sm font-medium text-stone-700 shadow-sm transition
                                       hover:border-orange-300 hover:bg-orange-50 hover:text-orange-700
                                       focus:outline-none focus:ring-2 focus:ring-orange-300
                                       active:scale-[0.98]"
                            >
                                Continue as guest
                            </button>
                        </form>

                        <p class="mt-5 text-center text-xs text-stone-400">
                            Demo: <span class="font-mono">buyer@farsell.test</span> / password
                        </p>
                    </div>

                    {{-- ── REGISTER TAB ────────────────────────────────── --}}
                    <div x-show="tab === 'register'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         style="display:none;">

                        <div class="mb-6">
                            <h2 class="text-xl font-bold text-stone-900">Join FarSell</h2>
                            <p class="text-sm text-stone-500 mt-1">Create your free account in seconds.</p>
                        </div>

                        {{-- Server-side register errors --}}
                        @if ($errors->register->isNotEmpty())
                            <div class="mb-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3"
                                 role="alert">
                                <ul class="list-disc pl-4 space-y-1">
                                    @foreach ($errors->register->all() as $error)
                                        <li class="text-sm text-red-700">{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @elseif ($errors->any() && old('_form') === 'register')
                            <div class="mb-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3"
                                 role="alert">
                                <ul class="list-disc pl-4 space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li class="text-sm text-red-700">{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form
                            method="POST"
                            action="{{ route('register') }}"
                            @submit="registerLoading = true"
                            class="space-y-4"
                            novalidate
                        >
                            @csrf
                            <input type="hidden" name="_form" value="register">

                            <div>
                                <label for="reg_name"
                                       class="block text-xs font-semibold text-stone-600 mb-1.5">
                                    Full name
                                </label>
                                <input
                                    id="reg_name"
                                    type="text"
                                    name="name"
                                    value="{{ old('name') }}"
                                    required
                                    autocomplete="name"
                                    placeholder="Your name"
                                    class="w-full rounded-xl border-stone-200 bg-stone-50 px-4 py-3
                                           text-sm text-stone-900 placeholder:text-stone-400
                                           transition focus:border-orange-400 focus:ring-orange-300
                                           @error('name') border-red-400 bg-red-50 @enderror"
                                >
                            </div>

                            <div>
                                <label for="reg_email"
                                       class="block text-xs font-semibold text-stone-600 mb-1.5">
                                    Email address
                                </label>
                                <input
                                    id="reg_email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    autocomplete="email"
                                    placeholder="you@example.com"
                                    class="w-full rounded-xl border-stone-200 bg-stone-50 px-4 py-3
                                           text-sm text-stone-900 placeholder:text-stone-400
                                           transition focus:border-orange-400 focus:ring-orange-300
                                           @error('email') border-red-400 bg-red-50 @enderror"
                                >
                            </div>

                            <div>
                                <label for="reg_password"
                                       class="block text-xs font-semibold text-stone-600 mb-1.5">
                                    Password
                                </label>
                                <input
                                    id="reg_password"
                                    type="password"
                                    name="password"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Min. 8 characters"
                                    class="w-full rounded-xl border-stone-200 bg-stone-50 px-4 py-3
                                           text-sm text-stone-900 placeholder:text-stone-400
                                           transition focus:border-orange-400 focus:ring-orange-300
                                           @error('password') border-red-400 bg-red-50 @enderror"
                                >
                            </div>

                            <div>
                                <label for="reg_password_confirmation"
                                       class="block text-xs font-semibold text-stone-600 mb-1.5">
                                    Confirm password
                                </label>
                                <input
                                    id="reg_password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Repeat password"
                                    class="w-full rounded-xl border-stone-200 bg-stone-50 px-4 py-3
                                           text-sm text-stone-900 placeholder:text-stone-400
                                           transition focus:border-orange-400 focus:ring-orange-300"
                                >
                            </div>

                            <div>
                                <label for="reg_intent"
                                       class="block text-xs font-semibold text-stone-600 mb-1.5">
                                    I want to…
                                </label>
                                <select
                                    id="reg_intent"
                                    name="intent"
                                    class="w-full rounded-xl border-stone-200 bg-stone-50 px-4 py-3
                                           text-sm text-stone-900
                                           transition focus:border-orange-400 focus:ring-orange-300"
                                >
                                    <option value="buyer">Shop (buyer)</option>
                                    <option value="seller">Sell products (seller)</option>
                                    <option value="rider" @selected(request('intent') === 'rider')>
                                        Deliver orders (rider)
                                    </option>
                                </select>
                                <p class="mt-1.5 text-[11px] text-stone-400">
                                    All accounts start as Buyer. Seller/Rider access is granted after review.
                                </p>
                            </div>

                            <button
                                type="submit"
                                class="relative w-full rounded-xl bg-gradient-to-r from-orange-500 to-amber-500
                                       px-4 py-3 text-sm font-semibold text-white shadow-md
                                       shadow-orange-500/30 transition
                                       hover:from-orange-600 hover:to-amber-600 hover:shadow-orange-500/40
                                       focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2
                                       active:scale-[0.98] disabled:opacity-60"
                                :disabled="registerLoading"
                            >
                                <span x-show="!registerLoading">Create account</span>
                                <span x-show="registerLoading"
                                      class="flex items-center justify-center gap-2"
                                      style="display:none;">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"
                                         aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                                stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor"
                                              d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                    </svg>
                                    Creating account…
                                </span>
                            </button>
                        </form>
                    </div>

                </div>{{-- /card body --}}
            </div>{{-- /card --}}

            <p class="mt-6 text-center text-xs text-stone-400 leading-relaxed">
                By continuing you agree to FarSell's
                <a href="#" class="underline underline-offset-2 hover:text-stone-600">Terms of Service</a>
                and
                <a href="#" class="underline underline-offset-2 hover:text-stone-600">Privacy Policy</a>.
            </p>
        </div>{{-- /max-w-md --}}
    </div>{{-- /right panel --}}

</div>{{-- /outer flex --}}
@endsection
