@props(['kind' => 'cards'])
<svg {{ $attributes->merge(['class' => 'h-full w-full']) }} viewBox="0 0 320 160" fill="none" aria-hidden="true">
    <circle cx="160" cy="80" r="67" fill="currentColor" opacity=".06"/>
    <circle cx="160" cy="80" r="49" stroke="currentColor" opacity=".12"/>
    @if ($kind === 'keyboard')
        <g transform="translate(72 41) rotate(-7 88 40)" stroke="currentColor" stroke-width="2">
            <rect width="176" height="82" rx="12" fill="currentColor" fill-opacity=".08"/>
            @for ($row = 0; $row < 3; $row++)
                @for ($column = 0; $column < 9; $column++)
                    <rect x="{{ 12 + $column * 17 }}" y="{{ 11 + $row * 17 }}" width="12" height="12" rx="3" opacity="{{ $row === 1 ? '.7' : '.35' }}"/>
                @endfor
            @endfor
            <rect x="46" y="62" width="80" height="10" rx="3"/>
        </g>
    @elseif ($kind === 'figure')
        <g stroke="currentColor" stroke-width="2.5" stroke-linejoin="round">
            <ellipse cx="160" cy="135" rx="45" ry="8" fill="currentColor" fill-opacity=".08"/>
            <path d="m143 109-7 22m41-22 7 22m-39-61-23 25m53-25 23 25m-51-34-9 47h44l-9-47"/>
            <path d="M139 43c0-15 42-15 42 0v9c0 30-42 30-42 0Z" fill="currentColor" fill-opacity=".08"/>
            <path d="m140 44 10-10 15 12 14-5M150 54h1m18 0h1"/>
        </g>
    @else
        <g stroke="currentColor" stroke-width="2">
            <rect x="105" y="28" width="70" height="104" rx="9" transform="rotate(-14 140 80)" fill="currentColor" fill-opacity=".06"/>
            <rect x="144" y="27" width="70" height="104" rx="9" transform="rotate(12 179 79)" fill="currentColor" fill-opacity=".1"/>
            <path d="m180 51 6 17 18 5-15 11 1 18-15-11-17 6 6-17-11-14 18 1Z"/>
        </g>
    @endif
    <path d="M48 48h8m-4-4v8m208 52h8m-4-4v8" stroke="currentColor" stroke-width="2" opacity=".4"/>
</svg>
