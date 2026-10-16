<button type="button" x-data @click="$theme.toggle()"
        :aria-label="$theme.isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        {{ $attributes->merge(['class' => 'theme-toggle', 'aria-label' => 'Switch color theme']) }}>
    <svg x-show="!$theme.isDark" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/>
    </svg>
    <svg x-show="$theme.isDark" style="display:none;" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
        <circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/>
    </svg>
</button>
