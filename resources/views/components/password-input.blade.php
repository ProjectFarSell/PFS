@props(['id', 'name' => 'password', 'autocomplete' => 'current-password', 'placeholder' => 'Enter your password'])
<div class="relative" x-data="{ visible: false }">
    <input id="{{ $id }}" name="{{ $name }}" type="password" :type="visible ? 'text' : 'password'" autocomplete="{{ $autocomplete }}" required placeholder="{{ $placeholder }}" {{ $attributes->merge(['class' => 'fs-input pr-12']) }}>
    <button type="button" @click="visible = !visible" :aria-label="visible ? 'Hide password' : 'Show password'" :aria-pressed="visible" aria-controls="{{ $id }}" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-xl text-text-muted hover:text-accent">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path x-show="visible" style="display:none;" d="m3 3 18 18"/></svg>
    </button>
</div>
