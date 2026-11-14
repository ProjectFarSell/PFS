@props(['href', 'label', 'icon', 'active' => false, 'count' => 0])

<x-ui.button variant="tertiary" size="sm" :href="$href" :icon="$icon"
    :aria-current="$active ? 'page' : null"
    {{ $attributes->class([
        'whitespace-nowrap !text-sm lg:min-h-target-desktop',
        'bg-dashboard-light-active dark:bg-dashboard-dark-active' => $active,
    ]) }}>
    {{ $label }}
    @if ($count > 0)
        <span class="rounded-full bg-dashboard-light-success-bg px-1.5 py-0.5 text-[10px] tabular-nums text-dashboard-light-success-ink dark:bg-dashboard-dark-success-bg dark:text-dashboard-dark-success-ink">
            <span class="sr-only">Unread messages:</span>{{ $count > 99 ? '99+' : $count }}
        </span>
    @endif
</x-ui.button>
