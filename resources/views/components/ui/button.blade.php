@props([
    'variant' => 'secondary', 'size' => 'md', 'icon' => null, 'iconPosition' => 'left',
    'iconVariant' => 'outline', 'loading' => false, 'disabled' => false, 'href' => null,
    'type' => 'button', 'as' => null, 'iconOnly' => false, 'wireTarget' => null, 'loadingWhen' => null,
])

@php
    $isLink = $href !== null || $as === 'a';
    $inactive = $disabled || $loading;
    $iconSize = $size === 'sm' ? 'sm' : ($size === 'lg' ? 'lg' : 'md');
    $labelExpression = $attributes->get('x-text');
    $wireTarget = $wireTarget ?? $attributes->get('wire:target');
    $wireEnabled = ! $isLink && ($wireTarget || $attributes->has('wire:click'));
    $styles = match ($variant) {
        'primary' => 'bg-ui-brand text-accent-text shadow-sm hover:bg-accent-hover',
        'tertiary' => 'text-text-muted hover:bg-surface-muted hover:text-text-base',
        'danger' => 'text-red-700 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950/50',
        'link' => 'text-accent underline-offset-4 hover:underline',
        default => 'border border-ui-brand text-accent hover:bg-accent-subtle',
    };
    $padding = match ($size) {
        'sm' => 'px-3 py-2 text-xs',
        'lg' => 'min-h-target-primary px-6 py-3 text-base',
        default => 'px-4 py-2.5 text-sm',
    };
    $classes = 'inline-flex min-h-target min-w-target items-center justify-center gap-2 rounded-ui font-semibold transition-colors active:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ui-focus focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50';
    $buttonAttributes = $attributes->except('x-text')->class([$classes, $styles, $iconOnly ? 'p-0' : $padding]);
@endphp

@if ($isLink)
    <a @unless($inactive) @if($href !== null) href="{{ $href }}" @endif @endunless
       @if($inactive) role="link" aria-disabled="true" tabindex="-1" @endif
       @if($loading) aria-busy="true" @endif {{ $inactive ? $buttonAttributes->except(['href', ':href', 'x-bind:href']) : $buttonAttributes }}>
        @include('components.ui.button-content')
    </a>
@else
    <button type="{{ $type }}" @disabled($inactive) @if($loading) aria-busy="true" @endif
            @if($loadingWhen && !$loading) x-bind:aria-busy="({{ $loadingWhen }}) ? 'true' : 'false'" @endif
            @if($loadingWhen && !$inactive && !$attributes->has(':disabled') && !$attributes->has('x-bind:disabled')) x-bind:disabled="{{ $loadingWhen }}" @endif
            @if($wireEnabled) wire:loading.attr="disabled" @endif
            @if($wireTarget && !$attributes->has('wire:target')) wire:target="{{ $wireTarget }}" @endif {{ $buttonAttributes }}>
        @include('components.ui.button-content')
    </button>
@endif
