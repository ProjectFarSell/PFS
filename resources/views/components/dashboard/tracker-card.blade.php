<article class="fs-card min-w-0 p-4" x-data="dashboardDeferred" :aria-busy="ready ? 'false' : 'true'">
    <h3 class="font-semibold" x-text="tracker.title"></h3>
    <div x-show="!ready" class="mt-3 h-40 rounded-lg bg-surface-muted motion-safe:animate-pulse">
        <span class="sr-only">Loading tracker</span>
    </div>
    <template x-if="ready">
        <div>
            <template x-if="tracker.rows.length">
                <div class="mt-3 overflow-x-auto" tabindex="0" :aria-label="tracker.title">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only" x-text="tracker.title"></caption>
                        <thead><tr class="bg-surface-muted">
                            <th scope="col" class="px-2 py-2">Date</th>
                            <template x-for="column in tracker.columns" :key="column">
                                <th scope="col" class="px-2 py-2" x-text="column"></th>
                            </template>
                        </tr></thead>
                        <tbody>
                            <template x-for="(row, position) in tracker.rows" :key="position">
                                <tr class="border-b border-surface-border">
                                    <td class="whitespace-nowrap px-2 py-2 text-xs tabular-nums" x-text="row.date"></td>
                                    <template x-for="(cell, column) in row.cells" :key="column">
                                        <td class="px-2 py-2" x-text="cell"></td>
                                    </template>
                                    <template x-if="row.stock">
                                        <td class="px-2 py-2"><x-dashboard.stock-bar data-expression="row.stock" /></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
            <p x-show="!tracker.rows.length" class="mt-3 text-sm text-text-muted">No records in this period.</p>
            {{ $slot }}
        </div>
    </template>
</article>
