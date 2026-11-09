@props(['label' => 'More', 'context' => null, 'inline' => false])

<div {{ $attributes->class('relative') }}
     x-data="{ open: false }"
     @click.outside="open = false"
     @keydown.escape="if (open) { $event.stopPropagation(); open = false; $refs.trigger.focus() }"
     x-id="['action-panel']">
    <x-ui.button variant="tertiary" type="button" x-ref="trigger" @click="open = !open" ::aria-expanded="open"
            ::aria-controls="$id('action-panel')" :aria-label="$context ? $label.' '.$context : null" class="gap-2">
        {{ $label }}
        <x-ui.icon name="more" />
    </x-ui.button>
    <div x-show="open" x-cloak :id="$id('action-panel')"
         @class([
             'mt-2 rounded-xl border border-surface-border bg-surface p-2',
             'space-y-3' => $inline,
             'absolute right-0 top-full z-20 max-h-80 w-64 max-w-[calc(100vw-2rem)] space-y-2 overflow-y-auto shadow-card-md' => ! $inline,
         ])>
        {{ $slot }}
    </div>
</div>
