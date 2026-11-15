# Progress Log

| Date | Workstream | Branch | Summary | Open items / Backend Requests |
| --- | --- | --- | --- | --- |
| 2026-11-15 | Seller payouts — B1a and B1b-2 | `main` | Added the payable ledger, eligibility and balance services, locking foundation, and unpaid batch creation. SQLite uses immediate transactions. Full suites: MySQL 473 passed / 5 skipped; SQLite 475 passed / 3 skipped. MySQL tests exercised separate-connection lock waits and 20 iterations each of batch-creation and refund races. | Batch cancellation and mark-paid actions, seller payout history, and the historical ledger backfill remain open. Rider remittance, editable settings and checkout snapshots remain for B2. |
| 2026-11-12 | Dashboard restyle (a) | `main` | Added Seller and Admin dashboard palette and card radius tokens, a sticky top bar, a full-width mobile navigation sheet, and the same page and surface colors for the isolated demos. | Step (a) awaiting review. Steps (b)–(e) pending. Help and Seller Notifications need destinations. See [Dashboard data contracts](dashboard-data-contracts.md#backend-requests-for-the-dashboard-shell). |
