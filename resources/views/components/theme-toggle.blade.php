<x-ui.icon-button variant="tertiary" label="Switch color theme" ::title="$theme.isDark ? 'Switch to light mode' : 'Switch to dark mode'" type="button" x-data @click="$theme.toggle()"
        ::aria-label="$theme.isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        {{ $attributes->merge(['class' => 'theme-toggle', 'aria-label' => 'Switch color theme']) }}>
    <x-ui.icon name="dark" x-show="!$theme.isDark" />
    <x-ui.icon name="light" x-show="$theme.isDark" x-cloak />
</x-ui.icon-button>
