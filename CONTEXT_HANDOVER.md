# FarSell / PFS Context Handover

## Project workflow

- Karl / Player 1 owns backend work: Laravel, PHP, MySQL, Eloquent, and transactions.
- Jopher / Player 2 owns frontend: Blade, Tailwind, Alpine, and PWA work.
- Kyle / Player 3 owns integration, QA, and DevOps.
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

## September 18 — local P2 theme integration (not committed or pushed)

- Integrated P2 commit `5852445` selectively: violet design tokens, theme controls, welcome/auth styling, category navigation, mobile menu, footer, and related page styling.
- Preserved the local read-only `/admin` dashboard, role restrictions, admin-only links, and `admin` login alias for `admin@farsell.test`. The alias requires the real password and admin role; default admin login redirects to the dashboard.
- Kept My Orders within Profile rather than adding direct order links to the header/mobile menu/footer. Existing buyer order ownership checks and the standalone PSGC cascade remain intact.
- Fixed P2's escaped checkout content section. Theme controls now share reactive state, tolerate unavailable storage, and synchronize saved/system preferences. Marketplace and admin surfaces use theme-aware colours.
- Preserved the Week 4 backend record above and corrected destructive reset guidance in README.
- Excluded P2's reintroduced `.env.local`; neither local environment file was edited. Remote main still tracks it at `5852445`: remove it from the final tracked tree when a commit/push is authorized. Never print or commit its contents; rotate any real exposed secrets.
- Git HEAD has not been advanced: this is a local integration with existing uncommitted admin work preserved. Reconcile with remote main when committing; do not blindly pull over this work.
- Automated verification: 54 PHP tests / 248 assertions, 9 JavaScript tests, and Vite build passed. Browser visual verification remains pending because no browser surface was available.
- Next: manually check both themes on desktop/mobile and the buyer/admin flows, then prepare a commit only when Karl asks. Admin editing/approvals, real payments, and delivery dispatch remain out of scope.

## Follow-up — checkout sign-in and Shops fixes (local only)

- Guest checkout now opens a checkout-specific login prompt with a primary login action and secondary Create an account action. Continue as guest is hidden on that prompt; regular guest browsing remains available elsewhere.
- Login and registration preserve the session cart and resume checkout. New buyers without an address are sent to address creation and returned to checkout after saving it. Normal registration/rider onboarding and normal address management keep their existing destinations.
- Explicit guest browsing clears the protected intended destination instead of looping back to checkout.
- Added public `/shops` directory with active shops, active-product counts, alphabetical pagination, empty states, and links to each shop. Desktop/mobile/footer Shops navigation now targets it; inactive shops remain inaccessible.
- Verification: 66 PHP tests / 339 assertions and production Vite build passed. No business records, environment files, commits, or pushes were changed by this implementation. Browser walkthrough remains pending.

## Seller Dashboard — first read-only version (local only)

- Added `/seller`, available through the account menu and Profile. Requires seller/admin role and scopes all data to the signed-in user's own shop, including administrators.
- Shows product/active/low-stock counts, paginated inventory with price and stock, and independently paginated order-item snapshots with order number/date/status and item totals.
- Mixed-seller orders are scoped at the item level. Buyer names, email, phone, address, delivery fees, full order totals, and other sellers' items are not loaded/displayed. Existing whole-order ownership policy is unchanged.
- Empty/missing/inactive shops have explicit states. No product editing, stock mutation, shop creation, or fulfillment actions were added; no migrations or live database changes were needed.
- Historical limitation: item ownership is inferred from the current linked product. Deleted-product items are omitted, and future product transfer/deletion workflows need immutable shop ownership snapshots before use. Totals include cancelled/demo items and are not revenue.
- Verification: 73 PHP tests / 398 assertions and Vite build passed. Visual browser walkthrough still pending. Test the seeded seller account (`seller@farsell.test`, demo password `password`) at `/seller`.
- Stop here for review. Rider Dashboard/dispatch and payment/invoice work have not been started. Nothing committed or pushed.

## Rider Dashboard — September 19 (local only)

- Added `/rider/dashboard`, with application status/onboarding states for signed-in applicants and read-only assigned/in-transit deliveries for approved rider-role accounts (admins are likewise restricted to their own approved rider profile).
- Orders are scoped by `rider_profiles.id`, not the user's ID or query parameters. Only active assignment number/status/date and checkout delivery address/contact snapshot are loaded. Completed deliveries contribute a count only; cancelled/completed/unassigned records do not disclose delivery addresses. Full buyer order pages remain protected by their existing ownership policy.
- Added shared reactive-theme styling, oldest-order-first pagination, active-status filtering, empty states, and links from navigation, account profile, and rider profile. Actual rider-role accounts have rider-specific profile shortcuts rather than buyer order/address shortcuts; applicants keep their buyer access while pending.
- No approval, dispatch, assignment/status mutation, route optimization, GPS tracking, or collection/payment actions were added. No account roles or live assignments were changed.
- Read-only local check: `rider@farsell.test` currently exists with buyer role and no rider application. It will show onboarding at the dashboard until application/role approval is completed; do not silently promote it.
- Verification: 84 PHP tests / 521 assertions and Vite build passed. Browser visual walkthrough remains pending. Nothing committed or pushed.

## Rider admin approvals — September 19 (local only)

- Admin Dashboard now links to `/admin/riders`: paginated status-filtered application queue, detail review, and authenticated admin-only private document downloads. Pending applications can be approved or rejected. Review requires confirmation; rejection requires a note visible on the applicant's rider profile.
- Approval changes the account role to `rider` and profile to `approved` in one transaction. Records latest reviewer, review timestamp, and note. Seller/admin roles cannot be overwritten. Duplicate/non-pending decisions and stale pages after applicant edits/uploads are rejected. Applicant submissions lock the same account/profile; approved/suspended profiles cannot be reset through onboarding. Rejected applicants may resubmit without receiving rider access.
- Removed Seller Dashboard shortcuts from admin desktop/mobile dropdowns and account profile. Seller shortcuts and existing backend admin permissions are unchanged.
- Added and applied local migration `2026_09_19_000001_add_review_fields_to_rider_profiles`. Existing accounts/applications were not approved, promoted, deleted, or seeded; environment files untouched.
- MVP boundary: uploads are still optional and approval does not mark individual documents verified. Review metadata stores the latest decision only and is cleared on resubmission; no permanent review history, seller approvals, suspension controls, dispatch, or fulfillment actions yet.
- Verification: 96 PHP tests / 665 assertions, 9 frontend tests, Vite build, and diff whitespace check passed. Browser visual walkthrough remains pending. Nothing committed or pushed.
- **Next: manual walkthrough** — submit the rider application while signed in as the applicant, then sign in as admin and review/approve it via Admin Dashboard. Refresh the rider account to confirm role-specific profile and delivery dashboard. Delivery assignment/dispatch is the subsequent backend task.

## Profile CRUD — September 19 (local only)

- Added **My Profile → Edit profile & security** at `/account/profile/edit`, with separate details, password, and account-deletion forms. Registration continues to handle creation and existing profile/purchase-history pages handle reading.
- All roles can edit their own name/email/optional phone after current-password confirmation. Explicit field allowlist prevents role/approval/password injection and submitted IDs never select another account. Email uniqueness is case-insensitive; changed email clears verification and the previous email's reset token. Saved addresses, rider review state, and order snapshots remain unchanged.
- Password changes validate the current password, confirmation, minimum length, and difference from the current password. Passwords use the model's hash cast. Rotate remember token, remove reset tokens and own database sessions, invalidate current session/cart, then return to login. Enabled Laravel's web session-password authentication middleware to reject stale sessions.
- Self-deletion requires current password plus exact `DELETE` confirmation. Only buyer accounts with no orders, shop, or rider application can self-delete. Other accounts are blocked server-side with admin-assisted handling guidance to avoid destructive cascades and lost history. Eligible deletion removes the account and saved addresses permanently; another user's records are never targeted. Transactions and locked rechecks protect updates/deletion; sensitive routes are rate-limited and forms use CSRF/method spoofing.
- Added 17 feature tests covering role isolation, validation/error rendering, password hashing/login/session revocation, deletion ownership/dependencies/rollback, and rate limiting. Final verification: **113 PHP tests / 913 assertions, 9 frontend tests, Vite build, Pint and diff whitespace checks passed**. Browser visual walkthrough is still pending.
- No migrations, live account mutations/deletions, environment changes, commits, or pushes in this task. Scope excludes avatars, email-verification delivery, forgotten-password recovery, and vouchers.
- **Next:** manually test profile editing and password changes with a demo account; test irreversible deletion only with a disposable buyer account without marketplace history. Rider approval walkthrough and subsequent dispatch work remain next in the larger feature sequence.
