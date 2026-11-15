# Vehicle-aware internal dispatch and package presets

## Existing flow preserved

FarSell already has Seller acceptance/preparation, Ready for Pickup, city-based offers, barangay assignment, Rider pickup confirmation, in-transit, delivery proof/PIN/COD confirmation, and Buyer completion. Checkout remains **COD**: the Buyer agrees to the current shipping fee when ordering and pays at delivery, not online at checkout.

Offers continue using the existing one-at-a-time queue and expiry process. No new broadcast fan-out, mobile timers, scanning, live GPS, hub, split-parcel workflow or carrier booking is added here. Internal Rider vehicle eligibility now applies to sending/listing offers, accepting claims, manual Admin assignment and automatic barangay assignment. Larger vehicles may take smaller measured jobs; smaller vehicles cannot take larger measured jobs.

## Vehicle mapping and compatibility

Registration already supports `motorcycle`, `bicycle`, `car`, and `van`. Existing records are reused, with no new vehicle field or reclassification of Rider accounts:

| Registered vehicle | Eligible measured tiers |
| --- | --- |
| motorcycle | motorcycle |
| car | motorcycle, sedan_mpv |
| van | motorcycle, sedan_mpv, van_light_truck |
| bicycle / unknown | None of the measured motor-vehicle tiers |

`DispatchVehicleEligibility` owns this mapping and both query/runtime checks. A fresh locked Rider profile is checked on claim and manual assignment, so a stale or forged offer cannot bypass the tier. The original routing/approval/city/availability rules remain. Admin assignment preserves its existing offline-rider policy; automatic offers still require online availability.

The additive migration `2026_11_15_050000_add_dispatch_vehicle_requirements.php` adds `required_vehicle_tier`, `vehicle_review_pending` and `vehicle_upgrade` to fulfillments. New measured checkouts initialize the tier from the immutable package calculation. For prior package snapshots with a null dispatch column, eligibility falls back to that snapshot's tier; existing records are not backfilled. Already-assigned deliveries are not reassigned automatically.

**Unmeasured/legacy compatibility:** when neither the dispatch field nor package snapshot supplies a tier, the pre-existing dispatch flow remains available. Such a shipment is explicitly marked unmeasured/needs shipping details and requires operational confirmation; the system cannot guarantee vehicle suitability without measurements. A Seller can request a known tier for Admin review. A designated barangay Rider who is too small is not automatically assigned; the job remains Ready for Admin routing rather than silently sending an unsuitable Rider.

## Seller upgrade and Admin review

- At Ready for Pickup, the Seller may select a higher tier and give a 10–1000 character reason describing the actual package. A separate request action is also available on an unassigned Ready shipment.
- Only the owning Seller may request. The action rejects downgrades/same-tier requests, external couriers, pending duplicate requests and assigned shipments.
- Requesting pauses dispatch and expires existing offers. The shipment remains Ready; the calculated package snapshot is retained.
- Admin reviews in shipment controls or Logistics, approving or rejecting with a reason. A Seller cannot approve their own request. Parent-order/shipment locks serialize review with claim/assignment.
- Approval updates the dispatch requirement to the requested larger tier. Rejection retains the original requirement. Dispatch resumes with fresh offers restricted by the resulting requirement.
- Requests and decisions retain actor/time/reason details in the current request record, immutable audit events and delivery event history. Repeated later requests do not erase audit history.
- This does not add extra shipping charges, change voucher allocations, recalculate commission, change Rider earnings, or transfer money. A physical two-parcel split is not implemented in this version.

Web mutation route remains `POST /fulfillments/{fulfillment}`. New actions: `request_vehicle_upgrade` (`requested_vehicle_tier`, `vehicle_upgrade_reason`) and `review_vehicle_upgrade` (`vehicle_upgrade_decision=approved|rejected`, `vehicle_upgrade_reason`). Ready/request-rider accepts the same optional upgrade fields. Existing CSRF, role and Admin sensitive-confirmation protections apply.

Buyer/Rider shipment JSON responses add optional `required_vehicle_tier` and `vehicle_review_pending`. The Rider offer acceptance's inline shipment schema includes them too. The immutable `shipping_package.vehicle_tier` remains the **calculated recommendation**, while `required_vehicle_tier` reflects any approved upgrade. All 64 existing API operations remain. No Flutter UI files were changed; native display/review UI is not implemented.

## Editable listing presets

`PackagePreset` is the single PHP source for preset labels and values:

| Preset | Length × width × height (cm) | Weight (kg) |
| --- | --- | --- |
| Small pouch | 20 × 15 × 5 | 0.5 |
| Medium box | 30 × 25 × 20 | 2 |
| Large box | 50 × 40 × 30 | 5 |

These are clearly labeled **editable estimates**, not certified package measurements. Clicking fills the four existing number inputs; Sellers can correct them before saving. It does not select/clear handling flags, auto-save the listing or bypass normal numeric validation. Exact custom measurements and all existing gallery, SKU and variant controls remain available.

## Verification and rollout

Run only additive migrations (`php artisan migrate`), rebuild assets and restart workers. Keep the database and environment private. Tests cover measured 50 kg exclusion, higher-tier eligibility, forged/stale offers, upgrade pause/approve/reject/ownership/downgrade rules, unchanged fees, valid presets, legacy package fallback, and existing Rider/mobile/barangay flows on SQLite and MySQL.

The local dispatch migration was applied after an encrypted MySQL dump and checksum verification. Account, product, order, shipment, Rider and offer row counts were preserved. No existing shipment received a fabricated vehicle requirement or upgrade request. The backup remains private; this local checksum check is not production restore acceptance.

The SQLite regression group passed 45 tests (529 assertions), and the initial MySQL dispatch/Rider/barangay regression group passed 29 tests (270 assertions). The final MySQL group passed all 13 tests (141 assertions), including the additive API fields, migration rollback/reapply and measured-vehicle pickup/delivery. A final SQLite dispatch run passed all 4 tests (56 assertions). Formatting, the production asset build and all 64 API operations passed checks. No Flutter UI is implemented.
