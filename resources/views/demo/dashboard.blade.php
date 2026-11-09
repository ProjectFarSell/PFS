<div data-demo-dashboard data-dashboard-widgets x-data="demoDashboard(@js($panel))"
     @fullscreenchange.document="syncFullscreen()"
     class="min-h-screen bg-surface [&:fullscreen]:overflow-y-auto"
     :class="{ '[&_.dashboard-kpi-value]:text-3xl [&_.dashboard-kpi-value]:sm:text-5xl [&_.dashboard-chart-title]:text-xl [&_.dashboard-chart-plot]:h-80': presenting || fullscreen }">
    <header class="sticky top-0 z-40 border-b border-surface-border bg-surface">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div>
                <p class="badge badge-accent">Demo data</p>
                <h1 class="mt-1 text-xl font-bold">{{ $heading }}</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-theme-toggle />
                <x-ui.button variant="tertiary" ::aria-pressed="presenting.toString()" @click="presenting = !presenting">Presentation</x-ui.button>
                <x-ui.button variant="secondary" @click="toggleFullscreen()" x-text="fullscreen ? 'Exit fullscreen' : 'Fullscreen'">Fullscreen</x-ui.button>
            </div>
        </div>
    </header>

    <div class="mx-auto max-w-7xl space-y-5 px-4 py-5" :class="{ 'max-w-screen-2xl': presenting }">
        <p class="text-sm text-text-muted">Fictional figures and records. Operational actions are disabled in demo mode.</p>
        <p x-cloak x-show="fullscreenError" role="status" class="text-sm text-text-muted" x-text="fullscreenError"></p>
        <div class="fs-card flex flex-wrap items-end gap-2 p-3">
            <label class="text-sm font-semibold">Demo date range
                <select class="fs-input mt-1 block" @change="selectPreset($event.target.value)" :value="preset">
                    <option value="7" selected>Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="custom">Custom</option>
                </select>
            </label>
            <div x-cloak x-show="preset === 'custom'" class="flex flex-wrap items-end gap-2">
                <label class="text-sm">From<input type="date" class="fs-input mt-1 block" x-model="start" :min="earliest" :max="snapshotDate"></label>
                <label class="text-sm">To<input type="date" class="fs-input mt-1 block" x-model="end" :min="earliest" :max="snapshotDate"></label>
                <x-ui.button variant="secondary" @click="applyRange()">Apply date range</x-ui.button>
            </div>
            <p class="w-full text-xs text-text-muted" role="status" x-text="period"></p>
            <p x-show="error" class="w-full text-sm text-red-800 dark:text-red-300" role="alert" x-text="error"></p>
        </div>
        <div x-show="!snapshot" class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-hidden="true">
            @for ($card = 0; $card < 4; $card++)
                <div class="h-32 rounded-ui bg-surface-muted motion-safe:animate-pulse"></div>
            @endfor
        </div>
        <template x-if="snapshot">
            <div class="space-y-5">
                <template x-for="alert in snapshot.alerts.filter(item => item.count > 0)" :key="alert.key">
                    <x-dashboard.alert-banner />
                </template>
                <section aria-label="Key metrics" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <template x-for="(metric, index) in snapshot.kpis" :key="metric.key">
                        <x-dashboard.kpi-card data-expression="metric" featured-expression="index === 0">
                            <x-ui.button variant="link" size="sm" disabled class="mt-2">View details</x-ui.button>
                        </x-dashboard.kpi-card>
                    </template>
                </section>
                <section aria-label="Charts" class="grid gap-3 lg:grid-cols-2">
                    <template x-for="chartSpec in snapshot.charts" :key="chartSpec.key">
                        <x-dashboard.chart-card data-expression="chartSpec" presentation-expression="presenting || fullscreen" />
                    </template>
                </section>
                <section aria-label="Trackers" class="grid gap-3 lg:grid-cols-2">
                    <template x-for="tracker in snapshot.tables" :key="tracker.key">
                        <x-dashboard.tracker-card>
                            <x-ui.button variant="link" size="sm" disabled class="mt-2" x-text="tracker.actionLabel">Open details</x-ui.button>
                        </x-dashboard.tracker-card>
                    </template>
                </section>
            </div>
        </template>
        <noscript><p class="fs-card p-4 text-sm">Enable JavaScript to view the demo charts and date ranges.</p></noscript>
    </div>
</div>
