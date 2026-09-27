@extends('layouts.auth')

@section('title', 'FarSell — Auction surplus. Everyday prices.')

@section('content')
@php
    $checkoutIntent = $checkoutIntent ?? false;
    $defaultTab = $errors->register->isNotEmpty() ? 'register'
        : ($errors->login->isNotEmpty() ? 'login'
            : ($errors->any() && in_array(old('_form'), ['login', 'register'], true) ? old('_form') : ($initialTab ?? 'login')));
@endphp

<div class="min-h-screen flex flex-col lg:flex-row"
     x-data="{ tab: '{{ $defaultTab }}', loginLoading: false, registerLoading: false }">

    {{-- ═══════════════════════════════════════════════════════════
         LEFT  —  Brand illustration panel  (Doorzo-style)
         Hidden on mobile, full-height on desktop
         ═══════════════════════════════════════════════════════════ --}}
    <div class="hidden lg:flex lg:w-1/2 xl:w-[55%] relative flex-col justify-between
                overflow-hidden p-12 text-white"
         style="background: linear-gradient(135deg, rgb(var(--color-accent)) 0%, rgb(var(--color-accent-hover)) 60%, #1e1b4b 100%);">

        {{-- Decorative geometry --}}
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            {{-- Large soft circle top-left --}}
            <div class="absolute -top-32 -left-32 h-96 w-96 rounded-full opacity-20"
                 style="background: radial-gradient(circle, white, transparent);"></div>
            {{-- Medium circle bottom-right --}}
            <div class="absolute -bottom-24 -right-24 h-72 w-72 rounded-full opacity-15"
                 style="background: radial-gradient(circle, white, transparent);"></div>
            {{-- Grid pattern overlay --}}
            <svg class="absolute inset-0 h-full w-full opacity-[0.04]"
                 xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)"/>
            </svg>
            {{-- Floating accent orb --}}
            <div class="absolute top-1/3 right-1/4 h-48 w-48 rounded-full blur-3xl opacity-30"
                 style="background-color: white;"></div>
        </div>

        {{-- Logo --}}
        <div class="relative z-10">
            <a href="{{ route('welcome') }}"
               class="inline-flex items-center gap-3 group">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl
                             bg-white/20 border border-white/30 font-black text-xl
                             group-hover:bg-white/30 transition-colors">
                    F
                </span>
                <span class="text-2xl font-bold tracking-tight">FarSell</span>
            </a>
        </div>

        {{-- ── Illustration area ──────────────────────────────────── --}}
        <div class="relative z-10 my-8">
            {{-- Abstract product showcase illustration --}}
            <div class="grid grid-cols-2 gap-3 max-w-xs mx-auto lg:mx-0">
                {{-- Mock product cards --}}
                @foreach ([
                    ['icon' => '', 'label' => "Women's Fashion", 'price' => '₱249'],
                    ['icon' => '', 'label' => 'Gadgets & Tech',  'price' => '₱899'],
                    ['icon' => '', 'label' => 'Home & Living',    'price' => '₱159'],
                    ['icon' => '', 'label' => 'Sports & Outdoor', 'price' => '₱349'],
                ] as $mock)
                    <div class="rounded-2xl bg-white/10 border border-white/20 p-3
                                backdrop-blur-sm hover:bg-white/15 transition-colors">
                        <div class="text-3xl mb-2">{{ $mock['icon'] }}</div>
                        <p class="text-xs font-medium text-white/80 leading-tight">{{ $mock['label'] }}</p>
                        <p class="text-sm font-bold mt-1">{{ $mock['price'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Floating badge --}}
            <div class="absolute -top-3 -right-3 lg:right-0 rounded-full px-3 py-1.5
                        bg-white text-xs font-bold shadow-lg"
                 style="color: rgb(var(--color-accent));">
                🔥 Flash deals live
            </div>
        </div>

        {{-- ── Hero copy ──────────────────────────────────────────── --}}
        <div class="relative z-10 space-y-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/60 mb-3">
                    Quality lots · Quick & fast checkout
                </p>
                <h1 class="text-4xl xl:text-5xl font-bold leading-tight">
                    Best surplus.<br>
                    <span class="text-white/80">Everyday prices.</span>
                </h1>
            </div>
            <p class="text-base text-white/70 max-w-sm leading-relaxed">
                Browse quality lots and good deals. Buy in minutes,
                sell your store, or ride with us and earn every delivery.
            </p>

            {{-- Feature pill row --}}
            <div class="flex flex-wrap gap-2">
                @foreach (['Shop lots', 'Sell online', 'Rider earnings', 'Browse freely'] as $pill)
                    <span class="rounded-full border border-white/25 bg-white/10
                                 px-3 py-1.5 text-xs font-medium text-white/90 backdrop-blur-sm">
                        {{ $pill }}
                    </span>
                @endforeach
            </div>

            {{-- Social proof --}}
            <p class="text-xs text-white/40 pt-2">
                Trusted by buyers, sellers &amp; riders across the Philippines.
            </p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         RIGHT  —  Auth card  (Login / Register tabs)
         ═══════════════════════════════════════════════════════════ --}}
    <div class="flex flex-1 flex-col items-center justify-center px-5 py-10
                sm:px-10 lg:px-14 xl:px-20 min-h-screen lg:min-h-0"
         style="background-color: rgb(var(--color-on-surface));">

        {{-- Mobile logo --}}
        <div class="mb-8 flex flex-col items-center lg:hidden">
            <a href="{{ route('welcome') }}"
               class="flex h-12 w-12 items-center justify-center rounded-2xl
                      font-black text-xl mb-3 text-white"
               style="background-color: rgb(var(--color-accent));">
                F
            </a>
            <p class="text-xl font-bold" style="color: rgb(var(--color-text-base));">FarSell</p>
            <p class="text-sm mt-1" style="color: rgb(var(--color-text-muted));">
                Best surplus. Everyday prices.
            </p>
        </div>

        {{-- Theme toggle (top-right of right panel, mobile only) --}}
        <div class="fixed top-4 right-4 lg:absolute lg:top-6 lg:right-6 z-10" x-data>
            <button class="theme-toggle"
                    @click="$theme.toggle()"
                    :aria-label="$theme.isDark ? 'Switch to light mode' : 'Switch to dark mode'">
                <svg x-show="$theme.isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/>
                </svg>
                <svg x-show="!$theme.isDark" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="1.8" style="display:none;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75 9.75 9.75 0 0 1 8.25 6c0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 12c0 5.385 4.365 9.75 9.75 9.75 4.797 0 8.818-3.462 9.75-8.002z"/>
                </svg>
            </button>
        </div>

        <div class="w-full max-w-md">

            {{-- ── Auth card shell ──────────────────────────────────── --}}
            <div class="fs-card overflow-hidden shadow-card-md">

                {{-- Tab switcher --}}
                <div class="flex border-b" style="border-color: rgb(var(--color-surface-border) / 0.5);">
                    <a href="{{ route('login') }}" @click.prevent="tab = 'login'"
                            class="flex flex-1 items-center justify-center px-3 py-4 text-center text-sm font-medium transition-colors duration-150
                                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-4px] relative"
                            :style="tab === 'login'
                                ? 'color:rgb(var(--color-accent)); border-bottom: 2px solid rgb(var(--color-accent));'
                                : 'color:rgb(var(--color-text-muted));'"
                            aria-label="Log in tab" :aria-current="tab === 'login' ? 'page' : null">
                        Log in
                    </a>
                    <a href="{{ route('register') }}" @click.prevent="tab = 'register'"
                            class="flex flex-1 items-center justify-center px-3 py-4 text-center text-sm font-medium transition-colors duration-150
                                   focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-4px]"
                            :style="tab === 'register'
                                ? 'color:rgb(var(--color-accent)); border-bottom: 2px solid rgb(var(--color-accent));'
                                : 'color:rgb(var(--color-text-muted));'"
                            aria-label="Create account tab" :aria-current="tab === 'register' ? 'page' : null">
                        Create account
                    </a>
                </div>

                <div class="p-7 sm:p-8">

                    {{-- ══ LOGIN ══════════════════════════════════════ --}}
                    <div x-show="tab === 'login'" style="{{ $defaultTab === 'login' ? '' : 'display:none;' }}"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0">

                        <h2 class="text-xl font-bold mb-1"
                            style="color: rgb(var(--color-text-base));">{{ $checkoutIntent ? 'Log in to checkout' : 'Welcome back' }}</h2>
                        <p class="text-sm mb-6" style="color: rgb(var(--color-text-muted));">
                            {{ $checkoutIntent ? 'Sign in or create an account to place your order. Your cart will be kept.' : 'Sign in to your FarSell account.' }}
                        </p>

                        @if ($errors->login->isNotEmpty())
                            <div class="alert-error mb-4" role="alert">
                                {{ $errors->login->first() }}
                            </div>
                        @elseif ($errors->any() && old('_form') !== 'register')
                            <div class="alert-error mb-4" role="alert">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}"
                              @submit="loginLoading = true" class="space-y-4" novalidate>
                            @csrf
                            <input type="hidden" name="_form" value="login">

                            <div>
                                <label for="login-identifier" class="fs-label">Email or admin username</label>
                                <input id="login-identifier" type="text" name="email"
                                       value="{{ old('email') }}" required
                                       autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="you@example.com or admin"
                                       class="fs-input @error('email') ring-1 ring-red-400 @enderror">
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label for="login_password" class="fs-label mb-0">Password</label>
                                    <span class="text-xs cursor-not-allowed"
                                          style="color: rgb(var(--color-text-muted));"
                                          title="Password reset coming soon">Forgot password?</span>
                                </div>
                                <input id="login_password" type="password" name="password"
                                       required autocomplete="current-password"
                                       placeholder="••••••••" class="fs-input">
                            </div>

                            <label class="flex items-center gap-2 text-sm cursor-pointer select-none"
                                   style="color: rgb(var(--color-text-muted));">
                                <input type="checkbox" name="remember"
                                       class="rounded"
                                       style="accent-color: rgb(var(--color-accent));">
                                Remember me
                            </label>

                            <button type="submit" :disabled="loginLoading"
                                    class="btn-accent w-full py-3">
                                <span x-show="!loginLoading">Log in</span>
                                <span x-show="loginLoading"
                                      class="flex items-center justify-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
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
                            <div class="h-px flex-1"
                                 style="background-color: rgb(var(--color-surface-border) / 0.5);"></div>
                            <span class="text-xs" style="color: rgb(var(--color-text-muted));">or</span>
                            <div class="h-px flex-1"
                                 style="background-color: rgb(var(--color-surface-border) / 0.5);"></div>
                        </div>

                        @if($checkoutIntent)
                            <a href="{{ route('register') }}" class="btn-outline w-full py-2.5">Create an account</a>
                            <a href="{{ route('cart.index') }}" class="block mt-3 text-center text-sm underline">Back to cart</a>
                        @else
                            <a href="{{ route('welcome') }}" class="btn-outline w-full py-2.5">Back to browsing</a>
                        @endif

                        <p class="mt-5 text-center text-xs"
                           style="color: rgb(var(--color-text-muted));">
                            Demo: <span class="font-mono">buyer@farsell.test</span> / password
                        </p>
                    </div>

                    {{-- ══ REGISTER ════════════════════════════════════ --}}
                    <div x-show="tab === 'register'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         style="{{ $defaultTab === 'register' ? '' : 'display:none;' }}">

                        <h2 class="text-xl font-bold mb-1"
                            style="color: rgb(var(--color-text-base));">Join FarSell</h2>
                        <p class="text-sm mb-6" style="color: rgb(var(--color-text-muted));">
                            Create your free account in seconds.
                        </p>

                        @if ($errors->register->isNotEmpty())
                            <div class="alert-error mb-4" role="alert">
                                <ul class="list-disc pl-4 space-y-0.5">
                                    @foreach ($errors->register->all() as $e)
                                        <li>{{ $e }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @elseif ($errors->any() && old('_form') === 'register')
                            <div class="alert-error mb-4" role="alert">
                                <ul class="list-disc pl-4 space-y-0.5">
                                    @foreach ($errors->all() as $e)
                                        <li>{{ $e }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('register') }}"
                              @submit="registerLoading = true" class="space-y-4" novalidate>
                            @csrf
                            <input type="hidden" name="_form" value="register">

                            <div>
                                <label for="reg_name" class="fs-label">Full name</label>
                                <input id="reg_name" type="text" name="name"
                                       value="{{ old('name') }}" required
                                       autocomplete="name" placeholder="Your name"
                                       class="fs-input @error('name') ring-1 ring-red-400 @enderror">
                            </div>
                            <div>
                                <label for="reg_email" class="fs-label">Email address</label>
                                <input id="reg_email" type="email" name="email"
                                       value="{{ old('email') }}" required
                                       autocomplete="email" placeholder="you@example.com"
                                       class="fs-input @error('email') ring-1 ring-red-400 @enderror">
                            </div>
                            <div>
                                <label for="reg_password" class="fs-label">Password</label>
                                <input id="reg_password" type="password" name="password"
                                       required autocomplete="new-password"
                                       placeholder="Min. 8 characters"
                                       class="fs-input @error('password') ring-1 ring-red-400 @enderror">
                            </div>
                            <div>
                                <label for="reg_password_confirmation" class="fs-label">
                                    Confirm password
                                </label>
                                <input id="reg_password_confirmation" type="password"
                                       name="password_confirmation" required
                                       autocomplete="new-password" placeholder="Repeat password"
                                       class="fs-input">
                            </div>
                            @if($checkoutIntent)
                                <input type="hidden" name="intent" value="buyer">
                            @else
                            <div>
                                <label for="reg_intent" class="fs-label">I want to…</label>
                                <select id="reg_intent" name="intent" class="fs-input">
                                    <option value="buyer">Shop (buyer)</option>
                                    <option value="seller" @selected(old('intent', request('intent')) === 'seller')>Sell products (seller)</option>
                                    <option value="rider" @selected(old('intent', request('intent')) === 'rider')>
                                        Deliver orders (rider)
                                    </option>
                                </select>
                                <p class="mt-1.5 text-[11px]"
                                   style="color: rgb(var(--color-text-muted));">
                                    All accounts start as Buyer. Seller/Rider access granted after review.
                                </p>
                            </div>
                            @endif

                            <button type="submit" :disabled="registerLoading"
                                    class="btn-accent w-full py-3">
                                <span x-show="!registerLoading">{{ $checkoutIntent ? 'Create account and continue' : 'Create account' }}</span>
                                <span x-show="registerLoading" style="display:none;"
                                      class="flex items-center justify-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
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
            </div>{{-- /fs-card --}}

            <p class="mt-5 text-center text-xs leading-relaxed"
               style="color: rgb(var(--color-text-muted));">
                By continuing you agree to FarSell's
                <a href="#" class="underline underline-offset-2 hover:opacity-100 opacity-70">Terms of Service</a>
                and
                <a href="#" class="underline underline-offset-2 hover:opacity-100 opacity-70">Privacy Policy</a>.
            </p>
        </div>
    </div>
</div>
@endsection
