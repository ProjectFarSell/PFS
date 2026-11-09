# Courier logistics

FarSell supports one delivery method per shop shipment: **FarSell rider** or **Lalamove**. Existing shipments default to FarSell riders. No supplier, warehouse or fulfillment-center module is needed for direct pickup from sellers.

## Working without API credentials

1. Buyer checks out as usual. The agreed delivery charge is fixed at checkout.
2. Seller accepts the order, chooses a delivery method, enters pickup address/contact, and marks the shipment ready.
3. For FarSell riders, use the existing barangay/city offers and admin dispatch.
4. For external couriers, admin opens **Logistics**. Admin can change an unbooked, unassigned shipment's method before dispatch.
5. Arrange the courier booking outside FarSell. Confirm service area, parcel eligibility, pickup and recipient details, the exact COD amount (if applicable), and courier cost with the provider. Do not assume a courier account supports COD.
6. Record its existing reference, confirmed cost, optional official HTTPS tracking link and verification note. This form does **not** book a courier remotely.
7. After checking the provider's evidence, admin records pickup, then delivery with recipient/handover details and confirmed COD collection. Provider tracking status alone never confirms collection or delivery in FarSell.
8. Buyer sees courier/reference/tracking link in order details, checks the goods, and confirms receipt. External deliveries do not use the internal FarSell rider PIN.
9. For new real COD shipments, buyer confirmation posts the existing commission policy and actual external courier cost. FarSell rider pay is zero for these shipments. Accounting records do not transfer funds or confirm a courier remittance or seller payout.

Actual courier cost does not change the buyer's agreed charge. The platform absorbs the difference in the contribution calculation, including losses. Example: PHP 500 goods + PHP 49 delivery, 8% goods commission, PHP 80 courier cost, PHP 7 COD handling and PHP 15 operating allocation gives PHP 460 seller entitlement and **PHP -13 platform contribution**. The two fixed order costs are allocated once per order. Existing unsnapshotted orders retain their original accounting behavior.

## Failed delivery and cancellation

External shipments stay out of internal rider offers, assignment and earnings. Admin can record a verified courier failure, an agreed retry (maximum existing attempt limit), or return instructions. These forms record arrangements made with the provider; they do not change the provider's booking remotely. Seller confirms physical return and chooses restock/quarantine using the existing flow. Failed attempts do not release stock or record earnings.

A booked, submitting or uncertain external shipment blocks buyer cancellation. Before pickup, admin can record **verified provider cancellation/non-creation**; this requires checking the provider first and does not remotely cancel anything. The buyer can then cancel the purchase and release stock once. The original booking/reference is retained. A fresh courier booking on that same shipment is deliberately unavailable: cancel/reorder rather than overwrite its record. Cancellation or failed-delivery charges need separate manual reconciliation; they are not calculated as completed delivery costs.

## Lalamove sandbox adapter

Implemented against the [official Lalamove v3 reference](https://developers.lalamove.com/): signed PH quotation, explicit sandbox booking, and on-demand status lookup. No new PHP package is required. Credentials stay on the server. Stored quotation contact data is encrypted and excluded from model serialization and security audit fields.

Optional local configuration:

```dotenv
LALAMOVE_ENABLED=false
LALAMOVE_ENVIRONMENT=sandbox
LALAMOVE_API_KEY=
LALAMOVE_API_SECRET=
LALAMOVE_ALLOW_LIVE_BOOKINGS=false
```

Get sandbox credentials from the provider's partner portal before enabling. Run `php artisan config:clear` after local changes. Never commit keys. Admin supplies verified map coordinates and recipient phone numbers in international format; there is no geocoding or automatic address validation. The service code must be supported for that route/account. Quotations expire after five minutes. Booking is an explicit second action.

Sandbox shipments are visibly labeled and **never credit real COD collection, rider earnings or cash settlements**, even after simulated delivery and buyer confirmation. API details have automated HTTP-fake coverage; no real provider call has been validated because credentials are not available.

Booking creation is never automatically retried. An unconfirmed response becomes `unknown`; do not submit again. Check the provider portal and reconcile the existing reference or verified non-creation/cancellation. Recent in-flight submissions cannot be reconciled for two minutes (the HTTP call times out after 15 seconds). Reconciliation preserves the API environment so sandbox records cannot become real cash shipments accidentally. API environment changes invalidate existing quotations.

**Production booking remains blocked by the application** while Philippine COD/payment requirements and account access are unconfirmed, even if the environment flag is changed. Production-ready integration requires the team to verify service/COD support, fees, cancellations, settlement/remittance, real provider responses and operational procedures first.

## Deferred providers and remaining external work

J&T is deferred and is not available as a delivery choice. Official Philippine merchant documentation and credentials would be needed before adding it later. Lalamove coexists with internal riders; each shipment selects one method.

There are no courier webhooks, automatic status polling, live GPS, route drawing, auto-rebooking, automated courier payouts or warehouses in this change. Buyers open the provider's official tracking link; admin explicitly verifies operational events.

## Setup and checks

Run `php artisan migrate` after pulling this change. This adds delivery method, courier booking records and external-cost accounting without resetting accounts, products or existing orders. The normal admin MFA and recent-confirmation gates apply to Logistics writes.

Check a manual external order through seller ready → admin verified booking → pickup → delivery/COD → buyer confirmation → admin settlements. Check failure → retry or seller return as a separate order. Also check an internal rider order to verify dispatch and PIN behavior still work. Tests are in `tests/Feature/CourierLogisticsTest.php`.
