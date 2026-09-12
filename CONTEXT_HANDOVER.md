# FarSell / PFS Context Handover

## Project workflow

- Karl / Player 1 owns backend work: Laravel, PHP, MySQL, Eloquent, and transactions.
- Player 2 owns frontend: Blade, Tailwind, Alpine, and PWA work.
- Player 3 owns integration, QA, and DevOps.
- The team is currently syncing work through Google Drive. Do not commit or push unless Karl explicitly asks.
- On September 12, Karl authorized merging the local Week 3–4 work and the previously assimilated P2 welcome portal into GitHub main.

## Current backend status

### Week 3 — complete (P1)

- Product variants and product images added: migrations, models, factories, relationships, and demo seed data.
- Guests can browse the catalogue and use the cart, but checkout and order pages now require authentication.
- Guest-specific checkout fields and order access logic were removed.
- Addresses use local PSGC data with a Region → Province → City/Municipality → Barangay cascade.
- PSGC reference data is stored under `database/data/psgc` and seeded into local database tables; no external API token is required.
- Checkout retains transaction handling and row-level stock locking.
- Verification: 23 passing feature tests, 51 assertions.

### Week 4 — complete (P1 backend foundation)

- Checkout requires a saved address owned by the signed-in buyer and snapshots its delivery details on the order.
- Added a configurable delivery-fee service contract; the current implementation uses the `DELIVERY_FEE` flat rate.
- Added explicit allowed order-status transitions through a transactional state-machine service.
- Added a stock-reservation policy: lock and validate current product rows, use authoritative prices, reserve stock in the order transaction, and release it once on cancellation.
- Cart quantity changes reject amounts above available stock; checkout remains the authoritative final check.
- Cart data clears only after the order transaction succeeds.
- Verification: 31 passing tests, 81 assertions.

## Next task — P1

> **Integrate the checkout and confirmation UI with Player 2**

Connect Player 2's final UI to saved-address selection, delivery-fee presentation, checkout errors, and the order confirmation/status display. After integration, continue with rider role isolation and onboarding work.

## Later backend work

- Delivery assignment and basic seller/rider tracking.
- Invoice generation.
- Payment gateway/card logic.
- Optional Google/Gmail/phone authentication, pending team confirmation.

## Week 3–4 merge handoff

- Included P2 integration: welcome portal with login/register tabs, named validation error bags, marketplace `/home` routing, and Vite HMR fixes (commit `6f7f4fb`).
- The separate remote `test/test-newfeat` branch is not part of this integration; it needs a separate review.
- Verification before merge: 31 tests / 81 assertions on SQLite, and a successful Vite production build. These tests do not verify simultaneous MySQL transactions or full browser interaction.
- Existing scope limits: purchases still use product-level stock rather than variants; the gateway is a demo stub; the order transition service has no admin/rider action endpoints yet. COD payment and fulfillment handling needs further integration before live use.
- Local standalone setup after pulling: install Composer dependencies, run `php artisan migrate --env=local`, then `php artisan db:seed --class=PsgcGeographySeeder --env=local`. This imports reference geography without resetting business data.
- Docker setup: run migrations and the PSGC seeder inside the app container using its Docker environment. Do not overwrite `.env` with local MySQL settings.
- Frontend setup: install npm dependencies including dev dependencies, then run `npm run build` or keep `npm run dev` running. If the npm launcher fails on Karl's host, `node node_modules/vite/bin/vite.js build` works with dependencies already installed.
