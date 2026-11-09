@if ($loading)
    <x-ui.icon name="loading" :size="$iconSize" class="motion-safe:animate-spin" />
    <span class="sr-only">Loading</span>
@elseif ($loadingWhen)
    <span x-cloak x-show="{{ $loadingWhen }}" class="inline-flex">
        <x-ui.icon name="loading" :size="$iconSize" class="motion-safe:animate-spin" />
        <span class="sr-only">Loading</span>
    </span>
@elseif ($wireEnabled)
    <span wire:loading.inline-flex @if($wireTarget) wire:target="{{ $wireTarget }}" @endif style="display:none">
        <x-ui.icon name="loading" :size="$iconSize" class="motion-safe:animate-spin" />
        <span class="sr-only">Loading</span>
    </span>
@endif
@if ($icon && $iconPosition === 'left' && ! $loading)
    <x-ui.icon :name="$icon" :variant="$iconVariant" :size="$iconSize" />
@endif
@if ($labelExpression)
    <span x-text="{{ $labelExpression }}">{{ $slot }}</span>
@else
    {{ $slot }}
@endif
@if ($icon && $iconPosition === 'right' && ! $loading)
    <x-ui.icon :name="$icon" :variant="$iconVariant" :size="$iconSize" />
@endif
