@props(['name', 'label', 'value' => 0])

<fieldset x-data="{ score: {{ (int) $value }} }" class="space-y-2">
    <legend class="text-sm font-semibold">{{ $label }}</legend>
    <div class="flex flex-wrap items-center gap-1 sm:gap-2">
        @for($star = 1; $star <= 5; $star++)
            <label class="cursor-pointer rounded-lg p-1">
                <input type="radio" name="{{ $name }}" value="{{ $star }}" x-model.number="score" @checked((int) $value === $star) required class="peer sr-only">
                <span class="sr-only">{{ $star }} {{ $star === 1 ? 'star' : 'stars' }}</span>
                <svg viewBox="0 0 24 24" class="h-9 w-9 transition-colors peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-accent sm:h-10 sm:w-10" :class="score >= {{ $star }} ? 'text-amber-400' : 'text-text-muted/30'" fill="currentColor" aria-hidden="true"><path d="m12 2.5 2.94 5.96 6.58.96-4.76 4.64 1.13 6.55L12 17.52l-5.89 3.09 1.13-6.55L2.48 9.42l6.58-.96L12 2.5Z"/></svg>
            </label>
        @endfor
        <span class="ml-2 text-sm text-text-muted" x-text="score ? score + ' / 5' : 'Choose a rating'" aria-live="polite"></span>
    </div>
</fieldset>
