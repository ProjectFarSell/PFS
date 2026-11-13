# Mobile scope - implementation update

November 13, 2026: the user authorized the backend implementation. All 45 OpenAPI operations are now routed, with shared business services, automated response-contract tests and provider/release code. See `docs/api/README.md` and `backend-runbook.md` for current behavior and live gates. Flutter authenticated-screen integration and external acceptance are not implied by backend presence.

The following pre-implementation scope draft remains the original boundary/reference. Its statements that only two endpoints exist and all other operations are proposals are superseded by this update.

# FarSell mobile MVP scope — backend contract draft

Prepared November 13, 2026 against `main` commit `3f65a32` and the supplied **Mobile-Plan-Backend.docx** dated November 12. This file reconstructs the Backend Requests described in that document; it is not an independently approved product brief.

The document's claim that B1–B4 are finished does not match this checkout. Only `GET /api/v1/health` and `GET /api/v1/catalog` currently exist. The Flutter project contains its starter screen. Everything below is a requested capability until implemented and tested.

## Ownership and review

- P1/backend owns the API specification, Laravel implementation, permissions, business-service reuse and backend tests.
- P2/mobile frontend reviews the contract and implements Flutter navigation, screens, secure token storage and device behavior.
- P3/QA reviews role isolation, test scenarios, device coverage and release evidence.
- The product owner/team approves open decisions, retention policy and launch scope.

The contract is [openapi.yaml](../api/openapi.yaml), revision `1.0.0-draft.1`. [decisions.md](../api/decisions.md) records proposals and unresolved decisions. No approval has been recorded. A draft proposal is not a new endpoint or a deployed feature.

## Apps and roles

Buyer and Rider are separate logical app roles. One Flutter codebase with role-specific entry/navigation or two app builds is a frontend/team decision. Tokens are bound to one logical app and exact user role; switching screens never changes permissions.

Seller and Admin operations remain on the website for v1. New signups are Buyer accounts; existing website approval grants Rider access. The app cannot assign itself a role or set a demo flag.

## Backend Requests covered

| Group | Required mobile behavior | Contract coverage |
|---|---|---|
| Identity | Password sign-in, required email/phone challenge, Buyer registration and consent, identity/session, logout, optional saved-contact verification, reviewed deletion request | `/auth/*`, `/session`, `/account/*` |
| Push | Register/remove installation, safely rebind on account change, deliver generic committed-domain-event notifications | `/devices/{installation_id}`; sending is B5 |
| Catalogue | Existing legacy catalogue compatibility plus mobile products/details, active variants, SKU, price and stock | `/catalog` unchanged; new `/products` and `/products/{product_id}` |
| Cart | Get cart, add quantity, set quantity/remove, version checking, retry-safe guest-cart merge with rejected lines | `/buyer/cart`, `/buyer/cart/lines`, `/buyer/cart/lines/{line_id}`, `/buyer/cart/merge` |
| Address directory | Own saved-address CRUD and linked Philippine region/province/city/barangay choices | `/buyer/addresses*`, `/locations/*` |
| Checkout | Server quote, snapshot/expiry, current shipping, stock validation, COD order placement, idempotency | `/buyer/checkout/quotes`, `POST /buyer/orders` |
| Order history | Own orders, per-shop shipments, status timeline, verified courier link, owner-only in-transit PIN | `GET /buyer/orders`, `GET /buyer/orders/{order_id}` |
| Completion and proof | Confirm receipt once; private proof image; preserve current earnings rules | `/buyer/shipments/{shipment_id}/confirm-receipt`, `/proof-photo` |
| Rider status | Own approval/availability; switching unavailable keeps assignments | `/rider/status`, `/rider/availability` |
| Rider offers | Eligible unexpired offers, server time, atomic accept/decline, no private recipient data before assignment | `/rider/offers`, `/rider/offers/{offer_id}/decision` |
| Rider delivery | Assigned shipments/history, pickup, PIN/name/note/COD proof with optional photo, failed attempts and dispatch instructions | `/rider/shipments*` |
| Earnings | Credited total and own paginated ledger, no payout/remittance transfer | `/rider/earnings` |
| Real-time | Sanctum-authorized private user/order channels; generic invalidation events, no location | `/broadcasting/auth`; event/channel transport is B5 |

All non-legacy paths above are **new proposals**. See the [operation map](../api/operation-map.md) for each verb, effort, risk and reuse/extraction point.

## Business rules to retain

- Reuse `StockReservationService`, `FulfillmentService`, `DeliveryRequestService`, `DeliveryExceptionService`, `SettlementService`, `CheckoutAvailability` and shared address/auth policies. Extract controller/session-dependent logic rather than copying it into new API controllers.
- An order can contain multiple per-shop fulfillments. Buyer confirmation completes one delivered shipment. It must not complete other shipments or create duplicate credit.
- Production payment is COD only. A simulated gateway is not a real payment integration; the proposed mobile stub is staging-only behind an explicit flag.
- Current shipping service returns PHP49 for the order, allocated across its shipments with existing rounding. Do not accidentally charge PHP49 for every shop.
- Current offer validity is two minutes, not a 15-second mobile popup. Availability, approval, city and assignment rules remain authoritative on the server.
- Buyer PIN is displayed only to the owning buyer while the shipment is in transit. Riders submit it but never receive it in a response.
- Internal Rider and external Lalamove delivery are distinct. External courier shipments cannot appear as internal-rider offers or earn internal-rider credit.
- Current earnings use the shipment's snapshotted fee policy when present; older shipments may use their allocated delivery fee. Do not rewrite historical amounts to a new fixed rule.
- Failed attempts go to dispatch for retry/return decisions. Collected-cash cases need manual dispatch intervention. Seller physical receipt is required before stock-restoration decisions.
- Local/testing demo second-factor exemption remains limited to explicitly marked matching reserved accounts. No production exemption.

## v1 limits

No rider-location endpoints, GPS/background polling, route optimization, automatic offline writes, barcode scanning, warehouse-management module, real prepaid gateway, automatic COD remittance or seller/admin mobile workspace.

Rider push is required before mobile launch because offers expire quickly. Polling while the app is foregrounded is a recovery path, not proof of background push delivery.

Chat, saved products, cancellation, return/replacement forms, reviews, invoices, Google mobile login and password-reset mobile handoff already have related web functionality but are not specified as mobile endpoint groups in the supplied backend plan. Record these as explicit follow-up Backend Requests if the team expects them in the first app release; do not silently claim they are covered by this draft.

## Contract conventions

- New API amounts: integer PHP centavos; new resource IDs: opaque strings; timestamps: UTC ISO8601 ending in `Z`.
- Legacy `/catalog` retains its numeric IDs, peso prices, page-number pagination and existing error behavior. The new `/products` catalogue avoids silently breaking that contract.
- New JSON failures: `{message, code, errors?}` with field errors only when appropriate. Wrong role403, unauthenticated401, foreign resource404, state/version conflict409, invalid input422, throttling429 with `Retry-After`.
- New lists use bounded cursor pagination; no per-row queries or unbounded loading.
- `Idempotency-Key` is required for order creation and shipment/offer transitions. Replays must authenticate/authorize, compare payload/file hashes, and never repeat effects. State checks and unique domain constraints remain required after key retention expires.
- API serializers use explicit allowlists, never raw models. No secrets or personal delivery details in logs, push payloads or broadcast events. Private JSON/photos use `private, no-store`.

## Handoff gates

1. Team reviews this scope and the decision register.
2. Backend/frontend review the complete OpenAPI contract and record approval.
3. Implement B2 authentication, then B3 buyer groups and B4 rider groups with targeted ownership/state/replay tests and reviewable commits.
4. Audit implementation against the approved contract. The current [audit report](../api/audit-report.md) is a source-only baseline, not a passing API test report.
5. Prepare staging and repeatable approved/pending-rider test fixtures; run phone/device scenarios.
6. Implement queued FCM delivery, private real-time channels and published event contract. Verify background rider delivery on a real device.
7. Finish deep-link association, support/privacy/deletion documentation, retention, provider monitoring and CI contract validation before release.

There is currently no production/staging domain, FCM sending credential, Reverb deployment, approved proof-retention duration or confirmed Android application ID/signing fingerprint recorded in this task. Hosting and Cloudflare setup remain team work. No live notification, booking or payment should be triggered while drafting/testing.
