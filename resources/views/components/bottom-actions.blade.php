@props(['buyer' => false])

<div {{ $attributes->class([
    'flex items-center justify-end gap-2',
    'max-md:fixed max-md:inset-x-0 max-md:bottom-[calc(3.8125rem+max(.25rem,env(safe-area-inset-bottom)))] max-md:z-40 max-md:border-t max-md:border-surface-border max-md:bg-surface max-md:px-3 max-md:py-2' => $buyer,
    'max-md:sticky max-md:bottom-0 max-md:z-10 max-md:border-t max-md:border-surface-border max-md:bg-surface max-md:pt-2 max-md:pb-[max(.5rem,env(safe-area-inset-bottom))]' => ! $buyer,
]) }} @if ($buyer) :inert="drawerOpen" @endif>
    {{ $slot }}
</div>
