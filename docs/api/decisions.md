# Mobile API implementation decisions

User authorized implementation on November 13, 2026. These are implementation defaults within that authorization; frontend/QA review and external launch approval are separate.

| Topic | Implemented decision |
|---|---|
| Token lifetime | Sanctum 24 hours, re-login; no refresh tokens. Exact app/role ability, account state and password/contact/security-version fingerprint checked on each request. |
| IDs | Additive UUID public_id columns, preserving numeric web keys. Batch backfill and batch priming for raw geography seeds. |
| Money | New responses integer PHP centavos. Legacy catalogue keeps numeric IDs/peso floats/page pagination; no mixing formats. |
| Signup | Buyer only, privacy version/consent required. Mobile passwords min 12/mixed case/numbers/symbols; existing website signup and valid older passwords unchanged. |
| OTP | Shared SignInOtp/Firebase verification with server-side ticket context; 10 minutes, five attempts, saved contacts, 60-second resend cooldown, one-use proof/ticket. Security mail cannot use a log transport. |
| Pending Rider | Valid Rider-role identity may read own status/account; every operational Rider endpoint requires approval and an unsuspended account. |
| Cart | Per-buyer durable mobile cart independent of browser sessions; versioned mutations and merge UUID replay receipts. Device-local Flutter guest data needs an explicit API merge. |
| Quote | Five minutes, no reservation; exact cart/address/options/price/stock/shipping/method snapshot, revalidated under stock locks. |
| Shipping | Existing PHP 49/order allocated across shops with existing remainder rule; preserve input line ordering while acquiring locks deterministically. |
| Replay | UUID header on order/offer/shipment transitions; user+operation+resource scope, canonical body/file hash, encrypted successful result, minimum 7 days. Domain/consumed-quote rules remain mandatory. |
| Production payments | COD only regardless of the staging flag. Gateway stub only in staging with explicit mobile flag, no real charge. |
| Offers / PIN | Existing 120-second sequential offers and atomic shared fulfillment rules. Wrong-PIN limits 5/shipment and 20/Rider per 15 minutes. |
| Earnings | Existing saved fee policy or legacy amount, one credit after Buyer confirmation and existing COD conditions. No automatic payout/remittance. |
| Evidence | Existing private local disk, SafeImage re-encoding and max 4 MiB. Failed-image cleanup plus orphan recovery; referenced-proof purge default off pending team retention decision. |
| Privacy | Existing reviewed deletion request after scoped recent-password ticket; historical Rider contact data redacted. Native Google/recovery handoff/profile editing remain separate Backend Requests. |
| Real-time | Laravel Reverb implementation/config/private auth; client events disabled. Generic user/order invalidations, offer events only to intended Rider. Live host/WSS testing pending. |
| Push | FCM HTTPv1 service-account OAuth, after-commit outbox, queued retries/recovery, provider-token disabling, expiry and binding guards. No secret/PII event payloads; actual device acceptance pending. |
| Monitoring | Admin mobile counts/heartbeat, failing CLI health check and optional generic operator email with 15-minute cooldown. No operator address invented. |
| Release | Non-production explicit QA seeder, guarded isolated reset, configurable association files/support page, schema/route validation and HTTP response contracts in CI. |

## Changes from the initial draft

Before mobile frontend integration, corrected: legacy Address geography may be null; CartLine SKU may be null; suspended Rider profile status is represented; registration can return 200 when a configured/local policy does not require a challenge; cache-header directive ordering is equivalent. Offer expiry was added to the event payload. These clarify the pre-release draft and must be reviewed by frontend; no shipped mobile client depends on that draft.

## Remaining team/provider decisions

Real staging/production domain and hosting, actual MySQL concurrency proof, Firebase sending project/credentials and native registration, app packaging/final IDs/signing fingerprints, provider origin/proxy settings, public support contact, proof/financial/dispute/backups retention and final store/legal wording. No Cloudflare deployment was performed. No approval was invented for these release gates.

## Historical draft (superseded)

The following original proposal is kept as planning history; its Pending/Not implemented statements are not current implementation status.

# Mobile API decisions and review record

Draft November 13, 2026. Related contract: `openapi.yaml` version `1.0.0-draft.1`. Proposals below are **not approved decisions**. This record corrects the supplied backend plan's inaccurate completion assumptions.

## Confirmed existing constraints

| Topic | Evidence / current behavior |
|---|---|
| Architecture | Laravel13 with existing Blade/Alpine controllers and services. This checkout does not contain Livewire components to extract from. |
| Mobile implementation | Only public health/catalog API routes exist. Sanctum is installed, but `User` has no `HasApiTokens` trait and there is no personal-access-token migration in this checkout. |
| Challenges | Buyer/Rider password/Google web sign-in uses email or phone OTP. Seller/Admin use authenticator. These rules must be preserved; Seller/Admin are outside the mobile v1 token scope. |
| Demo exemptions | Only explicit matching reserved local/testing demo identities bypass the second factor; never production/staging. |
| Monetary storage | Product/order decimal fields are peso values. `SettlementService` fee policies already use integer centavos internally. New API serialization needs deliberate conversion. |
| Shipping | `FlatRateDeliveryFeeService` returns PHP49/order; `FulfillmentService::createForOrder` allocates this fee across shops. |
| Offer expiry | `DeliveryRequestService::notifyRiders` creates sequential two-minute offers. `FulfillmentService::act` locks parent order then fulfillment/profile; preserve this ordering. |
| Proof | `SafeImage` re-encodes JPEG/PNG/WebP; existing delivery accepts optional max 4 MiB photo on the private local disk. Web proof access includes owning Buyer, assigned Rider and Admin; proposed Buyer API serves owning Buyer only. |
| Earnings | `SettlementService::riderPay` applies snapshotted policy when present, otherwise legacy delivery fee. Completion/COD conditions and unique ledger credit remain authoritative. |
| Returns | `DeliveryExceptionService` restricts retries/returns to appropriate actors and states; collected COD is not automatically reversed. |
| Privacy | Existing consent version is `2026-11-03`; account deletion is a reviewed request, not immediate erasure. |

## Decisions requiring review

| ID | Topic | Concrete proposal / question | Review owner |
|---|---|---|---|
| M01 | Token lifetime | Sanctum bearer access tokens expire24hours after full sign-in; re-login, no refresh token. Revocation/suspension/password/contact/role/security-version changes invalidate access immediately. Store fingerprint metadata with token; never make native app depend on web cookies. Confirm24hours is acceptable. | Backend + frontend/product |
| M02 | Money/legacy compatibility | New operations use centavos with tested exact decimal conversion. Keep `/catalog` unchanged; add `/products` for opaque IDs/cursor pagination/centavos. No legacy numeric ID can be substituted into a new endpoint. | Backend + frontend |
| M03 | Public IDs | Add stable opaque public UUID aliases with resource prefixes for new API-exposed entities, including geographic/offer/cart-line records. Public-ID backfill must be additive; existing numeric web FKs/URLs remain unchanged. Confirm alias strategy and migration impact first. | Backend |
| M04 | Pending rider | Allow authenticated pending/missing Rider identity to read only session, status and own account/device routes. Every delivery/offer/earnings operation requires approved profile. Buyer registrations cannot request a Rider token. Alternative: deny all Rider app login until approval. | Product + frontend |
| M05 | Cart | Durable per-buyer mobile cart, separate from browser-session cart. Client guest cart merges once per merge UUID; user can retain the browser cart independently. Confirm if web/mobile cart synchronization is instead required; that needs a deliberate shared-storage migration. | Product + backend/frontend |
| M06 | Checkout quote | Quote lifetime5minutes; bind exact cart/version, address snapshot, price/option/stock/shipping and payment policy. Revalidate inside stock/order transaction. Stock changes invalidate quote conservatively; no reservation merely for viewing a quote. | Backend + frontend |
| M07 | Idempotency | UUID key per user+operation+resource; retain successful response/hash minimum 7 days. Hash canonical validated fields and uploaded bytes, not temporary path. Transactional mutation+receipt; concurrent replay serializes. Same key/different payload409. Authentication/authorization before replay; domain uniqueness/single-use quote survives retention. | Backend + QA |
| M08 | Payment safety | Mobile production COD only. `gateway_stub` permitted only in staging with dedicated flag, never production even if misconfigured. Existing local web demo behavior does not automatically become mobile production behavior. | Backend + product |
| M09 | OTP context | Reuse SignInOtp verification logic after extracting browser-session context behind a shared interface. Ten-minute one-use primary-auth ticket, saved-contact delivery, five attempts, account60-second resend cooldown, fresh Firebase proof with replay prevention. No password-only usable token while a challenge is pending. | Backend + frontend |
| M10 | Offers / PIN throttles | Retain120-second web offers; serialize claims. Proposed additional API wrong-PIN limits5 attempts/shipment and20/rider in15minutes, with shared storage and429 Retry-After. Confirm operational retry/reset policy. | Backend + product/QA |
| M11 | Proof retention | Private local storage and max 4 MiB are existing rules. **Retention duration not agreed**; team must specify legal/support retention, deletion/anonymization exceptions and backups. Do not invent a scheduled deletion window. Failed-upload cleanup is separate. | Product/privacy owner |
| M12 | Real-time transport | Prefer Laravel Reverb per supplied plan, but package/server/private auth are not installed. Need team agreement, hosting, WSS endpoint and credentials. Channel events are generic invalidations; clients refetch authorized state after reconnect. | Backend + hosting team |
| M13 | Push | Queued FCM HTTPv1 with dedicated Firebase sending credentials/project. Existing Firebase Phone Auth public config is NOT a server push credential. Atomic outbox/after-commit handling, retries, dead-token disable, short offer TTL and stale-offer guard. On-time background delivery needs actual staging/device proof; no absolute delivery guarantee. | Backend + mobile |
| M14 | Deletion / recent identity | Reuse reviewed request; preserve legally required order/financial records. Proposed password-based5-minute reauth ticket bound to current API token. Google-only account reauth and mobile Google login are not defined; need separate identity-provider contract if included. | Backend + product |
| M15 | App packaging/deep links | Confirm one role-aware Flutter app or distinct Buyer/Rider builds. Need final Android package IDs and signing SHA256 fingerprints; iOS team/bundle IDs only if iOS is included. Cannot publish valid association files from placeholders. | Frontend + team |
| M16 | Historical delivery data | Define contact/evidence retention and whether completed Rider shipment details redact recipient contact/address. Avoid retaining operational access indefinitely without a stated policy. | Product/privacy owner |
| M17 | API scope gaps | Web chat, cancellation, returns/replacements, reviews, invoices, saved items, Google mobile login and forgot-password mobile links are absent from this plan's explicit endpoint groups. Decide first-release inclusion with written Backend Requests. | Product + frontend |

## Change discipline

This initial draft adds proposed operations and leaves the legacy catalogue unchanged. It makes no runtime or deployed contract change.

After approval: added optional fields/operations are non-breaking. Removing/renaming fields, making optional fields required, changing types, monetary units, ID meaning, errors or status semantics is breaking and needs frontend agreement plus a new API version or documented deprecation. No endpoint may be marked implemented from a document alone.

## Approval record

| Gate | Reviewer | Date | Decision / evidence |
|---|---|---|---|
| Mobile scope and v1 limits | Not supplied | — | Pending |
| Backend contract and decisions | Not supplied | — | Pending |
| Frontend contract review | Not supplied | — | Pending |
| QA acceptance coverage | Not supplied | — | Pending |

Only record real review outcomes here. The supplied plan says: “Spec first. Write docs/api/openapi.yaml and get it approved before implementing any endpoint.” It also explicitly says to wait for backend and mobile-developer approval after B1. This revision delivers that reviewable artifact; it does not pretend the missing B2–B4 implementation exists.
