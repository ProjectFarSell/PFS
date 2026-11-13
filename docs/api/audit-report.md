# Mobile backend implementation audit

Updated November 13, 2026 after implementation authorized by the user. The baseline audit below is historical; its missing-endpoint findings have been addressed. Backend implementation does not constitute staging, device or store acceptance.

## Current verification

| Item | Result and evidence |
|---|---|
| Spec and route coverage | PASS: OpenAPI validates; all 60 operations resolve. New API response schemas are checked by HTTP feature tests. Legacy health/catalogue remain compatible. |
| Backend regression | PASS: 420 tests, 3,577 assertions, including 42 mobile tests; existing web workflows are covered by the same suite. |
| Authentication and roles | PASS automated checks for OTP, one-use proof, expiry, revocation, security fingerprint changes, pending Rider restrictions, wrong-role rejection and throttling. |
| Ownership and sensitive fields | PASS automated checks for foreign order/shipment/proof rejection, Buyer-only in-transit PIN, safe Rider responses, private proof responses and generic event payloads. |
| Reliability | PASS automated checks for replay, conflicting payloads, stale quotes, cart versions, shipment states, image cleanup and unique earnings. Deterministic stock/assignment locks implemented. Actual simultaneous MySQL contention remains UNVERIFIED. |
| List performance | PASS bounded product-list query-count check and cursor/schema checks. Production load testing remains UNVERIFIED. |
| Production payments | PASS: production rejects the staging stub even with its flag enabled. COD is the only supported production mobile method. |
| FCM and private channels | PASS mocked-provider audience, binding, expiry, deduplication, dead-token and channel authorization tests. Live background delivery and WSS acceptance remain UNVERIFIED. |
| Release support | PASS automated fixture/reset guards, association-file configuration, support page and default proof-retention checks. Missing app identifiers return 503 rather than fabricated association data. |
| Build/tooling | PASS Laravel view/route compilation, Pint, website build, asset budgets and all 19 frontend tests. Composer locked-dependency audit found no security advisories. The buyer Flutter integration is tracked separately in ../../farsell_mobile/README.md and ../mobile/buyer-check-flow.md; the Rider app is deferred. |
| Local migration | PASS encrypted database-only backup restored and verified before additive migration. Counts preserved: 10 users, 27 products, 13 orders. All existing products received public UUIDs. No fixtures inserted into the normal database. |
| Local HTTP smoke check | PASS catalogue/products 200, unauthenticated session 401, support 200, unconfigured Android association 503. |

## Remaining work and priority

1. Before native acceptance: frontend reviews the final contract and validates the connected Buyer screens on actual devices. The main app now integrates secure tokens, OTP, cart/checkout and order screens. The Rider app is a separate deferred project, per the user; its screens are not implemented here.
2. Before Rider pilot: provision separate staging HTTPS/database/queue/FCM/Reverb settings; run real background push, reconnect and parallel MySQL claim/order tests. Observe queue backlog and delivery latency on actual devices.
3. Before store submission: provide final app identifiers and signing fingerprints, validate association files and installed-app routing, approve retention/privacy/support wording and the reviewed deletion policy. Referenced-proof automatic deletion defaults off pending that policy.
4. Before launch: obtain frontend/QA acceptance against staging. No hosting, Cloudflare setup, production booking/payment or store publication was performed.

Provider credentials and actual `.env` values were not changed. New providers default off. No commits or push were requested for this implementation. See `../mobile/backend-runbook.md`, `decisions.md` and `realtime.md` for operation and handoff details.

## Historical baseline audit

November 13, 2026; source baseline `main` at `3f65a32`. Input: **Mobile-Plan-Backend.docx**. This is the initial source/inventory audit before B2–B4, **not** the document's proposed audit of a finished API. No new API endpoint or fresh behavioral/security test result is claimed.

`PASS (source)` means the named source was found. `FAIL (missing)` means required mobile implementation is absent. `UNVERIFIED` means it needs implementation tests, provider/device evidence or approval; it does not mean a confirmed vulnerability exists in the website.

## Stage correction

| Stage | Document says | Verified checkout state |
|---|---|---|
| B1 OpenAPI | Done | Was absent; review draft now supplied, approval pending. |
| B2 mobile authentication | Done | Missing routes/controllers/resources/token schema and user trait. Web auth is not Sanctum mobile auth. |
| B3 Buyer API | Done | Only legacy public catalogue exists; buyer auth/cart/address/quote/order APIs missing. |
| B4 Rider API | Done | Web rider workflow exists; mobile JSON operations missing. |
| Device registration | Done | No API registration/removal routes or device-token persistence found. |
| B5 push/real-time | Partly done | Existing queued email notifications are reusable groundwork, not FCM or private websocket support. |
| B6 staging/release | Some checkpoints Done | No staging environment/domain or device sign-off supplied. Website privacy/release tools exist but do not prove mobile release readiness. |

## Findings and priority

| Priority | Item | Result | Source / next action |
|---|---|---|---|
| P0 | Approved scope and contract | UNVERIFIED | Review `mvp-scope.md`, `openapi.yaml`, `decisions.md`; no approval recorded. |
| P0 | Mobile token issuance/expiry/revocation | FAIL (missing) | `User` lacks `HasApiTokens`; no personal-access-token migration; `routes/api.php` has health/catalog only. Add scoped tokens only after full required challenge. |
| P0 | Buyer token on Rider routes / reverse403 | FAIL (missing) | No protected mobile route groups. Add integration tests using real issued tokens, not only simulated users. |
| P0 | Unapproved Rider operational access blocked | FAIL (missing API) | Web checks exist. Add mobile approval middleware and approved/pending/missing-profile fixtures; decide status-only exception. |
| P0 | Foreign order/shipment/proof404 | FAIL (missing API) | Website policies/services exist; mobile lookups/resources missing. Test every resource boundary. |
| P0 | PIN never in Rider responses | UNVERIFIED | `Fulfillment` contains encrypted PIN and hash; no API serializers exist. Use explicit separate Buyer/Rider allowlists and recursive leak tests. |
| P0 | PIN only owning Buyer while in transit | UNVERIFIED | Define omission, not a nullable PIN in every response. Test pending/assigned/in_transit/delivered/completed and foreign buyers. |
| P0 | PIN attempt throttling | FAIL (missing API) | Add per-shipment and per-rider shared-cache limits and429 Retry-After; exact thresholds proposed in decision register. |
| P0 | Idempotency payload/file replay | FAIL (missing) | Existing transactional transitions are not durable request replay. Add transactional receipts, canonical payload/file hashes and concurrency/reuse tests. |
| P0 | Exactly-one offer assignment | PASS (source); mobile concurrency UNVERIFIED | `FulfillmentService::act` parent/fulfillment/profile locking and sequential offer check exist. Test actual parallel API claims against MySQL, not SQLite-only assertions. |
| P0 | Stale quote/cart/address/price rejection409 | FAIL (missing) | No quote/mobile cart version storage. Extract shared checkout preparation and use server snapshot/transaction checks. |
| P0 | Production COD-only / stub impossible | UNVERIFIED for mobile | Existing web has `release.allow_demo_payments`; mobile requires explicit environment AND flag guard. Add production/staging negative tests rather than assuming flag default is enough. |
| P0 | No credentials/PIN/personal data in logs/push | PASS (source telemetry allowlist); API/provider behavior UNVERIFIED | `RequestTelemetry` avoids request bodies/querystrings; no mobile handlers/push exist. Test normal and exception paths; never log provider bodies or validation values containing secrets. |
| P1 | Existing health/catalog preserved | PASS (source) | `routes/api.php`, `Api/V1/CatalogController.php`. Existing catalogue has numeric IDs, peso floats and page pagination; cannot silently change these to new conventions. |
| P1 | Catalogue/header/error compatibility | UNVERIFIED runtime | Legacy Laravel validation envelope differs from proposed Error schema. Draft explicitly marks compatibility exception and introduces new `/products`. |
| P1 | Common errors including429 header | FAIL (missing on new API) | `bootstrap/app.php` chooses JSON but does not normalize all failures to `{message,code,errors?}`. Preserve existing legacy/web behavior when adding scoped renderer. |
| P1 | Opaque public IDs / centavos / UTC | FAIL (missing new API layer) | Models use numeric database keys and decimal pesos; add public aliases/resources/conversion tests. No float repricing. |
| P1 | Persistent cart/version and guest merge receipts | FAIL (missing) | `Cart` uses browser session. Separate mobile storage and shared validation need design/extraction. |
| P1 | Address hierarchy and owner validation | PASS (source); mobile extraction missing | `AddressController::validatedAddress` is private and uses numeric IDs. Extract shared rules without weakening policy. |
| P1 | Pagination and N+1 | PASS (source legacy pagination); new lists missing | New cursor lists need per-endpoint relation/query-count tests. Bound detail history/event arrays if large. |
| P1 | Private uploaded proof cleanup | PASS (source); API behavior missing | `FulfillmentController`, `SafeImage` use local private path and cleanup on failure. Reuse in multipart transition including idempotent retries. |
| P1 | Earnings credit rules unchanged | PASS (source) | `FulfillmentService::recordState`, `SettlementService::riderPay`. New-policy vs legacy earnings differ; never credit merely on rider delivery. |
| P1 | Device binding/signout/dead-token handling | FAIL (missing) | Need unique installation/provider token constraints, token-specific binding, race/rebind tests. |
| P1 | FCM delivery from committed events | FAIL (missing) | Email outbox exists but is not mobile push. Need dedicated server credentials, generic events, retries, dead-token handling and stale-offer cutoff. |
| P1 | Private real-time authorization | FAIL (missing) | No Reverb package/channel routes or mobile broadcast authorization. Decide transport before implementing contract. |
| P1 | Provider/real-device background offer delivery | UNVERIFIED | Requires separate staging Firebase project, worker and real Android background testing; cannot prove from a source review. |
| P1 | Test accounts and shipment-state reset | PARTIAL | Local guarded demo seeders exist. Approved/pending Rider and all-state isolated mobile fixtures/reset still needed; no reset of real user data. |
| P1 | Retention/deletion/re-auth policy | PARTIAL | Existing reviewed account deletion and private proof handling. Retention duration and Google-only recent identity are unresolved decisions. |
| P1 | Deep links/support/store artifacts | UNVERIFIED / pending | Need real domain, app IDs and signing fingerprints. No placeholder association file should be published as valid. |
| P1 | CI detects OpenAPI response drift | FAIL (missing) | Existing test/build workflow is not a mobile response-schema gate. Draft validation alone does not test runtime responses. |

## Scope differences needing explicit agreement

1. The supplied document refers to Laravel11+/Livewire; this project uses Laravel13 Blade/Alpine. Shared-service extraction applies to controllers/session helpers.
2. No `docs/mobile/mvp-scope.md` existed. The new scope reconstructs the document's endpoint groups and must be reviewed; it does not prove an earlier scope approval.
3. Existing public catalogue formats conflict with the proposed universal ID/money/error conventions. The draft adds `/products` and preserves legacy `/catalog`.
4. The web cart is session-based. Cross-device/web-mobile cart synchronization is not an existing service; the proposed separate mobile cart needs team agreement.
5. The current delivery fee is once per order, allocated across shipments. A new quote must not multiply it by shop count.
6. Shipment PINs are created/stored by existing fulfillment logic; transport still must enforce owner/state-based omission. Raw model serialization is unsafe.
7. Rider credits are not universally the allocated shipping fee: snapshotted fee-policy shipments use policy rider pay, while legacy records may use allocated fee.
8. Existing sequential dispatch cannot promise a15-second popup or a realtime mobile system. Two-minute offer expiry and actual provider/device latency need honest testing.
9. Authentication for Seller/Admin remains on the website. Authenticator support is not a new buyer/rider token type.
10. App packaging (one role-aware app versus two builds), Google mobile login and web-only feature parity need explicit decisions, not assumptions.

## Acceptance evidence to add during implementation

- Contract examples/schema validation, then real HTTP request/response schema validation for each implemented operation.
- Actual Sanctum token tests: expiry, revoke, password/security/contact/role/suspension changes, exact app/ability/approval boundaries.
- Owner-scoped404 tests for all order/address/offer/shipment/evidence reads and writes; no raw PIN/hash leaks in any nested response.
- Transactional checkout/offer/state-transition tests with same/different idempotency keys and payloads; file hash replay; real MySQL competing requests.
- Stale cart/address/stock/price/quote tests; no duplicate stock changes/notifications/files/earnings on retry; web regression suite remains green.
- FCM and broadcast fake-provider tests, then explicitly separate staging/real-device evidence for background delivery, account rebinding, reconnect and channel isolation.
- Production payment deny tests even with stub flag mistakenly enabled; local/test seed guards and data-retention decisions.

No failed mobile item above was fixed merely by writing this report. B2–B6 implementation and behavioral validation remain pending after contract review.

## Buyer extension verification

The 15 additional main-app operations now expose Google primary authentication (followed by existing OTP rules), shared chat/history, public reviews/media, feedback submission, cancellation, return/refund tracking/evidence and inbox/read state. Shared website feedback, chat and Google account-resolution services are reused. API ownership, replay, upload mapping/cleanup, anonymous review presentation, cancellation restrictions, return eligibility and Google JWT signature/audience/issuer/expiry checks passed automated tests. The SQLite index rollback failure found in recovery checks was fixed; all 420 backend tests passed afterwards.

An encrypted database-only backup was restored and verified before the additive six-table UUID migration. Counts preserved: 10 users, 27 products, 13 orders, 0 messages, 1 product review and 0 return requests. Actual .env/provider credentials were not changed. No native Rider screens, live provider acceptance or hosting deployment is claimed.
