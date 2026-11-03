# FarSell operating and recovery runbook

This runbook is for the existing Laravel web app. Production actions below are instructions for the selected hosting environment, not actions already performed. Cloudflare setup remains a separate team task.

## Commands and where to check

| Action | Command / page |
|---|---|
| Local app plus worker/scheduler | `composer dev` |
| Database/background-process status | `php artisan ops:status --json` |
| Boot configuration gate (no workers required yet) | `php artisan release:check --boot` |
| Technical go/no-go gate | `php artisan release:check --json` |
| Backend operational review | Admin dashboard → Operations |
| Email failures and retries | Admin → Notifications |
| Boot health / actual readiness | `/up` / `/ready` |
| Safe recovery rehearsal, dummy database only | `php artisan ops:rehearse` |
| Encrypted SQLite snapshot | `php artisan ops:backup --include-files` |
| Isolated SQLite restore check | `php artisan ops:restore-check ops-YYYYMMDD-HHMMSS-UUID.zip` |
| Production admin provisioning | `php artisan ops:create-admin operator@YOUR-DOMAIN --name="Operator Name" --confirm-production` |

`ops:create-admin` asks for and confirms a hidden strong password. Do not put passwords in command arguments. Do not use demo seeders to provision a production admin. Review the target connection before running it.

The Operations page does not display credentials, addresses, PINs, job payloads, or exception bodies. The public readiness response contains only `ready` or `unavailable`. Worker/scheduler freshness is required in production; local settings can show them unavailable without making development unusable.

## Separate environments

Use separate databases, persistent private storage, session-cookie names, mail senders, Google/Firebase clients, invoice credentials, queues, and backup destinations for local, staging, and production. Never copy a developer's database into production to make release checks green.

Use `deploy/production.env.example` as a reference, fill secrets in the hosting service, and leave filled files out of Git. Production requires `APP_DEBUG=false`, a stable APP_KEY, a real HTTPS URL, secure session cookies, `ALLOW_DEMO_PAYMENTS=false`, and `REQUIRE_OPERATIONAL_HEARTBEATS=true`. `TRUSTED_PROXIES` contains only the actual load balancer's IP/CIDR addresses; it is empty on a direct HTTPS origin. `ALLOWED_HOSTS` adds exact hosting health-check hostnames only when needed. Forwarded host headers are not trusted.

Production image startup checks boot configuration, caches configuration/routes/views, preserves the key, and does not seed demo accounts. Migrations are opt-in with `RUN_MIGRATIONS=true`; prefer a controlled one-off migration step after taking a backup. The development container now seeds only when `SEED_DEMO_DATA=true` and keeps an existing key. A root document path must always be `public/`.

For SQLite, provision a persistent database file outside the disposable image and set `DB_DATABASE` to its absolute path. For MySQL, use a separate application user with the least required runtime privileges; apply migrations with the deployment identity. Do not expose the database publicly. The current root production image includes both PDO drivers, but a real MySQL acceptance run remains necessary before choosing that deployment mode.

## Controlled regional beta and staged release

1. P3 coordinates actual pilot participants and the issue log. P1/P2 support the backend/frontend checks.
2. Choose PSGC city IDs from the existing geography database; set `BETA_CITY_IDS` and then `BETA_ENABLED=true`. Both buyer delivery city and seller pickup city must be in the list. Missing city mappings or an empty list fail closed.
3. Start with `CHECKOUT_ENABLED=false`. Browsing and existing deliveries remain available while new checkout is paused.
4. Deploy to staging, run the technical checks, complete the acceptance matrix, then obtain team approval before opening a pilot.
5. Enable checkout for the agreed pilot and observe one complete real authorized test journey. Increase the supported cities/participants only after reviewing actual error and delivery results.
6. If checkout is faulty, set `CHECKOUT_ENABLED=false`, refresh configuration, and restart long-lived workers. Preserve existing orders, stock, logs, and customer support records.

Environment-file changes require `php artisan config:cache` and worker restart. They do not instantly update an already-cached deployment. This is an operator-controlled rollout, not percentage-based traffic splitting. Non-COD purchases require a separately integrated real gateway; the demo gateway is blocked and hidden when disabled.

## Background processes and observation

Run the notification worker under the hosting process supervisor, not in a developer terminal:

```text
php artisan notifications:work
```

Configure the host scheduler to run `php artisan schedule:run` every minute (or supervise `schedule:work` on hosts that support a long-lived scheduler). Do not run multiple unsynchronized schedulers across instances. The schedule records `ops.scheduler.last_seen` and queues a unique heartbeat onto the real notification queue. Only a worker processing that job records its heartbeat. Heartbeats become unhealthy after 180 seconds by default.

Point the external uptime monitor at `/ready` after the workers are initialized; `/up` only confirms application boot. Readiness also reports unhealthy when failed jobs, failed notifications, or notifications stale for more than five minutes are present. Startup hosts may use `/up` during initialization to avoid a deadlock while the scheduler is starting.

`storage/logs/operations-*.log` records server-generated request IDs, release revision, route name, method, status and duration for slow requests, server errors, and checkout/rider/fulfillment POST requests. It omits bodies, query strings, passwords, PINs, and delivery addresses. General Laravel exception logs can contain more detail: restrict their access and retention. Configure the selected host's external alerting/log collection separately; no SMS, Slack, or production alerts have been connected here.

Local performance budgets: 1,000 ms marks a slow request; built JS is limited to 80 KiB gzip per entry and CSS to 40 KiB. Shop-card query regression ensures listing more products does not add a separate relation/save query per card. These are regression safeguards, not a hosted throughput claim. Measure p50/p95, error rate, memory, database locks, checkout success, and worker delay on the chosen server under realistic concurrent load before accepting final budgets.

## Backup and isolated restore: SQLite

1. Store a unique backup password of at least 20 characters in `BACKUP_PASSWORD`, in the secret manager and a separate protected recovery record. Keep APP_KEY and provider credentials in that protected record as well; they are not put in the archive.
2. For a coordinated database/files recovery point, pause new checkout, enter maintenance, stop upload-producing writes and workers, and record the release revision. A live database snapshot is transactionally consistent, but unrelated filesystem changes are not automatically atomic with it.
3. Run `ops:backup --include-files`. The command uses SQLite `VACUUM INTO`, packages a manifest, and encrypts entries with ZIP AES-256 in private `storage/app/private/ops-backups`. It includes local rider documents, delivery proof, review media, and public uploads. It does not capture cloud/R2 objects, environment files, APP_KEY, or a running server image. ZIP entry names are visible even though contents are encrypted; keep the archive protected.
4. Run `ops:restore-check` with the generated basename. It decrypts into a temporary private directory, verifies every entry's SHA-256, checks database integrity and table row counts, then removes temporary plaintext. It never replaces the current database or uploads. Successful verification records a timestamp for the technical gate.
5. Copy the encrypted archive to the selected off-site protected destination, verify retrieval, and apply the team's agreed retention. Off-site scheduling and cloud-storage backups require the eventual hosting setup; they are not configured by this command.
6. To recover for real, first preserve the current failed state, restore to a new isolated target, supply the original APP_KEY, verify private files and application journeys, then switch the application to the restored target during a maintenance window. Do not overwrite the live database as a diagnostic step.

The dummy `ops:rehearse` command creates its own SQLite file, applies migrations, inserts a dummy account, rolls back/reapplies the latest migration, creates an encrypted snapshot, and validates an isolated restore. It restores the original connection and removes its temporary artifacts. It deliberately refuses production/staging, and it is not proof that the future production database/provider backup was tested.

## MySQL and cloud storage recovery

The built-in SQLite snapshot command intentionally refuses MySQL. Before a MySQL launch, configure provider snapshots/PITR and rehearse restoration to a separate MySQL database. Where logical backups are used, use the MySQL version's documented `mysqldump --single-transaction --quick` procedure, a restricted credentials option file (not password arguments), encrypted output, and protected off-site storage. DDL and non-transactional tables require additional consistency controls; consult the [MySQL backup documentation](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html). Confirm schema versions, row counts, stock totals, encrypted PIN access, uploads and authorized checkout/delivery against the restored target. Record the result in the acceptance sheet. Do not claim a SQLite rehearsal as MySQL evidence.

Cloud object recovery requires a separate versioning/backup/restore procedure with the team that owns storage. No Cloudflare settings are changed here.

## Release, rollback, and incident handling

Before release: inspect CI results and advisory audits for the exact lockfiles; record the commit; take and verify recovery points; verify staging and hosted providers; fill `launch-acceptance.md`; get approvals. Release migration changes should be backward compatible with the previous app version where possible.

For a regression: pause checkout when needed, record request ID/release/time, preserve logs and data, stop widening the beta, and roll the application back to the previously recorded artifact. Do not blindly run `migrate:rollback` on customer data. Determine whether the previous app can use the current schema; otherwise rehearse recovery on an isolated restored database before a maintenance-window switch. Preserve the failed database and never reseed over orders.

| Severity | Examples | Response |
|---|---|---|
| P0 | Unauthorized access, leaked private data, wrong stock/payment state, widespread failed checkout | Pause affected writes immediately, notify the agreed incident owner, preserve evidence and secrets, investigate/contain; do not continue rollout |
| P1 | Many riders cannot update deliveries, stale queue, invoice provider outage without usable fallback | Halt expansion; P1/backend and P3/QA investigate, P2 handles affected UI; use the runbook and approved rollback |
| P2 | Isolated non-blocking usability issue | Capture reproduction and evidence; prioritize from actual impact |

Keep the incident log factual: incident ID, timezone, affected revision, severity, request IDs (no passwords/PINs), affected flow, containment, owner, decision, recovery evidence, and follow-up. Names, contact channels and on-call hours must be agreed by the team before production. After the observation window, review actual checkout/rider errors and record a short retrospective with corrective actions.

GitHub verification uses the documented [PHP setup action](https://github.com/shivammathur/setup-php) and [Node setup action](https://github.com/actions/setup-node). It checks SQLite automatically; it does not provision or certify a production server.
