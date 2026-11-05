# P1 Weeks 9–12: audit and implementation

Checked on 2026-11-03. Cloudflare setup is reserved for the team. Existing beta complaints were reported resolved; no new feature was invented from those complaints.

## Audit baseline

The pre-change baseline passed 239 backend tests (1,952 assertions), 9 frontend tests, and the asset build. Passing tests did not cover every API input or deployment configuration.

Confirmed issues:

- The public catalog API returned an active product belonging to an inactive shop. An array `q` caused HTTP 500 and invalid price text was accepted.
- Login used the custom controller, bypassing the unused scaffold `LoginRequest` limiter.
- The app trusted every forwarding proxy. Many personalized pages lacked an explicit shared-cache prohibition.
- Production image startup automatically seeded demo accounts with a known development password. The development container regenerated APP_KEY on each restart, threatening sessions and encrypted delivery PINs.
- The image build context did not exclude local databases and private uploads.
- Shop and related-product card rendering could load category/shop/save records separately per card.
- A rollback rehearsal exposed an indexed-column rollback failure in the shops city migration; its down migration now drops the index before the column.
- Dependency audits reported seven PHP advisories affecting four packages and six npm package findings. Minimal compatible updates removed the reported advisories; this does not prove every advisory was reachable through FarSell.

## Week-by-week result

| Week / P1 responsibility | Existing coverage | Added after checking | Still needs the team/hosting |
|---|---|---|---|
| 9 — threat model, permissions, validation, release readiness | Role/ownership tests, hashed passwords, OTP, CSRF, private delivery evidence, privacy settings | Auth throttles, explicit proxy configuration, canonical production host/HTTPS checks, response safeguards, API validation/visibility fixes, demo seed guards, stable-key startup, clean build context, dependency fixes, recovery tools, threat-model and release checks | Cloudflare work; actual TLS/origin verification; database/storage/backup provider selection |
| 10 — feedback fixes and support for P3's controlled beta | Buyer/seller/rider flow, city filtering, barangay rider assignment; reported complaints resolved | Checkout pause switch, regional beta checks for pickup AND delivery cities, fail-closed empty beta list, production demo-payment restriction, issue/escalation procedure | Select pilot cities, users and dates; conduct the regional beta; record real feedback |
| 11 — performance and runbook hardening, paired with P3 | Pagination, product indexes, eager loading on major listing paths, transactional stock | Shop/related-card query fixes and regression budget, bundle budgets, sanitized request timing/IDs, worker/scheduler health checks, encrypted SQLite snapshots and isolated restore checks, migration/recovery rehearsal, support/recovery runbooks | Hosted load tests, database-specific restore drill (especially MySQL), cloud object backups, actual performance measurements under concurrent traffic |
| 12 — launch acceptance, observation, production go/no-go | Automated commerce/rider tests, notification monitor, application boot health | Technical release gate, readiness endpoint, admin Operations page, CI verification, production environment reference, staged-release/rollback/incident/acceptance templates | Actual production deployment, trusted HTTPS, provider verification, P2/P3 approvals, named on-call coverage, real observation/retrospective |

These additions prepare the repository. They do not declare a production launch completed or authorize one. `release:check` is a technical gate, not a substitute for the acceptance record.

## Scope boundaries

- No Cloudflare DNS, proxy, WAF, cache rules, or account settings were changed.
- No live release, migrations against a hosted production database, real email dispatch, or external team messages were performed.
- The web workflows remain the current scope; mobile GPS, push notifications, routing, and scanning remain deferred.
- Local security reports can be deleted independently of these app features. Recovery commands do not depend on the Topic 9 evidence folder.

Continue with [the operating runbook](runbook.md), [threat model](threat-model.md), [local verification results](verification.md), and [launch acceptance](launch-acceptance.md).
