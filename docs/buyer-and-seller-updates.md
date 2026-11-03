# Buyer notifications, cancellation, returns and seller sales

Implemented in this order: inbox, cancellation, returns/refunds, then sales reporting.

## Setup

Run `php artisan migrate` after pulling these changes, then build assets with `npm run build` (or use the existing Vite development process). Restart long-running queue workers after code updates. No new API keys or environment variables are needed.

## 1. Buyer notifications

Sign in as a buyer. The header bell opens **Notifications**. New orders, shipment changes and return/refund updates create persistent notifications, even when email delivery is disabled. The unread badge refreshes every 30 seconds while the tab is visible. Opening an update marks it read and opens its order; **Mark all as read** applies only to the signed-in buyer. Existing historical events are not backfilled.

## 2. Order cancellation

Open **My Orders → View order → Cancel order**, enter a reason and confirm. This cancels all remaining shipments, restores reserved product/variant stock once and records history. Already rejected shipments are not restocked again. The entire cancellation is blocked if any shipment has reached rider assignment or a later stage. Seller/rider actions lock the same parent order so competing actions are checked against current state.

Cancelled orders remain in history. Their printable documents show no payment due and no items to ship. Cancelled/rejected shipment lines are excluded from invoice charges. A queued confirmation email for a fully cancelled order is skipped; cancellation updates notify the affected seller when email notifications are enabled.

## 3. Returns and refunds

After a shipment is delivered or completed, buyers can expand **Request return / refund** under an item on the order page. Select quantity, reason and details; optionally attach one JPG, PNG or WebP photo up to 4 MB. There is one request per purchased order-item line, covering the submitted quantity. Delivery fees are excluded. There is currently no configurable filing deadline or resubmission after rejection.

**My returns & refunds** lists request status and timestamped responses. Sellers open **Returns & refunds** from their dashboard to recommend approval or dispute their own shop's requests. Admins use the same dashboard link to approve a bounded merchandise amount or reject with an explanation. Admins can require returned goods and include handover instructions in the response.

An approved request is not yet a paid refund. After reimbursing a collected COD payment outside FarSell, the admin records the payment reference and confirms payment, and confirms receipt of returned goods when required. The case then becomes **Reimbursement recorded**. Duplicate recording is blocked. Demo payments and uncollected COD cannot be marked reimbursed. FarSell does not transfer money, schedule return pickups, or automatically restock returned/damaged items. Original delivery history and rider earnings remain intact.

Evidence photos are private to the order's buyer, the shipment's current seller and admins. They are included in local encrypted backups made with `ops:backup --include-files`.

## 4. Seller sales

Open **Seller dashboard → Sales summary**. Apply a date range or download CSV. Completed COD merchandise sales, units and best-selling product/SKU snapshots use buyer-confirmed shipment completion timestamps. A completed shipment counts even if another shop in the same order is still processing. Legacy completed shipments with no completion event fall back to their last update timestamp.

Recorded reimbursements use their own reimbursement dates, including requests for earlier purchases. Sales less reimbursements can therefore be negative in a period. Delivery fees, demo payments and pending/cancelled/rejected shipments are excluded from COD revenue. Demo sales and pending/cancelled shipment counts are shown separately. This is a sales report, not a profit, tax or seller payout ledger. CSV exports contain only that seller's records, omit buyer contact/address data and neutralize spreadsheet formulas in text fields.

## Suggested presentation walkthrough

1. Buyer places a COD order; show the new unread bell notification.
2. Cancel that order before rider assignment; show history, restored stock and zero-charge printable invoice.
3. Use another order for the existing seller → rider → delivery PIN/COD → buyer confirmation flow.
4. Buyer requests a return with a photo; demonstrate that a different buyer/seller cannot view the evidence.
5. Seller responds, admin approves, then records a reimbursement only after it has actually been paid.
6. Seller opens the sales summary, adjusts dates and exports CSV; show the separate reimbursement total.

## Verification on 2026-11-03

- 277 backend tests passed (2,185 assertions), including 14 new tests for these workflows.
- 9 frontend tests, production asset build and gzip budgets passed.
- PHP formatting and whitespace checks passed; Blade and route caches compiled successfully. Route cache was cleared afterward for local development.
- The isolated migration rollback/reapply and encrypted SQLite restore rehearsal passed, verifying 42 tables without modifying the live database.
- Three additive migrations were applied locally. Existing orders and products were retained. No new credentials were needed. Verification did not deploy the app or transfer real payments.
- Automated tests render the new pages and exercise permissions and state changes. A manual multi-account browser rehearsal remains useful before presenting; no hosted payment transfer or production deployment was performed.
