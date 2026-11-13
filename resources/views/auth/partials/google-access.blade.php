@if (config('services.google.client_id') && config('services.google.client_secret'))
    <div class="my-5 flex items-center gap-3 text-xs text-text-muted"><span class="h-px flex-1 bg-surface-border/50"></span>or continue with<span class="h-px flex-1 bg-surface-border/50"></span></div>
    <x-ui.button variant="secondary" :href="route('auth.google.redirect')" class="w-full"><span aria-hidden="true" class="font-bold">G</span> Continue with Google</x-ui.button>
    <p class="mt-2 text-xs text-text-muted">By continuing with Google, you agree to FarSell’s <a href="{{ route('privacy') }}" class="text-accent underline">privacy notice</a> and the use of your Google name, email and account identifier to sign in or create your buyer account.</p>
@endif
@if ($errors->has('google'))<p role="alert" class="mt-3 text-sm text-error">{{ $errors->first('google') }}</p>@endif
