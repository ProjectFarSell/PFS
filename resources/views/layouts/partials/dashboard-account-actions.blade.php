<div class="flex items-center justify-between gap-2 border-t border-dashboard-light-border px-1 pt-2 dark:border-dashboard-dark-border">
    <x-theme-toggle />
    <form method="post" action="{{ route('logout') }}">
        @csrf
        <x-ui.button variant="tertiary" type="submit" class="lg:min-h-target-desktop">Sign out</x-ui.button>
    </form>
</div>
