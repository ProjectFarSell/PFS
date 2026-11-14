# FarSell mobile backend runbook

Implementation authorized November 13, 2026. The contract is `docs/api/openapi.yaml` (`1.0.0-dev.2`). This runbook covers Laravel backend code. The main Flutter buyer app is now integrated; the Rider app remains a separate future project.

## What exists

All 64 documented operations resolve to routes (61 route definitions, with four location-directory operations sharing one constrained route). Buyer/Rider authentication, cart, address, checkout, order, proof, buyer profile editing and Rider endpoints are implemented. FCM sending, Reverb private-channel authorization, an outbox, maintenance, QA fixtures and release association-file handlers are implemented. Live hosting/provider/device acceptance is not established merely by this code.

Legacy `/api/v1/catalog` remains numeric-ID/peso/page based. New `/api/v1/products` uses opaque UUID IDs, centavos and cursors. Do not mix their identifiers or monetary units. The main Flutter buyer app now uses the new reads and authenticated buyer APIs. Guest selections remain device-local until an explicit account-cart merge. See the [standalone buyer app README](https://github.com/KarlBRB/FarSell-Mobile#readme) and buyer-check-flow.md. No Rider app screens were implemented.

## Install/update locally

Back up the database and preserve APP_KEY before migrations. From PFS:

```powershell
composer install
php artisan migrate
php tools/check-mobile-contract.php
php artisan serve --host=0.0.0.0 --port=8000
```

Start the existing web development process with `composer dev` if needed. In local environments it now includes the mobile worker along with the website/frontend, notification worker and scheduler. Alternatively use separate terminals:

```powershell
php artisan mobile:work
php artisan schedule:work
```

The native client's standard Android emulator base URL is `http://10.0.2.2:8000/api/v1`; physical phones need a reachable LAN address. Staging/production must use HTTPS. Never copy Laravel `.env`, APP_KEY, service-account JSON or mail/provider passwords into Flutter.

The additive migration creates token/cart/quote/replay/device/outbox tables and public UUID columns; it does not reseed products or reset passwords/roles. Apply it before using new endpoints or the expanded admin notification monitor. Existing numeric web keys/links remain in place.

## Authentication flow

1. `POST /auth/login` with email, password and app (`buyer` or `rider`). Seller/Admin mobile tokens are refused; their website authenticator flow is unchanged.
2. Ordinary accounts receive202 plus a short-lived challenge ticket, not an access token. Explicit matching reserved local/testing demos may receive200 directly under the existing demo exemption. No staging/production demo exemption.
3. `POST /auth/challenges/send` with ticket/channel. Email uses the configured Laravel mailer. Phone prepares a challenge for the saved number; Flutter must complete Firebase Phone Auth and send the fresh ID token to `/auth/challenges/verify`.
4. Verification consumes the proof/ticket once and issues an app-scoped Sanctum token. Store it in platform secure storage. Every request uses `Authorization: Bearer ...`; browser cookies do not authorize these endpoints.
5. Tokens expire24hours after full sign-in; re-login rather than an invented refresh-token flow. Password, role, contact or security-version changes invalidate the fingerprint; suspension/revocation/expiry block access.
6. Rider identity/status can be read before profile approval; offers, shipment actions and earnings require an approved unsuspended Rider. New registration always creates Buyer.
7. Logout revokes only the current token and its current device binding. Recent password confirmation produces a separate five-minute ticket for a reviewed deletion request.

Optional contact verification is separate from login verification. Mobile registration requires a12-character mixed-case/numeric/symbol password; existing website credentials and website signup policy are unchanged. Native Google OAuth and profile/phone editing are not part of this contract; existing accounts can maintain saved contact information on the website.

### Email testing without credential logging

Use Mailpit or SMTP, never a log transport for mobile security emails. The API rejects a log mailer (including a log fallback) before sending codes. Mobile-order emails similarly refuse to log delivery details. Existing unrelated web sandbox behavior is unchanged.

The repository already supplies a Mailpit mailer. For a private local inbox, run `docker compose --profile mail up -d mailpit`, configure `MAIL_MAILER=mailpit` and `NOTIFICATION_MAILER=mailpit`, and view the inbox at `http://127.0.0.1:8025`. Docker/provider setup is an operator action; it was not started by this implementation. SMTP can be used instead when configured. Never include OTPs in screenshots, test reports, logs or committed fixtures.

## Buyer commerce

- Server cart is per Buyer and independent of the old browser-session cart. GET returns a version; new mutations must send it. Wrong version409. Quantity zero removes a line, even when its listing is unavailable.
- Merge a bounded guest cart once using `merge_id`. Same ID/body replays; changed payload409. Invalid individual lines are reported rather than substituted.
- New address saves require opaque location IDs and the actual region/province/city/barangay chain. Foreign address lookup404. Legacy addresses may expose null structured IDs.
- Quotes expire after5minutes and bind cart/version, address snapshot, prices/options/stock, shipping and enabled methods. Quotes do not reserve inventory.
- Order placement revalidates under locks and uses a durable UUID `Idempotency-Key`. Same key/body replays the original success, including201; different body409. A consumed quote cannot place another order even with another key.
- Production exposes COD only even if the staging flag is mistakenly true. `gateway_stub` is available only when APP_ENV=staging AND MOBILE_STAGING_PAYMENTS=true; it never charges money or collects card details.
- The delivery service currently charges PHP49 per order. Per-shop shipment allocation preserves the web remainder rules. No repricing of existing orders or earnings.
- Buyer order detail includes the PIN only for the owning Buyer while a shipment is in transit. Rider serializers never include the PIN/hash. Proof photos require owning Buyer authorization and `private, no-store`.

## Rider flow

Read status ? set availability ? fetch offers ? accept/decline own offer ? read assigned shipment ? pickup ? submit recipient/name/note/PIN/COD and optional photo ? Buyer confirms receipt ? existing earnings credit.

Offers last120seconds. Before assignment they expose shop name/city, opaque IDs and expiry/server time, not recipient/contact/precise address data. Disabling availability keeps accepted assignments. Only internal deliveries appear as Rider offers/assignments; Lalamove remains external logistics.

Every decision/transition uses a UUID idempotency key. Parent order and fulfillment locking plus domain checks prevent double state changes; transient database lock/serialization conflicts map to409. Real simultaneous MySQL requests still need rehearsal on the team's isolated staging database; in-process SQLite tests are not proof of production concurrency.

Delivery proof is multipart JPEG/PNG/WebP, at most4MiB, re-encoded privately through SafeImage. The replay hash includes uploaded bytes. A failed request removes its newly written image; replay does not duplicate photos/events/credits. PIN checks are limited to5/shipment and 20/Rider in15minutes with429 Retry-After.

Failed attempts need dispatch review for retry/return; collected-cash cases cannot be automatically reversed. Only Buyer-confirmed completion credits earnings, with existing policy-vs-legacy amounts. Historical Rider details redact recipient/pickup contact information; no rider GPS/location endpoints or events exist.

## QA fixtures

Explicit command only (not part of the normal seeder):

```powershell
php artisan mobile:fixtures
```

Creates guarded synthetic `mobile-buyer`, `mobile-rider`, `mobile-pending-rider`, `mobile-seller` and `mobile-admin` accounts at `@farsell.test`, a clearly named QA shop/category/geography and orders in all12 shipment statuses. Local default password is `password`; these extra QA identities still follow normal OTP policy and are not added to the four reserved demo exceptions. Use a private test inbox.

Rerunning preserves existing fixture accounts/orders and refuses a collision with an unmarked normal account. Production is always refused. Staging additionally requires MOBILE_STAGING_FIXTURES=true and a private MOBILE_FIXTURE_PASSWORD of at least12characters. Never copy QA accounts into production.

`mobile:fixtures --reset` refuses the normal database. It can rebuild ONLY an isolated SQLite file resolved beneath `storage/app/mobile-qa/` in local/testing; URL overrides and paths outside the workspace are rejected. Run with a separately configured `.env.mobile-qa` and `--env=mobile-qa`, APP_ENV=local, a separate APP_KEY, DB_CONNECTION=sqlite, DB_URL blank and an absolute DB_DATABASE path in that folder. Create the separate file before migration. It is deliberately a destructive test-environment reset; never point the normal `.env` there. Hosted MySQL reset/restore must follow the team's isolated provider-snapshot procedure.

No QA fixture seeding was run against the normal local catalogue for this implementation.

## Push / real-time setup

Read `docs/api/realtime.md` before integrating Flutter events. FCM Phone Auth's public configuration is not a push-sending credential.

Configure a separate staging Firebase project and service account with sending permission. Put the credential JSON outside public files, for example ignored `.secrets/firebase-mobile.json`; set only its absolute path in MOBILE_FCM_CREDENTIALS plus MOBILE_FCM_PROJECT_ID. Keep MOBILE_PUSH_ENABLED=false until configured. Native Firebase registration and notification permissions are still required on the phone.

For Reverb, provision REVERB_APP_ID, public REVERB_APP_KEY, private REVERB_APP_SECRET, host/port/scheme and allowed origins. Client/public host uses WSS on staging/production; bind the server internally and proxy upgrades through the chosen host. Client events are disabled. Set MOBILE_REALTIME_ENABLED=true only after checking private authorization. Example process configuration is `deploy/mobile-workers.conf.example`; it is not an executed deployment. No Cloudflare setup was performed.

Register a device installation only after full sign-in. It is bound to user, app and exact access token; changing accounts rebinds safely. Dead FCM tokens are disabled. Delivery uses queued jobs and a transactional outbox from the existing web AND API domain services. Queue failures leave recoverable pending records. Payloads are generic invalidations, not order receipts or proof documents.

```powershell
php artisan mobile:work
php artisan mobile:dispatch
php artisan mobile:status --check
```

The scheduler recovers pending events and expires offer notifications every minute. `composer dev` includes the local worker/scheduler; production needs managed long-running processes. Job timeout45seconds < worker timeout60seconds < default database retry_after90seconds; keep that ordering for other queue drivers. Multiple deliveries are possible after an uncertain provider response, so clients must deduplicate event IDs and refetch authorized current state.

## Monitoring and alerts

Admin ? Notifications now includes mobile provider flags, pending/failed counts, oldest pending timestamp and worker heartbeat. `mobile:status --check` returns nonzero for enabled providers with failure/backlog/stale-worker conditions. Disabled providers are explicitly shown as disabled rather than falsely claimed delivered.

Set MOBILE_ALERT_EMAIL to the team's operator inbox to receive generic operational alerts. The scheduler checks health and applies a15-minute alert cooldown; a failed alert send can retry. SMTP/provider outages still require the host's independent uptime/queue monitoring. No operator address was invented and no live alert was sent during implementation.

`operations` logs include sanitized API version/app/route/error class, never request bodies, bearer/challenge/FCM tokens, PINs, passwords or delivery contact details. Do not enable raw HTTP/debug logging in the reverse proxy or error-tracking provider. APP_DEBUG must be false outside local development.

## Maintenance and retention

`mobile:prune` runs daily: expired auth tickets after24hours, request receipts/quotes after7days, expired tokens through Sanctum, old expired generic events after30days, and bounded orphan-photo cleanup after24hours. Receipt cleanup does not remove quote-consumption/domain uniqueness protections within the successful order lifecycle.

MOBILE_PROOF_RETENTION_DAYS=0 intentionally disables automatic deletion of referenced delivery proofs until the team approves a retention window. With an explicit positive value, only old terminal proofs without return/replacement records qualify. Core orders, ledger amounts and timeline history are not deleted by this photo job. Align backups, legal/support holds and account anonymization before approving a real retention policy.

The account deletion endpoint creates the existing reviewed request; it does not promise instant deletion of financial/order records. Final public wording and retention decisions remain release gates.

## App links and store prerequisites

`/.well-known/assetlinks.json` serves configured Android package/SHA256 certificate fingerprints. `/.well-known/apple-app-site-association` serves configured iOS team/bundle IDs if iOS is included. Missing/invalid configuration returns 503 instead of a fake association. Public links are `/privacy` and `/support`; configure MOBILE_SUPPORT_EMAIL and publish final contact/deletion/retention wording.

Need from the team: real HTTPS domain, final Android package ID and release signing fingerprint (plus iOS identifiers only if used). Run the platform validators and actual installed-app deep-link tests. Association-file code alone does not register intent filters in Flutter or prove app-link routing works.

## Verification and remaining live gates

```powershell
php tools/check-mobile-contract.php
php vendor/phpunit/phpunit/phpunit
php vendor/bin/pint --dirty --test
npm.cmd run build
node tools/check-assets.mjs
```

CI validates the OpenAPI structure and 45-route coverage, then HTTP feature tests reject response drift. Feature coverage includes expiry/revocation/roles, OTP proof reuse, owner-scoped404s, stale quotes, cart versions, file replay/cleanup, PIN limits, receipt/earnings uniqueness, production payment guards, device rebinding, generic push payloads and private-channel auth.

Still requires external evidence: staging TLS/host/secret isolation, actual parallel MySQL transactions, background Android FCM within the offer window, live WSS reconnect/auth, notification permission behavior, deep-link association and native routing, approved proof/financial retention, privacy/support sign-off and store acceptance. These are not marked passed by mocked-provider tests.

Buyer extension update: 15 additive operations now cover Google identity verification, shared chat, public reviews/media, feedback submission, cancellation, returns/evidence and notification inbox. Read [buyer-extensions.md](../mobile/buyer-extensions.md) for main-app setup. Provider credentials, hosting, deep-link validation and signed-release acceptance remain external gates. No Rider app screens were built.
