# Dashboard demos

The standalone Blade stubs are `demo.seller`, `demo.admin`, and `demo.rider` under `resources/views/demo/`. Each uses the same sidebar-free layout and the shared KPI, chart, stock, alert, and tracker components. Operational actions are disabled. Date controls, theme, presentation mode, and fullscreen operate in the browser. The sticky **Demo data** badge remains visible while scrolling and in fullscreen.

## Editing the fixtures

The three files `seller-data.js`, `admin-data.js`, and `rider-data.js` under `resources/js/demo/` define the panel seeds and fictional labels. Update the relevant panel file to change its seed or labels. Use fictional products, people, businesses, and addresses. The current fixtures contain no real addresses or user records.

`dashboard-data.js` in the same directory defines the fixed snapshot date, seeded daily values, and widget projections. The presets select the last 7 or 30 days ending on the snapshot date. Custom ranges select inclusive dates in the 90-day demo window. The same seed and date range return the same values on every reload. The preceding comparison period is generated from the same seed. Default fixtures include a low-stock listing, an overdue seller order, and a failed delivery. Increase the historical growth or dip frequency in the shared generator when updating the sample trend.

The demo views use no controllers, model lookups, or submissions. Direct Laravel rendering of all three stubs recorded zero queries with query logging and an empty in-memory SQLite database. This check covers view rendering. The future authenticated HTTP route must be verified separately. There is no registered demo URL.

## Backend Requests

1. **Demo routes and authorization.** Register GET `/demo/seller`, `/demo/admin`, and `/demo/rider` for the respective view stubs behind an opt-in config flag, default off, plus authentication and a dedicated demo ability. Enforce the flag at request time as well so cached routes remain safe. Approve whether the ability is admin-only or assigned to other presenter roles. Keep demo rendering clear of database-backed view composers, and verify query logging end-to-end after authentication. Session and authorization lookups, if any, belong to the auth boundary, outside the demo render.
2. **Single demo entry.** After route approval, add one gated Admin menu entry to a demo chooser. The chooser needs links to the three authorized demo URLs. The live dashboards need no demo links.
3. **Live chart payloads.** Supply role-scoped real chart series and comparison metrics using [the dashboard data contracts](dashboard-data-contracts.md). The shared components already accept a data prop or an Alpine data expression. The live Rider metrics use the shared KPI card with existing counts. Live graphs need real data before they can use the shared chart card.
4. **Livewire integration, if desired.** Livewire is absent from this checkout. Any future `App\Livewire\Demo` wrappers should render the same stubs without querying or persisting application records.
