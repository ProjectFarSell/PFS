@if (config('services.google.client_id') && config('services.google.client_secret'))
    <div class="my-5 flex items-center gap-3 text-xs text-text-muted"><span class="h-px flex-1 bg-surface-border/50"></span>or continue with<span class="h-px flex-1 bg-surface-border/50"></span></div>
    <a href="{{ route('auth.google.redirect') }}" class="btn-outline w-full"><span aria-hidden="true" class="font-bold">G</span> Continue with Google</a>
@endif
@if ($errors->has('google'))<p role="alert" class="mt-3 text-sm text-error">{{ $errors->first('google') }}</p>@endif
