# Vouchers

Admin and Seller voucher management is available from **Vouchers** in their web navigation. Buyers enter a code at checkout and click **Apply**; clearing the code and applying removes it. Only one voucher is accepted per order: a FarSell code and a shop code cannot stack, including in a multi-shop checkout.

## Funding and calculation

- **Admin / FarSell:** fixed merchandise discount, percentage merchandise discount, or free shipping. FarSell funds the saving. Seller entitlement and Rider earnings remain unchanged; the platform's settlement contribution includes the voucher expense.
- **Seller / shop:** fixed or percentage merchandise discount, limited to that Seller's own shop. Other shops' merchandise cannot meet its minimum spend or receive its discount. Seller free-shipping vouchers are not included in this version.
- A shop-funded merchandise discount is deducted **before commission**. For PHP 500 goods and an 8% rate, a PHP 50 shop voucher leaves PHP 450 goods, PHP 36 commission, and PHP 414 Seller entitlement. A PHP 50 FarSell voucher leaves the Seller's original PHP 460 entitlement. With PHP 49 shipping, the Buyer pays PHP 499 in either case; internal Rider pay stays on its existing fee snapshot.
- Fixed discounts cannot exceed eligible merchandise. Percentage calculations round down to centavos, then respect the optional maximum saving. Free shipping applies to the recorded shipping fee, with an optional maximum saving.
- Money is calculated in centavos. Allocations use exact integer arithmetic, including large multiplication, and distribute remainder cents in stable item order.

## Management and eligibility

- Codes are unique, case-insensitive uppercase identifiers, 3–32 characters (letters, digits, hyphens, underscores).
- Configure name, type/value, minimum merchandise spend, optional maximum saving, total-use limit, per-buyer limit, start/expiry, and enabled status. Dates use the timezone shown on the form.
- Admin can manage platform vouchers and oversee shop vouchers. Sellers can read/edit only vouchers belonging to their own active shop; posted owner IDs cannot change funding or ownership.
- Unused vouchers can be deleted from the list after confirmation. A locked server check rejects deletion if a checkout has redeemed the voucher, even when the list was opened before that purchase. Used vouchers can be disabled, preserving redemption history. Edits and deletions are audited and checked against a version to prevent acting on another editor's changes.
- Expired, future, disabled, exhausted, wrong-shop and insufficient-spend codes are rejected on the server. All conditions are checked again when placing the order.
- Redemption is recorded in the same transaction as the order and stock reservation. A locked voucher row prevents simultaneous purchases from exceeding a use limit. A failed checkout consumes nothing.
- Cancelled/rejected/returned purchases **continue counting as uses**; this prevents repeatedly reclaiming one-use offers. An Admin/Seller can deliberately change limits for another promotion.

## Orders, logistics and returns

The code, funding, version and discount are snapshotted on each order. Later edits cannot reprice it. Item and shipment allocations ensure each Rider/courier collects the discounted amount, including multi-shop orders. Rider COD remittance balances use that same amount. Losing a rejected/cancelled shipment does not move its discount onto another shop.

Buyer order details, confirmation email, printable invoice and the Invoice Generator payload show the discount. The external invoice payload uses the documented flat `discounts` field ([Invoice Generator API](https://invoice-generator.com/developers)); the printable fallback works without an API key.

Merchandise refund requests are capped at the amount paid after the item discount. Partial quantities receive a deterministic share of the item discount. Platform-funded reimbursements reverse the corresponding platform subsidy along with the goods/commission; Seller-funded reimbursements reverse the net goods and commission. Immutable adjustments and payout ledger entries retain the history. Completed shipping, Rider pay and incurred costs remain subject to the existing return policy. Fully discounted merchandise has no cash merchandise reimbursement to record.

## Backend routes and mobile contract

- Admin: `/admin/vouchers` and Seller: `/seller/vouchers`, with index/create/store/edit/update/destroy. `DELETE /{voucher}` requires the displayed `version` and is restricted to unused vouchers. Existing role checks, CSRF, throttling and Admin sensitive-action confirmation apply.
- Buyer web preview: `POST /checkout/voucher` with a single nullable `voucher_code`; final `/checkout` accepts that same field and independently recalculates it. Client-supplied discount amounts are ignored.
- Buyer API: `POST /api/v1/buyer/checkout/quotes` accepts optional `voucher_code`. Quote responses add integer `discount` and `shipping_discount`, nullable `voucher_code`, and a discounted `total`. Quote shipment subtotals/shipping remain gross; the order-level discount is shown separately.
- Order responses expose the same discount fields; Buyer shipment responses expose their own allocations. Rider `cod_amount_due` already uses the net amount. Internal funding/shop/database identifiers stay in the server snapshot rather than the public response.
- Order creation takes the voucher from the owned quote, never an additional posted code. Edits, exhaustion or expiry make the quote stale; idempotent retries cannot redeem twice. Old clients may omit vouchers and retain their existing checkout flow.
- The OpenAPI file has additive optional properties only; the 64 existing API routes are retained. **The separate Flutter repositories have not been edited in this website/backend change.** Their checkout input and discount display still need to consume these fields before vouchers can be selected in-app.

## Local rollout and verification

Run `php artisan migrate` and `npm run build` after pulling, then restart long-running workers. Existing orders default to zero discount. No live coupon or voucher-bearing order is automatically seeded.

For a demo, create `SHOP50` as the Seller (fixed PHP 50, minimum PHP 500), then use it as a Buyer on a PHP 500 item from that shop. Verify PHP 499 COD including PHP 49 shipping, complete delivery, and inspect the PHP 414 Seller settlement at 8%. Create a separate Admin code to compare platform funding, or try applying both as one code to confirm rejection. Test expiry, disabled codes, usage limits and a second shop independently.

Automated checks cover ownership, administration, exact allocation, funding/commission, returns, invoice payloads, web preview, mobile quote/replay/staleness, and independent MySQL checkouts racing for a last redemption. Tests use only `farsell_test` or isolated SQLite, not the website's MySQL data.

Verification on 15 November 2026:

- Full MySQL suite: 508 passed, 5 expected skips (4,840 assertions).
- Full SQLite suite: 509 passed, 4 expected skips (4,516 assertions).
- After strengthening usage checks for existing REPEATABLE READ snapshots, the MySQL voucher/mobile checkout group passed all 23 tests (247 assertions), including the independent-connection last-redemption race. The final voucher-specific SQLite group passed all 12 tests (145 assertions).
- PHP formatting, OpenAPI route validation, production asset build and asset budgets passed.
- Local MySQL migration applied after an encrypted SQL dump and checksum check; existing users, products, orders and settlement counts remained unchanged. This dump checksum check is not a production restore rehearsal.
