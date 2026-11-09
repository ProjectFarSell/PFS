@props(['name', 'variant' => 'outline', 'size' => 'md'])

@php
    $icons = [
        'edit' => 'pencil-square', 'delete' => 'trash', 'view' => 'eye', 'hide' => 'eye-slash',
        'add' => 'plus', 'subtract' => 'minus', 'search' => 'magnifying-glass', 'filter' => 'adjustments-horizontal',
        'export' => 'arrow-down-tray', 'cart' => 'shopping-cart', 'order' => 'clipboard-document-list',
        'delivery' => 'truck', 'payout' => 'banknotes', 'settings' => 'cog-6-tooth',
        'home' => 'home', 'browse' => 'squares-2x2', 'account' => 'user-circle', 'buyers' => 'users',
        'notification' => 'bell', 'chat' => 'chat-bubble-left-right', 'save' => 'heart', 'rating' => 'star',
        'product' => 'cube', 'location' => 'map-pin', 'camera' => 'camera', 'time' => 'clock',
        'more' => 'ellipsis-horizontal', 'menu' => 'bars-3', 'close' => 'x-mark', 'expand' => 'chevron-down',
        'previous' => 'arrow-left', 'next' => 'arrow-right', 'increase' => 'arrow-up', 'decrease' => 'arrow-down',
        'light' => 'sun', 'dark' => 'moon', 'confirm' => 'check', 'warning' => 'exclamation-triangle',
        'play' => 'play', 'pause' => 'pause', 'loading' => 'arrow-path', 'image' => 'photo',
    ];
    $glyph = 'heroicon-'.($variant === 'solid' ? 's-' : 'o-').($icons[$name] ?? 'question-mark-circle');
    $dimensions = match ($size) { 'sm' => 'size-4', 'lg' => 'size-6', default => 'size-5' };
    $svgAttributes = $attributes->class($dimensions.' shrink-0')->merge(['aria-hidden' => 'true', 'focusable' => 'false']);
@endphp

@svg($glyph, '', $svgAttributes->getAttributes())
