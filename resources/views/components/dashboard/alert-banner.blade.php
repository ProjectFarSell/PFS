<aside class="rounded-xl border border-amber-500 bg-amber-50 px-4 py-3 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
    <h3 class="flex items-center gap-2 text-sm font-semibold">
        <x-ui.icon name="warning" size="sm" /> Alert
    </h3>
    <p class="mt-1 text-sm" x-text="alert.text"></p>
    {{ $slot }}
</aside>
