# FarSell 12-Week Delivery Workflow

## Team ownership

- **Person 1 - Technical Lead / Backend:** Laravel domain model, MySQL, authorization, APIs, transactions, queues, security, and deployment.
- **Person 2 - Frontend / Design Systems:** Blade, Tailwind, Alpine, Doorzo/Shopee visual language, accessibility, and responsive UI.
- **Person 3 - Mobile / QA / Operations:** API contracts, mobile client foundation, device testing, rider-flow QA, beta support, and release acceptance.

P0 items are launch blockers. P1 items are required for a useful beta. P2 items wait until the core transaction and delivery loop is proven.

| Week | Priority | Type | Work and acceptance evidence | Person in charge | Duration / deadline |
|---|---|---|---|---|---|
| 1 | P0 | Product / architecture | Confirm launch area, delivery radius, payment constraints, privacy baseline, ERD, API conventions, and definition of done. | Person 1 accountable; all contribute | 5 days - end of Week 1 |
| 1 | P0 | Foundation | Laravel repository, Sail/MySQL, Tailwind/Alpine, CI, formatting, test database, environment and secrets policy. | Person 1 | 4 days - end of Week 1 |
| 2 | P0 | Frontend | Responsive Doorzo/Shopee shell, guest entry, navigation, visual tokens, and accessibility baseline. | Person 2 | 5 days - end of Week 2 |
| 2 | P0 | Backend / QA | Roles, guest cart token, auth, addresses, policies, test matrix, browser/device coverage, and release checklist. | Person 1 / Person 3 | 5 days / 4 days - end of Week 2 |
| 3 | P0 | Catalogue | Seller, category, product, inventory, and media schema; catalogue, search, product and shop views. | Person 1 / Person 2 | 5 days each - end of Week 3 |
| 3 | P1 | QA | Inventory race, ownership, validation, and customer/seller journey tests. | Person 3 | 3 days - end of Week 3 |
| 4 | P0 | Commerce | Transactional cart, address, delivery quote interface, stock reservation, order state machine, checkout and confirmation UI. | Person 1 / Person 2 | 5 days each - end of Week 4 |
| 4 | P0 | QA / operations | Duplicate-submit, stock exhaustion, guest expiry, wrong address, and cancellation scenarios. | Person 3 | 4 days - end of Week 4 |
| 5 | P0 | Rider operations | Rider application, status, vehicle fields, protected documents, staff review queue, mobile-first registration UI. | Person 1 / Person 2 | 5 days each - end of Week 5 |
| 5 | P0 | QA | Rider role isolation, reapplication, data retention, form accessibility. | Person 3 | 4 days - end of Week 5 |
| 6 | P0 | Logistics | Delivery assignment, rider availability, append-only events, idempotent transitions, dispatch view, tabletop rehearsal. | Person 1 / Person 3 | 5 days / 3 days - end of Week 6 |
| 6 | P1 | Frontend | Rider job card, tracking timeline, seller fulfilment actions. | Person 2 | 5 days - end of Week 6 |
| 7 | P1 | Discovery / mobile | Indexed filters, serviceable areas, ETA contract; versioned API, mobile navigation plan, offline/error requirements. | Person 1 + 2 / Person 3 | 5 days each - end of Week 7 |
| 8 | P1 | Notifications / quality | Queue-backed notifications, regression suite, responsive and keyboard/accessibility hardening. | Person 1 / Person 2 / Person 3 | 4 / 4 / 5 days - end of Week 8 |
| 9 | P0 | Security / readiness | TLS, Cloudflare WAF/rate limits, backups, observability, threat model, permissions audit, rollback rehearsal. | Person 1 + Person 3 | 5 days - end of Week 9 |
| 10 | P0 | Beta | Controlled one-area seller/customer/rider beta; daily issue triage. Fix only observed journey blockers. | Person 3 accountable; all support | 5 days - end of Week 10 |
| 11 | P0 | Stabilization | Resolve P0/P1 defects, performance budget, migration restore, support/admin runbooks. | Person 1 + Person 3 | 5 days - end of Week 11 |
| 11 | P1 | Mobile handoff | Mobile API sample, screen contracts, device-test results, rider pilot backlog. | Person 3 | 4 days - end of Week 11 |
| 12 | P0 | Launch | Acceptance test, release notes, production configuration, on-call roster, go/no-go, gradual launch, monitoring, retrospective. | Person 1 accountable; all approve | 5 days - end of Week 12 |

## Milestone gates

1. **End of Week 4:** a guest discovers a real product, adds it to a cart, enters an address, creates a test order, and sees a tracked confirmation.
2. **End of Week 6:** a staff user approves a rider; an approved rider can receive and progress an assigned delivery in the state machine.
3. **End of Week 8:** the web app is regression-tested; the API contract is ready for the Capacitor client.
4. **End of Week 12:** the P0 transaction-and-delivery loop has passed acceptance, rollback, and production-readiness gates.
