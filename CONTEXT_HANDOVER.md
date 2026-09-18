# FarSell / PFS Context Handover

## Project workflow

- Karl / Player 1 owns backend work: Laravel, PHP, MySQL, Eloquent, and transactions.
- Jopher / Player 2 owns frontend: Blade, Tailwind, Alpine, and PWA work.
- Kyle / Player 3 owns integration, QA, and DevOps.
- The team is currently syncing work through Google Drive. Do not commit or push unless Karl explicitly asks.

## Current backend status

### Week 3 — complete (P1)

- Product variants and product images added: migrations, models, factories, relationships, and demo seed data.
- Guests can browse the catalogue and use the cart, but checkout and order pages now require authentication.
- Guest-specific checkout fields and order access logic were removed.
- Addresses use local PSGC data with a Region → Province → City/Municipality → Barangay cascade.
- PSGC reference data is stored under `database/data/psgc` and seeded into local database tables; no external API token is required.
- Checkout retains transaction handling and row-level stock locking.
- Verification: 23 passing feature tests, 51 assertions.

## Next task — P1

> **Checkout scenario and edge-case QA / order state work**

Focus on checkout failure paths and order lifecycle behavior: insufficient stock, concurrent checkout attempts, payment-method transitions, authorization for order viewing, cart cleanup, and confirmation-page integration with Player 2.

## Later backend work

- Delivery assignment and basic seller/rider tracking.
- Invoice generation.
- Payment gateway/card logic.
- Optional Google/Gmail/phone authentication, pending team confirmation.
