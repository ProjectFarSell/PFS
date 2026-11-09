@props(['id', 'name' => 'password', 'autocomplete' => 'current-password', 'placeholder' => 'Enter your password'])
<div class="relative" x-data="{ visible: false }">
    <input id="{{ $id }}" name="{{ $name }}" type="password" :type="visible ? 'text' : 'password'" autocomplete="{{ $autocomplete }}" required placeholder="{{ $placeholder }}" {{ $attributes->merge(['class' => 'fs-input pr-12']) }}>
    <x-ui.icon-button variant="tertiary" label="Show password" ::title="visible ? 'Hide password' : 'Show password'" type="button" @click="visible = !visible" ::aria-label="visible ? 'Hide password' : 'Show password'" ::aria-pressed="visible" aria-controls="{{ $id }}" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-xl text-text-muted hover:text-accent">
        <x-ui.icon name="view" x-show="!visible" /><x-ui.icon name="hide" x-show="visible" x-cloak />
    </x-ui.icon-button>
</div>
