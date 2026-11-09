# Failed deliveries, replacement orders and commissions

Implemented for the web/backend on 6 November 2026. Mobile apps and third-party
courier booking integrations are outside this change.

## Failed COD delivery

1. Assigned approved rider opens **Manage pickups and deliveries** after pickup.
2. Expand **Report unsuccessful delivery attempt**, select the reason and contact
   method, and describe the actual contact/handover attempt. Reasons distinguish
   an unreachable buyer, refusal, buyer-selected wrong variant, seller wrong item,
   address issue and other problems. Do not include PINs in notes.
3. The shipment becomes **Delivery attempt failed — awaiting review**. No COD
   collection, stock restoration, successful sale or rider earnings is recorded.
4. Admin opens **Failed deliveries** from the dashboard. Either schedule a retry
   with the existing rider, or arrange return to seller for uncollected COD.
5. Retry time is displayed in the configured application timezone. A future
   retry cannot be marked delivered or failed early. Up to three failed attempts
   are allowed; after that, another retry is blocked and return remains available.
6. For a return, the rider brings the goods to the pickup shop. The shop's seller
   confirms physical receipt of all shipment items and records their condition:
   **all sellable / restock**, or **quarantine / keep out of sellable stock**.
7. Sellable reservations are restored once. Quarantined goods stay out of stock.
   The returned shipment has no COD amount due. Its history remains accessible.

Buyers see the issue, retry/return status and timestamps in their order. Riders
keep failed/returning shipments in their dashboard until resolved. Buyer and
seller notification records follow transitions; current rider retry/return
instructions use the existing email queue and stale-event checks. Actual email
delivery still requires configured mail and running workers.

This is a whole-shipment refused-delivery workflow. Partial doorstep acceptance,
payment already collected, real prepaid refunds, rider reassignment while carrying
goods, automatic buyer penalties and failed-attempt/return compensation are not
automated. These require additional policy and custody/payment handling. A report
alone does not ban a buyer or automatically refund money. Completed-order returns
continue through the existing Returns & refunds workflow.

## Wrong variant after an unpaid refusal

1. Complete return-to-seller receipt first.
2. Buyer opens the original order and selects **Request another variant**.
3. Select a current active variant, quantity no greater than the original line,
   and explain the change. One request per original order item is supported.
4. The owning seller or admin approves/declines with a note. Approval does not
   reserve stock, waive costs or place an order.
5. Buyer opens **My replacement requests**, selects **Review current price &
   checkout**, reviews the desired SKU, price, address and delivery fee, and
   explicitly places a new COD order.
6. Checkout validates ownership, approval, original return status, quantity,
   product/variant, current stock and COD payment method under transaction locks.
   It reserves stock once and links the new order to the original request/order.

The original order stays returned. This is a linked replacement purchase for
unpaid goods, not a free exchange. There is no old payment to credit. Existing
paid-purchase return/refund cases are not silently converted into exchanges.
Rejected or already ordered replacement requests cannot place another linked
order; approving again is blocked. If the approved option later sells out, the
checkout is rejected without reserving other goods. Ordinary cart/Buy now use
clears replacement context so unrelated purchases cannot acquire that link.

## Approved fee policy

Defaults approved by the user:

| Component | Rule |
|---|---|
| Goods commission | 8% of shipment item subtotal |
| Rider pay | PHP 40 per buyer-completed shipment |
| COD handling cost | PHP 7 once per COD order |
| Operating cost | PHP 15 once per completed COD order |

Each new shipment snapshots these rules at checkout. Future configuration changes
do not reprice existing shipments. A goods-only commission does not increase the
buyer's checkout total. Buyer shipping charges and rider pay are separate.
Fixed order costs are attached to the first completed settlement, under the same
parent-order lock; another shop's completion does not charge them again. Each
completed shipment gets its own rider pay, so multi-shop orders can have a loss.
Negative platform contribution is displayed without clamping it to zero.

For one PHP 500 item and PHP 49 delivery:

| Allocation | PHP |
|---|---:|
| Buyer pays | 549.00 |
| Seller entitlement | 460.00 |
| Rider earnings | 40.00 |
| COD handling cost | 7.00 |
| Operating cost | 15.00 |
| Platform contribution | 27.00 |

The operating amount is a cost allocation, not an additional charge to the buyer
or seller. Contribution is not a statement of final business profit. These are
accounting records, not bank transfers or payout confirmations. There is no
integrated seller cash-out, bank reconciliation or third-party fee remittance.

**Commissions & settlements** is linked from seller/admin dashboards. Sellers
see only their own goods commission and net entitlement. Admin sees platform
costs and contribution too. Records are created only for collected COD shipments
on buyer completion. New demo payments do not post cash settlements or rider earnings. Orders
without fee snapshots keep their previous rider-pay rule and are not backfilled.

Recorded goods reimbursements add separate adjustments: proportionally reverse
commission and debit the seller's remaining goods share. The original settlement
and rider earnings are retained. Cumulative cent rounding prevents over-reversal;
delivery/handling/operating costs remain incurred. A refund recorded before buyer
completion is picked up when its settlement is later created. Settlement and
adjustment models disallow ordinary edits/deletes; selected accounting fields,
replacement decisions and delivery-attempt metadata are audited. This does not
prevent a privileged database operator from changing data directly.

Optional environment overrides in `.env.example` use centavos and basis points:
`MARKETPLACE_COMMISSION_BPS=800`, `MARKETPLACE_RIDER_PAY_CENTS=4000`,
`MARKETPLACE_COD_COST_CENTS=700`, `MARKETPLACE_OPERATING_COST_CENTS=1500`.
Existing `.env` need not be changed to use these defaults. Restart workers and
refresh config cache if changing deployment configuration.

## Setup and checks

After pulling, clear any old configuration cache with `php artisan config:clear`
and run `php artisan migrate`. Rebuild the configuration cache as part of the
normal production deployment if used. Both new migrations are additive; no
historical orders are seeded, overwritten or repriced. They have already been
applied to this local checkout. Use `npm run build` for updated Blade/CSS classes.

Retained internal dispatch still uses automatic eligible-rider offers with manual
admin assignment/oversight. It is not a third-party courier API or mobile push.
Use synthetic buyer/seller/admin/rider accounts to rehearse the numbered flows,
including refusal, retry, quarantine, rejection, out-of-stock and duplicate
submission cases. Dedicated regression tests cover these business boundaries.

Verification on 6 November: the final full backend suite passed 314 tests with
2,617 assertions (16 new tests), all nine frontend tests passed, and the frontend
build, asset budgets, PHP style, Blade compilation and route compilation passed.
Both migrations were applied locally. An isolated browser rehearsal, continued in
segments, exercised refusal -> admin return -> seller receipt -> replacement
request/approval -> buyer checkout -> seller preparation -> rider assignment,
pickup and COD delivery -> buyer completion -> seller/admin settlement screens.
It verified the PHP 460 seller amount and PHP 27 platform contribution, and the
settlement page fit a 390px viewport. Rapid dummy-admin logins also encountered
the expected used-TOTP rejection; the final ledger check used a fresh test MFA
sign-in. No real customer orders, provider payments or bank payouts were used.
