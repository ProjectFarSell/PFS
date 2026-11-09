@props(['name', 'description', 'confirmLabel' => null, 'submitName' => null, 'submitValue' => null])

<div {{ $attributes->class('flex justify-end') }} x-data="{ confirming: false }">
    <x-ui.button variant="danger" type="button" class="text-red-700 dark:text-red-300"
            @click="confirming = true; $refs.confirmation.showModal()">{{ $name }}</x-ui.button>
    <dialog x-ref="confirmation" @close="confirming = false" aria-label="{{ $name }}"
            class="m-auto w-[calc(100%-2rem)] max-w-md rounded-ui border border-surface-border bg-surface p-5 text-text-base shadow-card-md backdrop:bg-black/50">
        <h2 class="text-lg font-semibold">{{ $name }}?</h2>
        <p class="mt-2 text-sm text-text-muted">{{ $description }}</p>
        {{ $slot }}
        <div class="mt-5 flex flex-wrap justify-end gap-4">
            <x-ui.button variant="secondary" type="button" autofocus @click="$refs.confirmation.close()">Keep it</x-ui.button>
            @if ($submitName)
                <x-ui.button variant="danger" type="submit" disabled ::disabled="!confirming" name="{{ $submitName }}" value="{{ $submitValue }}"
                        @click="$refs.confirmation.close()" class="text-red-700 dark:text-red-300">{{ $confirmLabel ?? $name }}</x-ui.button>
            @else
                <x-ui.button variant="danger" type="submit" disabled ::disabled="!confirming" class="text-red-700 dark:text-red-300">{{ $confirmLabel ?? $name }}</x-ui.button>
            @endif
        </div>
    </dialog>
</div>
