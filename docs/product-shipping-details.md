# Optional product shipping details

## Existing implementation inspected

No `AGENTS.md` was present in the repository or the checked ancestor directories at the start of this task. Work was performed on `main`; local configuration was retained.

No parcel weight/dimension/handling columns, validation or snapshots existed. The Seller controller's `dimensions` variable describes variant option keys, not parcel measurements. Product create/edit share `Seller\ProductController::validateProduct`; there is no separate Seller product FormRequest. The listing already manages variants, SKU, optimistic edit versions and a five-image gallery. Those paths are retained.

Checkout for the website and mobile API converges on `PlaceOrder`, which already snapshots SKU, price and variant options on `order_items`. The mobile resource wraps explicitly assembled arrays; `MobileViews` defines the product and purchased-item response fields. A separate public catalog serializer exists and is also extended additively.

Seller dashboard listings, Admin inventory/order detail, and Rider dashboard/pickup/delivery controls reuse a badge component. The touched Admin inventory template contained committed conflict markers; that block was resolved preserving hover styling and reasoned hide/restore controls. Unrelated account-security template markers were not changed by this task.

## Fields and validation

New migration: `2026_11_15_030000_add_product_shipping_details.php`.

| Product column | Storage | Seller input |
| --- | --- | --- |
| `length_cm`, `width_cm`, `height_cm` | Nullable DECIMAL(8,2) | Positive numeric value, at most 1,000 cm and two decimal places |
| `weight_kg` | Nullable DECIMAL(8,3) | Positive numeric value, at most 1,000 kg and three decimal places |
| `handling_flags` | Nullable JSON array | Unique list of values from `HandlingFlag` only |

All fields are optional and independent. No unit strings/free text, negative/zero measurements, duplicate flags, nested arrays or unknown flags are accepted. Limits and shared create/edit rules live in `ProductShippingDetails`. Dimensions and weight have decimal casts; flags use an array cast and are stored as enum values.

`App\Enums\HandlingFlag` owns the allowed values and their human-readable labels: `fragile`, `perishable`, `liquid`, `keep_upright`, `keep_dry`, `keep_cold`, `flammable`, `heavy`. Blade labels come from that enum rather than a separate UI list. No free-text flag input exists.

The form uses fixed cm/kg labels and number inputs, preserves old values on validation failure and prefills edits. Clearing a measurement saves null; submitting the shipping section with no boxes selected saves an empty flag array. Omitting shipping fields entirely on edit preserves previous values. Existing products remain null: there is no backfill, demo-data rewrite, or delivery-fee recalculation.

These are declared packaged measurements **per item**, common to the listing's variants. They do not calculate carton packing, multiply dimensions by quantity, change shipping charges, or establish a courier's acceptance of hazardous/perishable goods. Existing Admin-hidden-product publishing protections remain intact.

## Purchase snapshots and display

`order_items.shipping_details` is a new nullable JSON snapshot containing all five fields. New orders copy it from the locked product during checkout; values use fixed-precision decimal strings and enum strings in storage. Later product edits cannot change the stored order. Missing legacy snapshots render safely and never fall back to live product data.

Handling badges and available measurements appear on:

- Seller listing dashboard and the listing editor's shipping section.
- Admin product inventory and order review/details.
- Seller/Admin shipment controls and assigned Rider pickup/delivery job controls.
- Assigned Rider dashboard shipment cards (items eager-loaded).

The initial optional-field change did not add a Buyer-facing Blade shipping section. The subsequent [checkout aggregation](shipping-aggregation.md) task adds per-shop package estimates to checkout and stored shipment summaries; individual Seller measurements are still managed in the Seller editor.

## API contract

- Mobile product responses add nullable numeric `length_cm`, `width_cm`, `height_cm`, `weight_kg` and nullable array `handling_flags` at product level. The separate public catalog endpoint has the same additive fields.
- Order responses contain purchased items under shipments. Each purchased item adds nullable object `shipping_details` with the five fields; measurements are numbers in JSON and flags remain enum strings. Assigned Rider shipment responses use the same item serializer and snapshot.
- Null means unprovided (or a legacy item without a snapshot); an empty array means no handling flags selected. No existing field is removed or renamed. OpenAPI defines `HandlingFlag` and `ShippingDetails`; all 64 existing documented operations remain.
- **Flutter display/UI is not implemented yet.** No files in either Flutter repository were edited. Clients may consume these additive fields in a later UI change.

## Rollout and checks

Apply with `php artisan migrate`, never `migrate:fresh`. Rebuild assets using `npm run build` and restart long-running workers. Existing accounts, products, stock, orders, application key and environment configuration must be preserved.

Tests cover create/edit omitted fields, populated/cleared fields, input rejection, boundaries, old input, scope/hidden-product protection, stable web/mobile snapshots, legacy rendering, badge views, API response validation and migration rollback/reapply on both database engines. Existing Seller listing, variant, gallery, checkout and mobile regressions are included in verification.

Verification on 15 November 2026:

- SQLite: 43 selected feature/regression tests passed, 483 assertions, covering shipping, Seller listing/variants/gallery, checkout, mobile Buyer/Rider and handoff endpoints.
- MySQL (`farsell_test` only): 13 shipping/migration/mobile API tests passed, 195 assertions. The other selected regression classes passed in the earlier MySQL run; subsequent reruns addressed the legacy-catalog schema and test setup without changing their implementation.
- Production asset build, Pint, `git diff --check`, and the 64-operation OpenAPI contract check passed.
- Local MySQL received only the additive shipping migration after an encrypted SQL dump and checksum verification. User/product/order/order-item/variant/image counts were unchanged; zero existing products or order items were populated. This local dump checksum check is not a production restore rehearsal.
- No seeders, existing migrations, `.env` or Flutter files were changed. The initial optional-fields phase did not add Buyer-facing shipping views; the subsequent aggregation phase adds the checkout estimates described above.
