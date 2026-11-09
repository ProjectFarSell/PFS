@props(['name', 'label', 'value' => 0])

<fieldset x-data="{ score: {{ (int) $value }} }" class="space-y-2">
    <legend class="text-sm font-semibold">{{ $label }}</legend>
    <div class="flex flex-wrap items-center gap-1 sm:gap-2">
        @for($star = 1; $star <= 5; $star++)
            <label class="cursor-pointer rounded-lg p-1">
                <input type="radio" name="{{ $name }}" value="{{ $star }}" x-model.number="score" @checked((int) $value === $star) required class="peer sr-only">
                <span class="sr-only">{{ $star }} {{ $star === 1 ? 'star' : 'stars' }}</span>
                <span class="flex size-target items-center justify-center rounded-ui text-amber-700 peer-focus-visible:ring-2 peer-focus-visible:ring-ui-focus dark:text-amber-300"><x-ui.icon name="rating" size="lg" variant="solid" x-show="score >= {{ $star }}" x-cloak /><x-ui.icon name="rating" size="lg" x-show="score < {{ $star }}" /></span>
            </label>
        @endfor
        <span class="ml-2 text-sm text-text-muted" x-text="score ? score + ' / 5' : 'Choose a rating'" aria-live="polite"></span>
    </div>
</fieldset>
