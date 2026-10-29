@extends('layouts.auth')
@section('title', 'Your next great find starts here · FarSell')

@section('content')
@php
    $checkoutIntent = $checkoutIntent ?? false;
    $defaultTab = $errors->register->isNotEmpty() ? 'register'
        : ($errors->login->isNotEmpty() ? 'login'
            : ($errors->any() && in_array(old('_form'), ['login', 'register'], true) ? old('_form') : ($initialTab ?? 'login')));
@endphp
<div class="auth-page" x-data="{ tab: '{{ $defaultTab }}', loginLoading: false, registerLoading: false }" @pageshow.window="loginLoading = false; registerLoading = false">
    <div class="auth-shell">
        <aside class="auth-story" aria-labelledby="auth-story-heading">
            <a href="{{ route('welcome') }}" class="inline-flex w-fit items-center gap-3 text-xl font-bold"><span class="flex h-10 w-10 items-center justify-center rounded-xl border border-white/30 bg-white/10">F</span> FarSell</a>
            <div class="relative z-10 py-10">
                <p class="text-xs font-semibold uppercase tracking-[.2em] text-white/70">Good finds. Great possibilities.</p>
                <h1 id="auth-story-heading" class="mt-5 text-4xl font-bold leading-[1.08] tracking-tight xl:text-5xl">A little more<br>you. A lot more<br><span class="auth-outline-text">to discover.</span></h1>
                <p class="mt-5 max-w-xs text-sm leading-relaxed text-white/80">Collect something special. Upgrade your everyday. Find your next favorite, closer to home.</p>
                <div class="auth-art" aria-hidden="true">
                    <div class="auth-art-card auth-art-card-back"><x-discovery-art kind="cards" /></div>
                    <div class="auth-art-card auth-art-card-front"><x-discovery-art kind="keyboard" /></div>
                    <span class="auth-art-label">Curated finds. Everyday prices.</span>
                </div>
            </div>
            <div class="relative z-10 flex flex-wrap gap-2 text-xs text-white/90"><span class="rounded-full border border-white/25 px-3 py-2">Local shops</span><span class="rounded-full border border-white/25 px-3 py-2">Unique finds</span><span class="rounded-full border border-white/25 px-3 py-2">One FarSell account</span></div>
        </aside>

        <section class="auth-form-side" aria-label="Your FarSell account">
            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('welcome') }}" class="inline-flex min-h-11 items-center gap-2 text-sm text-text-muted hover:text-accent"><span aria-hidden="true">←</span> Back to browsing</a>
                <x-theme-toggle />
            </div>
            <div class="auth-form-content">
                <a href="{{ route('welcome') }}" class="mb-6 inline-flex items-center gap-2 font-bold text-accent lg:hidden"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-accent text-accent-text">F</span> FarSell</a>
                <div class="auth-tabs" role="tablist" aria-label="Account access">
                    <a id="login-tab" x-ref="loginTab" href="{{ route('login') }}" role="tab" aria-controls="login-panel" :aria-selected="tab === 'login'" :tabindex="tab === 'login' ? 0 : -1" @click.prevent="tab = 'login'" @keydown.right.prevent="tab = 'register'; $refs.registerTab.focus()" :data-active="tab === 'login' ? 'true' : 'false'" data-active="{{ $defaultTab === 'login' ? 'true' : 'false' }}">Log in</a>
                    <a id="register-tab" x-ref="registerTab" href="{{ route('register') }}" role="tab" aria-controls="register-panel" :aria-selected="tab === 'register'" :tabindex="tab === 'register' ? 0 : -1" @click.prevent="tab = 'register'" @keydown.left.prevent="tab = 'login'; $refs.loginTab.focus()" :data-active="tab === 'register' ? 'true' : 'false'" data-active="{{ $defaultTab === 'register' ? 'true' : 'false' }}">Create account</a>
                </div>

                <div class="auth-forms">
                    <div id="login-panel" role="tabpanel" aria-labelledby="login-tab" class="auth-form-panel" :data-active="tab === 'login' ? 'true' : 'false'" data-active="{{ $defaultTab === 'login' ? 'true' : 'false' }}" :inert="tab !== 'login'" @if ($defaultTab !== 'login') inert @endif>
                        <p class="section-eyebrow">Your next find is waiting</p>
                        <h2 class="mt-2 text-3xl font-bold tracking-tight">{{ $checkoutIntent ? 'Log in to checkout' : 'Welcome back.' }}</h2>
                        <p class="mt-2 mb-6 text-sm text-text-muted leading-relaxed">{{ $checkoutIntent ? 'Sign in or create an account to place your order. Your cart will be kept.' : 'Good to see you again. Pick up where you left off.' }}</p>
                        @if ($errors->login->isNotEmpty())
                            <div class="alert-error mb-4" role="alert">{{ $errors->login->first() }}</div>
                        @elseif ($errors->any() && old('_form') !== 'register')
                            <div class="alert-error mb-4" role="alert">{{ $errors->first() }}</div>
                        @endif
                        <form method="post" action="{{ route('login') }}" class="space-y-4" @submit="loginLoading = true">
                            @csrf
                            <input type="hidden" name="_form" value="login">
                            <div><label for="login-identifier" class="fs-label">Email or admin username</label><input id="login-identifier" type="text" name="email" value="{{ old('email') }}" autocomplete="username" autocapitalize="none" spellcheck="false" required placeholder="you@example.com or admin" class="fs-input"></div>
                            <div><label for="login_password" class="fs-label">Password</label><x-password-input id="login_password" /></div>
                            <label class="inline-flex min-h-8 cursor-pointer items-center gap-2 text-sm text-text-muted"><input name="remember" type="checkbox" class="rounded border-surface-border text-accent focus:ring-accent"> Remember me</label>
                            <button type="submit" :disabled="loginLoading" class="btn-accent w-full min-h-12"><span x-text="loginLoading ? 'Signing in…' : 'Log in'">Log in</span><span x-show="!loginLoading" aria-hidden="true">→</span></button>
                        </form>
                        @include('auth.partials.google-access')
                        <p class="mt-5 text-center text-sm text-text-muted">New to FarSell? <a href="{{ route('register') }}" @click.prevent="tab = 'register'; $nextTick(() => $refs.registerTab.focus())" class="inline-flex min-h-11 items-center font-semibold text-accent hover:underline">Create an account</a></p>
                        @if ($checkoutIntent)<a href="{{ route('cart.index') }}" class="mt-2 block text-center text-sm text-accent hover:underline">Back to cart</a>@endif
                    </div>

                    <div id="register-panel" role="tabpanel" aria-labelledby="register-tab" class="auth-form-panel" :data-active="tab === 'register' ? 'true' : 'false'" data-active="{{ $defaultTab === 'register' ? 'true' : 'false' }}" :inert="tab !== 'register'" @if ($defaultTab !== 'register') inert @endif>
                        <p class="section-eyebrow">Make yourself at home</p>
                        <h2 class="mt-2 text-3xl font-bold tracking-tight">Your next chapter<br>starts with a find.</h2>
                        <p class="mt-2 mb-5 text-sm text-text-muted">Join FarSell. Discover, collect, and shop your way.</p>
                        @if ($errors->register->isNotEmpty() || ($errors->any() && old('_form') === 'register'))
                            <div class="alert-error mb-4" role="alert"><ul class="list-disc pl-4 space-y-1">@foreach (($errors->register->isNotEmpty() ? $errors->register : $errors)->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                        @endif
                        <form method="post" action="{{ route('register') }}" class="space-y-3.5" @submit="registerLoading = true">
                            @csrf
                            <input type="hidden" name="_form" value="register">
                            <div><label for="reg_name" class="fs-label">Full name</label><input id="reg_name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="120" required placeholder="Your full name" class="fs-input"></div>
                            <div><label for="reg_email" class="fs-label">Email address</label><input id="reg_email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" maxlength="180" required placeholder="you@example.com" class="fs-input"></div>
                            <div class="grid gap-3.5 sm:grid-cols-2">
                                <div><label for="reg_password" class="fs-label">Password</label><x-password-input id="reg_password" autocomplete="new-password" placeholder="Min. 8 characters" /></div>
                                <div><label for="reg_password_confirmation" class="fs-label">Confirm password</label><x-password-input id="reg_password_confirmation" name="password_confirmation" autocomplete="new-password" placeholder="Repeat password" /></div>
                            </div>
                            @if ($checkoutIntent)
                                <input type="hidden" name="intent" value="buyer">
                            @else
                                <div><label for="reg_intent" class="fs-label">I'm here to</label><select id="reg_intent" name="intent" class="fs-input"><option value="buyer">Discover &amp; shop</option><option value="seller" @selected(old('intent', request('intent')) === 'seller')>Sell on FarSell</option><option value="rider" @selected(old('intent', request('intent')) === 'rider')>Deliver &amp; earn</option></select><p class="mt-1.5 text-xs text-text-muted">Start as a buyer. Seller and rider access follow application review.</p></div>
                            @endif
                            <button type="submit" :disabled="registerLoading" class="btn-accent w-full min-h-12"><span x-text="registerLoading ? 'Creating Account…' : @js($checkoutIntent ? 'Create Account and continue' : 'Create Account')">{{ $checkoutIntent ? 'Create Account and continue' : 'Create Account' }}</span><span x-show="!registerLoading" aria-hidden="true">→</span></button>
                        </form>
                        @include('auth.partials.google-access')
                        <p class="mt-4 text-center text-sm text-text-muted">Already part of FarSell? <a href="{{ route('login') }}" @click.prevent="tab = 'login'; $nextTick(() => $refs.loginTab.focus())" class="inline-flex min-h-11 items-center font-semibold text-accent hover:underline">Log in</a></p>
                    </div>
                </div>
                <p class="mt-5 border-t border-surface-border/40 pt-4 text-center text-xs text-text-muted">FarSell Marketplace — Quality Deals &amp; Local Surplus</p>
            </div>
        </section>
    </div>
</div>
@endsection
