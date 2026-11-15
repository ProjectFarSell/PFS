@props(['class' => 'size-10', 'onDark' => false])

<span aria-hidden="true" {{ $attributes->merge(['class' => 'inline-block shrink-0 '.$class]) }}>
    @if($onDark)
        <img src="{{ asset('images/brand/farsell-dark.png') }}" alt="" class="h-full w-full object-contain" width="40" height="40">
    @else
        <img src="{{ asset('images/brand/farsell-light.png') }}" alt="" class="h-full w-full object-contain dark:hidden" width="40" height="40">
        <img src="{{ asset('images/brand/farsell-dark.png') }}" alt="" class="hidden h-full w-full object-contain dark:block" width="40" height="40">
    @endif
</span>
