# Payouts backend implementation handoff

Prepared 15 November 2026 against `main` at `5051288`, using **FarSell-Payouts-Backend-Handoff.pdf** and the approved [payout design](payouts-design.md). The design and mobile OpenAPI file have not been rewritten. The team confirmed **COD-only** checkout for this work; there is no new online payment integration or automatic cash transfer.

## Confirmed starting state

- No remote `payouts-wip` branch was found. The foundations are already on `main`.
- B1a ledger tables, eligibility, signed balance breakdown, B1b-1 retry/lock helpers, and B1b-2 batch creation already existed.
- SQLite already uses `IMMEDIATE` transactions.
- Existing MySQL batch tests include independent-process creation/refund races repeated twenty times; these passed locally.
- The existing needs-review page and MySQL/SQLite CI matrices exist. GitHub Actions for `5051288` succeeded: [Tests](https://github.com/ProjectFarSell/PFS/actions/runs/43695477636), [Release checks](https://github.com/ProjectFarSell/PFS/actions/runs/43695477615). These results are for the pulled baseline, not the additions below.

## Implemented additions

- B1b-3: reasoned cancellation releases active items without deleting history. Mark-paid requires a reference/date, positive matching item total, current eligibility, and reconciliation of new unpaid refund debits. Paid batches cannot be cancelled or paid twice.
- B1d: `payouts:backfill` imports identifiable settlements/adjustments once. `--dry-run` is read-only. It creates no payout or remittance history.
- B2 COD: internal collected COD, non-voided remittances, and signed cash owed. Recording locks the Rider profile and checks current owed amount. Voiding is one-way and audited; rows cannot be edited/deleted through SQL.
- B2 settings: audited commission/delivery/hold updates with a version check. Checkout locks one settings version and snapshots fees onto orders/fulfillments. Mobile quotes become stale when the settings version changes.
- Role-scoped admin, Seller, and Rider pages, with CSRF and the existing recent-admin-confirmation middleware on financial writes.
- Product gallery upload/primary/removal, private Seller business permits and Admin download, and reasoned Admin product hide/restore.

All new financial calculations and view amounts use integer PHP centavos. Decimal form values are parsed as strings. The legacy delivery-fee interface still returns a float at its boundary; new calculations convert to centavos before arithmetic. No historical order/settlement is repriced.

## Common frontend conventions

Use Laravel `route(...)` helpers. `{shop}` and `{product}` bind **slugs**, not numeric IDs. Payout/remittance/document/image identifiers are their own resource IDs. Never accept a Seller's shop identity or Rider identity from query/body input on owner pages.

All form mutations require CSRF. Validation errors use Laravel's normal error bag (or 422 for JSON requests). Wrong-role requests are 403; foreign private resources are 404 where documented. A required recent Admin confirmation returns the existing security redirect/423 before writing. Successful forms redirect to their owning page with a status message. Financial records do not execute bank payments.

Amounts ending in `_cents` are integers. `Money::decimal(...)` is available for exact signed PHP display. Dates accept ISO8601 with an explicit offset; the starter `datetime-local` forms label the application timezone. Future payment/receipt dates are rejected.

## Payout routes and view contracts

| Route name / method | Path | Role | Variables or fields |
| --- | --- | --- | --- |
| `admin.payouts.index` GET | `/admin/payouts` | Admin | `shops` (20/page), `breakdowns` keyed by shop ID, `holdDays` |
| `admin.payouts.show` GET | `/admin/payouts/shops/{shop}` | Admin | `shop`, `balance`, `payouts` (20/page), `holdDays`, `isAdmin=true` |
| `admin.payouts.store` POST | `/admin/payouts/shops/{shop}/batches` | Admin | No monetary input. Server includes eligible unassigned entries/debits. An existing unpaid batch is reused. Error: `payout`. |
| `admin.payouts.cancel` POST | `/admin/payouts/batches/{payout}/cancel` | Admin | `cancellation_reason`: nonblank string, max 1000. State error: `payout`. |
| `admin.payouts.pay` POST | `/admin/payouts/batches/{payout}/pay` | Admin | `payment_reference`: nonblank string, max 120; `paid_at`: date, not future. Stale/state/item errors: `payout`. |
| `seller.payouts.index` GET | `/seller/payouts` | Seller, own shop | Same history variables with `isAdmin=false`. Query `shop_id` cannot select another shop. |
| `admin.payouts.needs-review` GET | `/admin/payouts/needs-review` | Admin | Existing `settlements` paginator with `fulfillment.order` and `fulfillment.shop`. |

`balance` has `available_cents`, `in_unpaid_batch_cents`, and `ineligible_held_cents`. Available already includes the eligible portion in an unpaid batch; do not add in-batch again. A refund debit can make available negative, including while its source credit remains held. Payout rows expose integer `amount_cents`, `status`, timestamps, payment reference, and cancellation reason.

The batch is checked again under the shop/entry locks when paid. A newly posted unpaid debit, an open return, a missing delivery event, or a mismatched item sum rejects payment. The Admin cancels and rebuilds; the service does not silently change a previously recorded amount. Refunds posted after a completed payout reduce future availability.

## COD remittance routes and contracts

| Route name / method | Path | Role | Variables or fields |
| --- | --- | --- | --- |
| `admin.remittances.index` GET | `/admin/remittances` | Admin | `riders` (20/page, `user` loaded), `breakdowns` keyed by Rider profile ID |
| `admin.remittances.show` GET | `/admin/remittances/riders/{riderProfile}` | Admin | `rider`, `balance`, `remittances` (20/page), `isAdmin=true` |
| `admin.remittances.store` POST | same Rider path | Admin | `amount`: positive decimal-peso **string**, max two decimal places and at most current owed; `received_at`: date not future; `note`: nonblank string, max 1000. Error keys match these fields. |
| `admin.remittances.void` POST | `/admin/remittances/{remittance}/void` | Admin | `void_reason`: nonblank string, max 1000. Repeated-void error: `remittance`. |
| `rider.cod.index` GET | `/rider/cod` | Rider, own profile | Same history variables with `isAdmin=false`; foreign Rider query parameters are ignored. |

`balance` contains integer `collected_cents`, `remitted_cents`, and `owed_cents`. Only internal Rider-assigned COD shipments with `cod_collected_at` are counted, using snapshotted merchandise plus allocated shipping. External courier and simulated payments are excluded. Rider earnings are not deducted from cash owed. A void restores owed cash but retains the original row and audit trail.

## Marketplace settings

- `admin.marketplace-settings.edit` GET `/admin/marketplace-settings`: Admin only; view variable `settings` is the singleton with integer `commission_bps`, `delivery_fee_cents`, `payout_hold_days`, and `version`.
- `admin.marketplace-settings.update` PUT same path: `commission_percent` decimal **string** 0–100, max two decimal places; `delivery_fee` decimal-peso **string** 0–1000, max two places; `payout_hold_days` integer 0–90; `version` required positive integer matching the current row. Error keys are those form fields; stale version uses `version`.
- Conversion is exact: `8.00%` becomes 800 basis points; `49.00` becomes 4900 centavos. Database checks also enforce the approved bounds.
- New orders and fulfillments store the selected `fee_policy`, settings version, and stable integer allocation of shipping remainder cents. Existing snapshots remain unchanged. Changes to any settings version invalidate an unconsumed mobile quote, even if its displayed shipping price did not change.
- The existing PHP API response schema and `docs/api/openapi.yaml` remain unchanged; quote policy/version data stays inside its server-side snapshot.

## Historical import

```powershell
php artisan payouts:backfill --dry-run
# Reconcile historical payouts/cash movements before applying in a used environment.
php artisan payouts:backfill
php artisan payouts:backfill --dry-run
```

The command emits `dry_run`, `eligible`, `already_imported`, `unidentifiable`, and `imported` counts. Here **eligible means identifiable and importable**, not necessarily past the payout hold. Imported credits still pass normal payout eligibility later. Repeated application adds no duplicate source entry. Missing shop/order/settlement links are reported and skipped. No historical payout or Rider remittance is invented.

## Additional frontend requests

| Request | Backend contract / status |
| --- | --- |
| Seller packing slip | Already implemented: `seller.fulfillments.packing-slip` GET `/seller/fulfillments/{fulfillment}/packing-slip`, Seller's own shipment only; view variables `order`, `shipment`, `document=packing-slip`. |
| Seller courier form | Existing `seller.fulfillments.courier` POST `/seller/fulfillments/{fulfillment}/courier`. Own Seller, Ready/unassigned, no existing booking. Fields: `provider=lalamove`, `reference` 3–100 chars, `courier_cost` nonnegative peso decimal (up to 2 places), `verified=1`, `cod_supported=1` for COD. `tracking_url` is now optional; if supplied it must use HTTPS and an approved Lalamove tracking host. Existing Lalamove-only scope is retained; no additional courier integration is introduced. External shipments earn zero internal Rider pay. |
| Ready/unassigned filter | Already implemented: `admin.logistics.index` GET with `status=ready_unassigned`; it excludes assigned Riders and booked/submitting/unknown couriers. |
| Rider labels | Assigned/dispatch Rider models already provide `is_available`; true means Online, false Offline. |
| Rider contact/directions | `fulfillments.index` now also passes per-shipment `can_contact_buyer`, `can_get_directions`, and `buyer_contact_phone`. Flags are true only for the approved assigned internal Rider in an active accepted delivery state. Existing call/directions links use that permission. No GPS tracking is added. |
| Seller stat cards | Existing Seller dashboard variables `totalSales`, `pendingOrders`, and `stats.lowStock`. Added exact integer `totalSalesCents`; the old decimal field remains compatible. |
| Admin 30-day stat cards | Existing `ordersLast30Days` and `salesLast30Days`; added integer `salesLast30DaysCents`. Existing order/active-stock stats remain available. |
| Order notifications | Already implemented queued order-placed and shipment-state mail/events. Keep mobile worker, notification worker, and scheduler running; native live delivery still needs provider/device acceptance. |
| Rider IDs | Existing private uploads and `admin.riders.document` GET `/admin/riders/{riderProfile}/documents/{document}`. Admin-only and profile/document ownership checked. |

### Product images

Seller-only routes under `/seller/products/{product}/images`:

- `seller.products.images.store` POST: `images[]`, 1–5 JPG/PNG/WebP files, each at most 4 MiB/16 megapixels; **maximum five total photos including a legacy primary image**. A failed count/validation/upload leaves the previous gallery intact.
- `seller.products.images.primary` PUT `/{image}/primary`: image must belong to that product.
- `seller.products.images.destroy` DELETE `/{image}`: removes that image; another becomes primary when required.
- `seller.products.images.destroy-primary` DELETE `/primary`: also supports a legacy primary without a child image row.

Owner mismatch is 404, wrong role 403. The existing single `image` listing input stays compatible. Seller edit receives the Product with ordered `images`; `$product->galleryImages()` returns `id` (nullable for legacy), `url`, `alt_text`, `is_primary`. Guest product pages display the gallery. Objects remain on public R2; removal only deletes an unreferenced object after the database commit.

### Private Seller permit

- `seller.documents.store` POST `/seller/application/documents`: Buyer/Seller with their own existing pending/rejected Seller application. Field `business_permit`: PDF/JPG/PNG/WebP, max 5 MiB; images are decoded/re-encoded. Approval is not made conditional on this newly optional field.
- Uploading replaces that application's permit, changes its review revision and returns it to pending so a stale Admin review cannot approve a changed document. Approved applications require Admin follow-up rather than silent replacement.
- `seller.documents.show` GET `/seller/application/documents/{document}`: owning Buyer/Seller only.
- `admin.sellers.documents.show` GET `/admin/sellers/{application}/documents/{document}`: Admin only, matching application/document.
- Private local storage only; downloads are attachments with `private, no-store` and `nosniff`. The model hides `file_path`. Admin application detail and the own application view load `documents` for download links.

### Product moderation

- `admin.products.hide` POST `/admin/products/{product}/hide`, Admin only, required `reason` max 1000.
- `admin.products.restore` POST `/admin/products/{product}/restore`, same validation.
- `admin.products.index` supports `visibility=active|hidden|inactive`. Rows expose `admin_hidden_at` and `admin_hide_reason` to Admin/Seller management pages, never public product serializers.
- Hidden listings cannot be purchased or republished by Seller edits. Restoration is audited and returns to the previous publishing state, so an old draft is not accidentally published. Existing orders remain linked.

## Local test and rollout steps

1. Take and verify a backup of the actual target database before applying the additive migrations. For production MySQL use the [runbook's MySQL recovery procedure](operations/runbook.md); a SQLite rehearsal is not MySQL evidence.
2. `php artisan migrate`, then review `payouts:backfill --dry-run`. Do not infer historic cash payments from the new tables.
3. Start workers/scheduler and test an order → delivery → Buyer confirmation → hold eligibility → payout batch → external payment record, plus a refund before and after payout.
4. Test COD receipt and void independently of delivery earnings, with a second Rider to verify ownership.
5. Validate MySQL first (`farsell_test` only), then SQLite. The independent-connection MySQL race tests use `DatabaseMigrations`, not a hidden wrapping transaction.

The production host and automated MySQL snapshot/PITR configuration are still team deployment work. Before real payouts, prove one isolated MySQL restore, reconcile historic cash movements, and decide whether Seller payout eligibility should also wait for COD remittance. This implementation retains the approved present rule: the platform bears that timing gap. There is no live payment gateway, new courier booking, production deployment, or automatic transfer in this work.

## Verification completed locally (15 November 2026)

- Full MySQL suite, isolated `farsell_test`: **498 passed, 5 skipped**, 4,702 assertions. Skips cover SQLite-only backup/index/concurrency checks; the MySQL independent-connection batch/refund race scenarios passed.
- Full SQLite suite, isolated in-memory database: **500 passed, 3 skipped**, 4,387 assertions. The encrypted recovery rehearsal and migration rollback/reapply passed.
- The moderation rollback explicitly removes its index before dropping its column, supporting SQLite as well as MySQL. Encrypted local-file backups now include private Seller permits.
- PHP test processes receive a 512 MiB memory limit through `phpunit.xml`; the expanded suite exceeded the previous local 128 MiB allowance.
- `npm run build`, Pint, `git diff --check`, and the 64-operation mobile contract check passed. The OpenAPI contract remains unchanged.
- Existing local SQLite data was backed up with an encrypted, restore-verified archive, then copied into a new MySQL `farsell` database. All 38 copied business tables, row values and foreign keys were verified. Counts remain 12 users, 27 products and 14 orders, plus the existing Rider earnings record. The source SQLite file remains unchanged.
- Import compatibility adjustments were recorded privately: one legacy Windows-encoded address separator became UTF-8, and seven catalog comparison prices were rounded to the existing two-decimal display/schema precision. Historical order and settlement amounts were not repriced.
- Runtime sessions, authentication challenges/tokens, caches, quotes and queued delivery jobs started fresh. Stored Buyer inbox notifications and business/audit history were retained. Restart local servers/workers and sign in again after switching connections.
- Local `payouts:backfill --dry-run` found no legacy settlements/adjustments to import and wrote nothing. No payout or remittance was fabricated to populate the new pages.

The local transfer report and encrypted backup are private ignored files, not repository seed data. The local transfer does not constitute production MySQL backup/restore acceptance.
