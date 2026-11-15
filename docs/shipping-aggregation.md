# Per-seller checkout package estimates

## Agreed scope

The user selected a configurable default divisor of **5,000**, and explicitly chose to retain the current checkout fee while adding aggregation and vehicle recommendations. No vehicle/distance rate card has been approved. This aggregation phase does not add distance-based fees or carrier bookings. The subsequent [vehicle-aware dispatch phase](vehicle-dispatch.md) enforces these tiers in the existing internal offer/assignment workflow and supports reviewed Seller upgrade requests.

FarSell already splits checkout into one fulfillment per Seller. The new `ShippingPackageCalculator` groups cart lines by shop, and calculates each shipment independently. New fulfilled shipments store the result in nullable JSON `fulfillments.shipping_package`; legacy rows remain null, without backfill.

## Calculation

Each listing's packaged dimensions and weight describe **one item**. Weight is already stored in kg to three decimal places, so no duplicate `weight_grams` column is added.

For each Seller:

1. Actual weight = sum of `weight_kg × quantity`.
2. Estimated volume = sum of `length_cm × width_cm × height_cm × quantity`.
3. Volumetric weight = estimated volume / divisor. Sum volumes before dividing, then round **up** to 0.001 kg.
4. Chargeable weight = maximum of actual and volumetric weight.
5. Largest single-item side = maximum supplied length, width or height across the items, without multiplying the side by quantity.

All calculations use exact decimal arithmetic (`Brick\Math`); float conversion occurs only at the JSON API boundary. The handling flags are unioned, deduplicated and ordered by the existing `HandlingFlag` enum.

This is an **item-volume estimate**, not an algorithm that packs all merchandise into a real outer carton. Extra packaging, empty space and combined-box dimensions need confirmation. The final packed carton or multiple-parcel configuration can differ; the estimate is not represented as a live carrier quote.

## Vehicle recommendation

| Tier | Conditions (both must hold) |
| --- | --- |
| Motorcycle | Chargeable weight ≤20 kg and largest single-item side ≤50 cm |
| Sedan / MPV | Motorcycle unsuitable, chargeable weight ≤100 kg and largest side ≤100 cm |
| Van / light truck | Above either Sedan/MPV limit |

These are the requested **platform planning thresholds**, not a claim about a particular courier's supported vehicle capacities. Handling flags remain visible to the operator; selection does not establish acceptance of flammable goods, refrigeration, liquids or fragile merchandise. Existing Lalamove service selection and verification remain explicit operator actions.

Missing optional measurements do not block checkout. If any line lacks weight, `actual_weight_kg` is null. If any line lacks a dimension, volumetric weight and largest side are null. Chargeable weight and the vehicle choice remain null unless **all** items have complete measurements. The package status is then `needs_shipping_details` and the UI asks for details rather than treating unknown items as weightless. `missing_details_item_count` counts incomplete item lines; `total_quantity` counts units.

## Configuration and money

Set `SHIPPING_VOLUMETRIC_DIVISOR=5000` (the default) or `3500` in the local/server environment. Other values are rejected rather than silently producing an invalid quote. Run `php artisan config:clear` and restart long-running processes after changing it; production may rebuild its config cache normally.

The existing Admin-controlled flat delivery fee and its current per-shop allocation are unchanged. The recommendation does not introduce extra per-shop charges, distance surcharges, new Rider pay or courier expenses. Vouchers, COD collection, seller payouts and historical settlement amounts retain their existing calculations. Package metadata declares `pricing_basis=existing_flat_fee`.

The system has no approved route-distance calculation/rate card for this workflow. A later pricing task must define rates, route distance/source, rounding and capacity/packing rules before replacing the fee calculator.

## Snapshot and interfaces

- Web checkout shows a named package estimate per shop; buyer order shipment details retain the purchase estimate. Seller/Admin/Rider shipment controls and Admin logistics show the same stored record.
- `FulfillmentService::createForOrder` calculates from the **order-item shipping snapshots**, never from a later product edit. Changes to products or the configured divisor cannot reprice or recompute an existing shipment.
- The mobile quote's server snapshot now includes line shipping details and per-shop package calculations. Changing dimensions, weight, flags or the divisor makes an unconsumed quote stale even when the delivery fee is unchanged.
- Quote response `shipments[].shipping_package` and Buyer/Rider shipment response `shipping_package` are additive nullable objects. `actual_weight_kg`, `volumetric_weight_kg`, `chargeable_weight_kg`, and `max_single_item_dimension_cm` are numbers or null in JSON. Stored snapshots use decimal strings. Enum vehicle values: `motorcycle`, `sedan_mpv`, `van_light_truck`; null means no recommendation.
- All existing 64 API operations remain. No Flutter repository/UI is changed. **Flutter package/vehicle display is not implemented yet.**

## Rollout and verification

Apply the new additive migration with `php artisan migrate`, never `migrate:fresh`, then build assets. Existing data/configuration is preserved; no seeds or fake orders are created. Test Seller A's quantities separately from Seller B's, the 20/100 kg and 50/100 cm boundaries, volumetric dominance, partial measurements, a changed divisor, and stored order estimates after a product edit.

Verification completed locally on 15 November 2026:

- SQLite: **53 passed**, 586 assertions, covering the calculator, product shipping details, migration parity, checkout, mobile Buyer/Rider, vouchers and settings.
- MySQL (`farsell_test`): **41 passed**, 489 assertions, covering the calculator, shipping/product migrations, multi-shop snapshots, mobile Buyer API, vouchers and settings.
- The calculator tests include exact quantity totals, volumetric dominance, all tier boundaries, partial legacy data, both divisors, rounding once after aggregation, handling-flag union and large values without arithmetic overflow.
- Production asset build, Pint, diff checks and all **64 API operations** passed validation. The Rider offer-acceptance response's inline shipment schema also includes the new optional package field.
- The local MySQL migration was applied after an encrypted SQL dump and checksum check. Accounts, products, orders, order items, fulfillments, earnings and settlement row counts were unchanged. No existing fulfillment received a fabricated package estimate.
- Real `.env` and both Flutter repositories were untouched. Configuration defaults are documented in `.env.example`.
