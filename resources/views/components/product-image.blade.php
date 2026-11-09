@props(['src', 'alt', 'width', 'height', 'priority' => false])

<img src="{{ $src }}" alt="{{ $alt }}" width="{{ $width }}" height="{{ $height }}"
     loading="{{ $priority ? 'eager' : 'lazy' }}"
     @if ($priority) fetchpriority="high" @else decoding="async" @endif
     {{ $attributes }}>
