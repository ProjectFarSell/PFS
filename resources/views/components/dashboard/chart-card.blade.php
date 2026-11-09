@props(['data' => [], 'dataExpression' => null, 'presentationExpression' => 'false'])

<article data-dashboard-widgets class="fs-card min-w-0 p-4"
         x-data="dashboardChart(() => {{ $dataExpression ?? IlluminateSupportJs::from($data)->toHtml() }})"
         x-effect="update(chart, {{ $presentationExpression }}, $theme.isDark)">
    <h3 class="dashboard-chart-title font-semibold" x-text="chart.title"></h3>
    <p class="mt-1 text-xs text-text-muted" x-text="chart.summary"></p>
    <div hidden aria-hidden="true">
        <span data-chart-color class="text-dashboard-light-1 dark:text-dashboard-dark-1"></span>
        <span data-chart-color class="text-dashboard-light-2 dark:text-dashboard-dark-2"></span>
        <span data-chart-color class="text-dashboard-light-3 dark:text-dashboard-dark-3"></span>
        <span data-chart-color class="text-dashboard-light-4 dark:text-dashboard-dark-4"></span>
    </div>
    <div class="dashboard-chart-plot relative mt-3 h-52 sm:h-64" :aria-busy="loading ? 'true' : 'false'">
        <canvas x-ref="canvas" class="border-surface-border bg-surface text-text-base"
                :class="{ invisible: loading || failed }" role="img" :aria-label="chart.summary"></canvas>
        <div x-show="loading" class="absolute inset-0 rounded-lg bg-surface-muted motion-safe:animate-pulse">
            <span class="sr-only">Loading chart</span>
        </div>
        <p x-show="failed" class="absolute inset-0 grid place-items-center p-3 text-center text-sm text-text-muted">Chart unavailable. Open the data table below.</p>
    </div>
    <details class="mt-2">
        <summary class="cursor-pointer py-2 text-sm font-semibold text-accent">View chart data</summary>
        <div class="max-h-64 overflow-auto" tabindex="0" :aria-label="chart.title + ' data'">
            <table class="w-full text-left text-sm">
                <caption class="sr-only" x-text="chart.title"></caption>
                <thead><tr class="bg-surface-muted">
                    <th scope="col" class="px-2 py-2">Group</th>
                    <template x-for="dataset in chart.datasets" :key="dataset.label">
                        <th scope="col" class="px-2 py-2 text-right" x-text="dataset.label"></th>
                    </template>
                </tr></thead>
                <tbody>
                    <template x-for="(label, point) in chart.labels" :key="point">
                        <tr class="border-b border-surface-border">
                            <th scope="row" class="px-2 py-2 font-medium" x-text="label"></th>
                            <template x-for="dataset in chart.datasets" :key="dataset.label">
                                <td class="px-2 py-2 text-right tabular-nums" x-text="$dashboardMetric(dataset.values[point], chart.unit)"></td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </details>
</article>
