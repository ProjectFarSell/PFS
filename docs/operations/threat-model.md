# Application security review

This review covers the current Laravel web app. It is a working threat model, not a penetration-test certificate or legal-compliance claim.

| Boundary / asset | Threat | Current safeguards / verification | Remaining operational requirement |
|---|---|---|---|
| Guest → authenticated buyer | Credential guessing, account creation abuse, session fixation | Server validation, password hashing, login/signup limits, session regeneration, OTP limits; registration and hardening tests | Verified sender, real TLS, persistent rate-limit cache |
| Buyer ↔ seller/rider/admin | Role escalation, another user's orders/addresses/messages | Exact role middleware, owner policies, actor checks, private file authorization; role/profile/chat/order/delivery tests | Repeat acceptance with real deployment accounts |
| Public catalog/API | Invalid input, inactive-shop leakage, expensive unbounded input | API filter validation, maximum search size, fixed pagination, public API throttling, active-shop visibility, stock aggregate | Observe load and tune rate limits from measurements |
| Checkout → stock/order database | Overselling, price manipulation, invalid transitions, demo gateway misuse | Server prices, row locks, transactional reserve/release, owner-bound addresses, transition guards, demo payments disabled in production | MySQL/concurrent-load rehearsal and real payment integration before offering non-COD payments |
| Rider handover | Wrong recipient/PIN, public evidence exposure | Buyer-visible encrypted PIN plus hash verification, actor/state checks, private photo route, COD confirmation; fulfillment tests | Stable APP_KEY and protected recovery copy; verify recipient process during beta |
| Uploads → storage | Executable or oversized files, private-document disclosure | Mime/size validation, generated names, private rider/proof storage and authorization; file/feedback tests | Persistent private volumes; protect object storage; restore files as well as DB |
| SMTP/invoice/Google/Firebase | Secret leakage, provider outage, stale updates | Server-side credentials, OAuth state and explicit consent, verified Firebase token checks, queued retries, invoice fallback, stale-notification checks | Separate staging/production credentials and actual provider smoke tests |
| Proxy → application | Spoofed scheme/client IP/host, cache leakage | Explicit proxy address list; forwarded host ignored; production host check; canonical HTTPS redirect; dynamic responses private/no-store | Hosting team must establish the real proxy chain and TLS termination |
| Container/build → runtime | Demo credentials, baked-in personal data, key regeneration | Production demo guards, no automatic seed, no startup key regeneration, restricted image context, locked installs | Review final image/mounts/secrets on selected host |
| Runtime → operators | Silent queue failure, sensitive logs, false readiness | Per-request UUID; named-route/status/timing logs; no bodies/query strings/PINs in operational log; DB and worker/scheduler readiness | Protect all logs, including Laravel exception logs; configure external alerting and on-call coverage |
| Backups → recovery | Unusable/corrupted backup, data or key loss | Private AES-256 ZIP snapshots, SHA-256 entries, SQLite integrity/row-count restore checks, isolated dummy rehearsal | Separate protected backup password and APP_KEY; encrypted off-site copies; provider-specific MySQL and cloud-object restoration |

## Validation and permission review

- Registration always creates a buyer; seller/rider roles follow admin approval.
- Profile updates allow only approved profile fields and require the current password; role or another user's ID cannot be posted to change ownership.
- Order/address views and modifications are owner-bound. Private evidence is restricted by actor role and assignment.
- Admin operational and privacy-request pages use the existing admin-only role boundary.
- Catalog API input is now validated before query construction, and inactive shops are excluded.
- Beta serviceability is checked again inside the order transaction, before stock is reserved. Unsupported or paused ordering leaves the cart and stock unchanged.
- Review and application mutations retain their existing replay/stale-update protections. Queue receipt retries remain at-least-once; SMTP can duplicate an email in the acceptance/crash window.

## Findings to revisit before go-live

The additional [account protection controls](../account-protection.md) require admin authenticator MFA, recent identity confirmation for admin writes, account suspension/session revocation, selected change auditing, and decoding/re-encoding of newly uploaded photos. Google reauthentication cannot substitute for the enrolled admin MFA gate. Operator MFA recovery requires independent identity verification; the application cannot perform that human check.

The audit trail covers model/service writes, not arbitrary database SQL. Photos are rewritten, while PDFs/videos are not malware-scanned. Existing media is not retroactively rewritten. These limits remain part of the hosting review.

1. Validate actual SQL concurrency and account/storage privileges on the chosen database.
2. Configure shared persistent sessions/cache for more than one app instance. An in-memory limiter is not sufficient across instances.
3. Verify every hosted TLS hop and cookie attributes in a browser; application header configuration is not proof of TLS.
4. Never enable `gateway_stub` for real purchases. Card/e-wallet support needs a real gateway and a separate review.
5. Keep private uploads off public mounts and never expose the repository root as the document root.
6. Review the latest dependency audit again for the exact release artifact; advisory results change over time.
