# FarSell development workspace and task plan

Revised 5 November 2026. Board-ready proposal based on the teacher brief, rubric and available repository evidence. No external board has been edited.

## Scope and status baseline

Cover Superadmin, Buyer, Seller, Logistics, Rider, Flutter Buyer/Rider mobile app, Laravel API, database, security, testing, deployment and presentation. The current role enum has buyer, seller, rider and admin. Admin maps to the planned Superadmin role; a separate restricted Logistics role is a planned gap. No Flutter project was located in this checkout.

Invoices, notifications, returns/reimbursement recording, seller reports and account security already have implementation evidence. They are included as historical finished tasks, correcting the earlier short task list. Historical Done does not mean a fresh acceptance run occurred today. P05 audits those claims; unsupported cards must move to In Review. Completed-card owners are proposed accountable owners based on team responsibilities, not verified authorship credits. Confirm ownership and contributions in P05.

Proposed academic release uses COD and in-app notifications. Live gateway capture, automatic refunds/payouts and background push are outside this baseline. P01 must confirm teacher acceptance of these exclusions; if required, add separately estimated and scheduled tasks before approval. Existing reimbursement recording must not be represented as actual money transfer.

## Responsibility and workload

Proposed lead: Karl Benedict Boongaling, confirmed by the team in P02. Karl owns backend, database, API, security and technical handoff. Mark Jopher Domanico owns web UI, UI/UX, Flutter and user guides. Frances Kyle Mary Villamor owns planning coordination, integration, QA, deployment/recovery and demonstration evidence. Confirm Flutter capability during P01; this is a proposed mobile assignment.

Each card has one accountable owner. Kyle verifies feature acceptance; Karl reviews backend/security and Jopher reviews UI. Karl or Jopher reviews Kyle-owned deliverables. Estimates include implementation and focused self-checks; dedicated QA and final gates are separately scheduled.

- **Karl Benedict Boongaling: 84 proposed hours**, at 10 focused hours/week (2 hours per weekday).
- **Mark Jopher Domanico: 89 proposed hours**, at 10 focused hours/week (2 hours per weekday).
- **Frances Kyle Mary Villamor: 99 proposed hours**, at 10 focused hours/week (2 hours per weekday).

Proposed start: 2026-11-09. Calculated finish: 2027-02-01. Dates follow named-owner capacity and predecessor completion. They are proposed dates, not the teacher deadline. Holidays, exams and leave are not yet deducted; confirm the school calendar and reserve two additional teaching weeks after the finish for rework. If the actual class deadline is earlier, change agreed capacity/scope rather than silently compressing estimates. Historical effort values are retrospective estimates, not timesheets; unknown historical dates remain blank.

## System module map

Web role screens -> Laravel web controllers + authorization + shared services -> database and public/private file storage. Flutter Buyer/Rider screens -> versioned Laravel API -> the same authorization, checkout and shipment services -> the same database. Queue/scheduler handles events and dispatch retries. Private evidence downloads require authorization.

- Superadmin: dashboard, users/buyers, seller/rider approvals, Logistics permissions, products/categories, review moderation, order monitoring, reports and settings.
- Buyer: registration/login, browsing/search/filters, product details, cart, saved addresses, COD checkout, documents, history/tracking, reviews, returns and notifications.
- Seller: application, store profile, products/variants, inventory, fulfillment, sales reports and promotions.
- Logistics: separate restricted operations account, shipment queue, rider assignment, monitoring and delivery reports.
- Rider: approved login, availability/offers, assigned details, pickup, delivery proof/PIN, history and recorded earnings.
- Flutter: buyer authentication/catalogue/cart/addresses/checkout/orders/inbox; rider authentication/availability/offers/details/pickup/proof. Include empty, loading, validation, offline and expired-session states.
- API/database: resources, token lifecycle, ownership rules, migrations/relations, transactions, stock locks, order snapshots and evidence permissions.

## Board setup and definition of done

Use Backlog -> To Do -> In Progress -> In Review -> Testing -> Done. If the pictured workspace supports only To Do / In Progress / In Review / Done, use a Testing label under In Review. Record Blocked plus predecessor IDs in Details. Overdue is a deadline condition, not a completion status.

Map CSV Title to What needs doing?, Details to Details, Assignee to Who is doing it?, and Due to Deadline. Proposed deadline time: 5:00 PM Asia/Manila. Keep module, feature, priority, estimate, dependencies, subtasks and acceptance in Details if dedicated fields are unavailable. The CSV is a source/mapping file; import support depends on the platform.

A card is Done after its specific acceptance passes, a peer reviews it, relevant behavior tests pass, evidence is attached and no blocking defect remains. Use isolated demo data. Historical D cards retain Done to include finished work, with evidence audit assigned to P05; do not fabricate historical dates or fresh verification. New work starts as To Do.

## Milestones and dependency sequence

- **M01: Baseline and evidence** | 2026-11-09 to 2026-11-17 | Cards P01, P02, P03, P04, P05. Exit: all listed cards accepted with evidence.
- **M02: Existing web journey review** | 2026-11-18 to 2026-11-30 | Cards P06, P07, P08. Exit: all listed cards accepted with evidence.
- **M03: Five-role access and Superadmin** | 2026-11-13 to 2026-12-07 | Cards P09, P10, P11, P12, P13, P14, P15, P16, P17. Exit: all listed cards accepted with evidence.
- **M04: Seller profile and promotions** | 2026-11-23 to 2026-12-09 | Cards P18, P19, P20, P21. Exit: all listed cards accepted with evidence.
- **M05: Logistics workspace and reports** | 2026-11-26 to 2026-12-09 | Cards P22, P23, P24, P25, P26. Exit: all listed cards accepted with evidence.
- **M06: Dispatch and rider reliability** | 2026-12-09 to 2026-12-17 | Cards P27, P28, P29. Exit: all listed cards accepted with evidence.
- **M07: API and Flutter foundation** | 2026-12-03 to 2026-12-16 | Cards P30, P31, P32, P33. Exit: all listed cards accepted with evidence.
- **M08: Mobile catalogue and cart** | 2026-12-10 to 2026-12-23 | Cards P34, P35, P36, P37. Exit: all listed cards accepted with evidence.
- **M09: Mobile checkout and orders** | 2026-12-17 to 2026-12-30 | Cards P38, P39, P40, P41. Exit: all listed cards accepted with evidence.
- **M10: Mobile rider and notifications** | 2026-12-16 to 2027-01-08 | Cards P42, P43, P44, P45, P46, P47. Exit: all listed cards accepted with evidence.
- **M11: Integration and staging acceptance** | 2026-12-18 to 2027-01-26 | Cards P48, P49, P50, P51, P52, P53, P54, P55, P56. Exit: all listed cards accepted with evidence.
- **M12: Documentation and final submission** | 2027-01-13 to 2027-02-01 | Cards P57, P58, P59, P60. Exit: all listed cards accepted with evidence.

Milestones overlap when members can work independently. Dependency IDs on cards determine sequence; successors start on a working day after all predecessors finish. Key chain: scope/role design -> API contract -> token/cart/checkout -> mobile orders -> device/API acceptance -> staging/recovery/security -> final rehearsal -> evidence pack.

## Board-ready task entries

### D01 - Implement buyer registration and session login

- **Hierarchy:** FarSell > Shared > Authentication > Task D01 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 6 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** None
- **Subtasks:**
  - Implement buyer registration and session login.
  - Verify and attach evidence: Valid registration and login succeed; guests cannot check out; cart survives sign-in.
- **Acceptance criteria:** Valid registration and login succeed; guests cannot check out; cart survives sign-in.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D02 - Create product, variant and image relationships

- **Hierarchy:** FarSell > Database > Catalogue > Task D02 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 6 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** None
- **Subtasks:**
  - Create product, variant and image relationships.
  - Verify and attach evidence: Products load their variant and image records with valid relationships.
- **Acceptance criteria:** Products load their variant and image records with valid relationships.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D03 - Implement saved addresses and PSGC cascade

- **Hierarchy:** FarSell > Buyer > Addresses > Task D03 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D01
- **Subtasks:**
  - Implement saved addresses and PSGC cascade.
  - Verify and attach evidence: Location selections cascade correctly; another buyer's saved address is rejected.
- **Acceptance criteria:** Location selections cascade correctly; another buyer's saved address is rejected.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D04 - Implement add, quantity-update and remove actions

- **Hierarchy:** FarSell > Buyer > Cart > Task D04 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D02
- **Subtasks:**
  - Implement add, quantity-update and remove actions.
  - Verify and attach evidence: Totals update correctly; quantities above available stock are rejected.
- **Acceptance criteria:** Totals update correctly; quantities above available stock are rejected.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D05 - Implement transactional checkout and stock reservation

- **Hierarchy:** FarSell > Buyer > Checkout > Task D05 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 8 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D03,D04
- **Subtasks:**
  - Implement transactional checkout and stock reservation.
  - Verify and attach evidence: Order and stock changes commit together; failure preserves cart; stock cannot be released twice.
- **Acceptance criteria:** Order and stock changes commit together; failure preserves cart; stock cannot be released twice.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D06 - Implement shared themes and responsive navigation

- **Hierarchy:** FarSell > Web UI > Theme > Task D06 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** Done | **Priority:** Normal | **Estimate:** 6 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** None
- **Subtasks:**
  - Implement shared themes and responsive navigation.
  - Verify and attach evidence: Saved theme persists and menus remain usable in both themes at mobile width.
- **Acceptance criteria:** Saved theme persists and menus remain usable in both themes at mobile width.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D07 - Build login and registration portal screens

- **Hierarchy:** FarSell > Web UI > Authentication > Task D07 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** Done | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D01,D06
- **Subtasks:**
  - Build login and registration portal screens.
  - Verify and attach evidence: Errors appear on the correct tab and successful authentication resumes intended checkout.
- **Acceptance criteria:** Errors appear on the correct tab and successful authentication resumes intended checkout.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D08 - Build guest storefront and public shop directory

- **Hierarchy:** FarSell > Buyer > Discovery > Task D08 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** Done | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D02,D06
- **Subtasks:**
  - Build guest storefront and public shop directory.
  - Verify and attach evidence: Guests can browse available products and active shops; empty lists have useful states.
- **Acceptance criteria:** Guests can browse available products and active shops; empty lists have useful states.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D09 - Implement profile editing and safe account deletion

- **Hierarchy:** FarSell > Shared > Profile > Task D09 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 6 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D01
- **Subtasks:**
  - Implement profile editing and safe account deletion.
  - Verify and attach evidence: Users edit only themselves; password changes revoke old sessions; marketplace history blocks deletion.
- **Acceptance criteria:** Users edit only themselves; password changes revoke old sessions; marketplace history blocks deletion.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D10 - Implement seller application and admin review

- **Hierarchy:** FarSell > Seller > Approval > Task D10 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 6 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D01
- **Subtasks:**
  - Implement seller application and admin review.
  - Verify and attach evidence: Approval creates shop and seller role atomically; stale decisions are rejected.
- **Acceptance criteria:** Approval creates shop and seller role atomically; stale decisions are rejected.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D11 - Implement seller-owned listing and stock editor

- **Hierarchy:** FarSell > Seller > Listings > Task D11 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 8 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D02,D10
- **Subtasks:**
  - Implement seller-owned listing and stock editor.
  - Verify and attach evidence: Seller cannot edit another shop; stale stock edits fail; published products can be purchased.
- **Acceptance criteria:** Seller cannot edit another shop; stale stock edits fail; published products can be purchased.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D12 - Build seller inventory and order-item screens

- **Hierarchy:** FarSell > Seller > Dashboard > Task D12 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** Done | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D06,D11
- **Subtasks:**
  - Build seller inventory and order-item screens.
  - Verify and attach evidence: Inventory and order items are scoped to the signed-in seller's shop.
- **Acceptance criteria:** Inventory and order items are scoped to the signed-in seller's shop.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D13 - Implement rider applications and admin approval

- **Hierarchy:** FarSell > Rider > Onboarding > Task D13 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 6 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D01
- **Subtasks:**
  - Implement rider applications and admin approval.
  - Verify and attach evidence: Private documents require authorization; approval grants rider access without overwriting seller/admin roles.
- **Acceptance criteria:** Private documents require authorization; approval grants rider access without overwriting seller/admin roles.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D14 - Build rider onboarding and delivery dashboard

- **Hierarchy:** FarSell > Rider > Dashboard > Task D14 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** Done | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D06,D13
- **Subtasks:**
  - Build rider onboarding and delivery dashboard.
  - Verify and attach evidence: Applicants see onboarding; approved riders see only their assigned active shipments.
- **Acceptance criteria:** Applicants see onboarding; approved riders see only their assigned active shipments.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D15 - Implement shop accept, reject and ready actions

- **Hierarchy:** FarSell > Seller > Fulfillment > Task D15 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 7 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D05,D11
- **Subtasks:**
  - Implement shop accept, reject and ready actions.
  - Verify and attach evidence: Shop rejection releases only its stock once; ready status requires pickup address/contact.
- **Acceptance criteria:** Shop rejection releases only its stock once; ready status requires pickup address/contact.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D16 - Implement delivery offers and admin fallback assignment

- **Hierarchy:** FarSell > Logistics > Dispatch foundation > Task D16 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 8 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D13,D15
- **Subtasks:**
  - Implement delivery offers and admin fallback assignment.
  - Verify and attach evidence: One rider wins a claim; expired or declined offers retry without duplicate assignment.
- **Acceptance criteria:** One rider wins a claim; expired or declined offers retry without duplicate assignment.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D17 - Implement pickup, delivery and buyer receipt actions

- **Hierarchy:** FarSell > Rider > Delivery > Task D17 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 7 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D16
- **Subtasks:**
  - Implement pickup, delivery and buyer receipt actions.
  - Verify and attach evidence: Only assigned rider can transition shipment; buyer receipt completes the correct shipment.
- **Acceptance criteria:** Only assigned rider can transition shipment; buyer receipt completes the correct shipment.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D18 - Provide printable invoices and packing slips

- **Hierarchy:** FarSell > Buyer > Documents > Task D18 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D05
- **Subtasks:**
  - Provide printable invoices and packing slips.
  - Verify and attach evidence: Only authorized users print the order; cancelled orders show no payment due.
- **Acceptance criteria:** Only authorized users print the order; cancelled orders show no payment due.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D19 - Implement persistent buyer notification inbox

- **Hierarchy:** FarSell > Buyer > Notifications > Task D19 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D05,D17
- **Subtasks:**
  - Implement persistent buyer notification inbox.
  - Verify and attach evidence: Read actions affect only the current buyer; unread counts reflect persisted state.
- **Acceptance criteria:** Read actions affect only the current buyer; unread counts reflect persisted state.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D20 - Implement cancellation before rider assignment

- **Hierarchy:** FarSell > Buyer > Cancellation > Task D20 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D05,D15
- **Subtasks:**
  - Implement cancellation before rider assignment.
  - Verify and attach evidence: Assigned shipments block cancellation; repeated cancellation never double-restocks.
- **Acceptance criteria:** Assigned shipments block cancellation; repeated cancellation never double-restocks.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D21 - Implement return requests and reimbursement recording

- **Hierarchy:** FarSell > Buyer > Returns > Task D21 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 8 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D17
- **Subtasks:**
  - Implement return requests and reimbursement recording.
  - Verify and attach evidence: Quantity and amount are bounded; evidence is private; reimbursement is recorded once without automatic transfer claims.
- **Acceptance criteria:** Quantity and amount are bounded; evidence is private; reimbursement is recorded once without automatic transfer claims.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D22 - Implement sales summary and safe CSV export

- **Hierarchy:** FarSell > Seller > Sales > Task D22 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D17,D21
- **Subtasks:**
  - Implement sales summary and safe CSV export.
  - Verify and attach evidence: Only own-shop completed COD merchandise is counted; reimbursements use their recorded dates.
- **Acceptance criteria:** Only own-shop completed COD merchandise is counted; reimbursements use their recorded dates.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D23 - Implement admin MFA, session revocation and audit records

- **Hierarchy:** FarSell > Shared > Security > Task D23 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 8 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D09
- **Subtasks:**
  - Implement admin MFA, session revocation and audit records.
  - Verify and attach evidence: Sensitive writes require recent confirmation; revoked sessions lose access; secrets are excluded from audit logs.
- **Acceptance criteria:** Sensitive writes require recent confirmation; revoked sessions lose access; secrets are excluded from audit logs.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### D24 - Implement purchased-product feedback and media

- **Hierarchy:** FarSell > Buyer > Reviews > Task D24 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** Done | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** Historical; date not recorded | **Deadline:** Historical; date not recorded
- **Dependencies:** D17
- **Subtasks:**
  - Implement purchased-product feedback and media.
  - Verify and attach evidence: Ineligible reviews and invalid media are rejected; eligible buyer feedback is associated with the purchased item.
- **Acceptance criteria:** Ineligible reviews and invalid media are rejected; eligible buyer feedback is associated with the purchased item.
- **Evidence:** Historical implementation evidence; owner confirmation and evidence audit pending in P05.

### P01 - Confirm five-role scope and Flutter deliverables

- **Hierarchy:** FarSell > Planning > Scope > Task P01 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** High | **Estimate:** 3 hours
- **Start:** 2026-11-09 | **Deadline:** 2026-11-10 17:00 Asia/Manila
- **Dependencies:** None
- **Subtasks:**
  - Confirm five-role scope and Flutter deliverables.
  - Verify and attach evidence: Team approves five-role permissions, buyer/rider mobile screens and explicit payment/push exclusions.
- **Acceptance criteria:** Team approves five-role permissions, buyer/rider mobile screens and explicit payment/push exclusions.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P02 - Populate task board and confirm team lead

- **Hierarchy:** FarSell > Planning > Workspace > Task P02 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** High | **Estimate:** 3 hours
- **Start:** 2026-11-11 | **Deadline:** 2026-11-12 17:00 Asia/Manila
- **Dependencies:** P01
- **Subtasks:**
  - Populate task board and confirm team lead.
  - Verify and attach evidence: Every card has one owner, estimate, priority, deadline, dependency and acceptance; proposed lead Karl is confirmed.
- **Acceptance criteria:** Every card has one owner, estimate, priority, deadline, dependency and acceptance; proposed lead Karl is confirmed.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P03 - Map roles, database and API boundaries

- **Hierarchy:** FarSell > Planning > Architecture > Task P03 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** High | **Estimate:** 3 hours
- **Start:** 2026-11-11 | **Deadline:** 2026-11-12 17:00 Asia/Manila
- **Dependencies:** P01
- **Subtasks:**
  - Map roles, database and API boundaries.
  - Verify and attach evidence: Admin maps to Superadmin and restricted Logistics permissions are distinguished from global administration.
- **Acceptance criteria:** Admin maps to Superadmin and restricted Logistics permissions are distinguished from global administration.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P04 - Design buyer and rider Flutter flows

- **Hierarchy:** FarSell > UI UX > Mobile flows > Task P04 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 6 hours
- **Start:** 2026-11-11 | **Deadline:** 2026-11-13 17:00 Asia/Manila
- **Dependencies:** P01
- **Subtasks:**
  - Design buyer and rider Flutter flows.
  - Verify and attach evidence: Each agreed function has navigation, required data, loading, validation, empty and offline states.
- **Acceptance criteria:** Each agreed function has navigation, required data, loading, validation, empty and offline states.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P05 - Audit historical finished-task evidence

- **Hierarchy:** FarSell > QA > Evidence audit > Task P05 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 6 hours
- **Start:** 2026-11-13 | **Deadline:** 2026-11-17 17:00 Asia/Manila
- **Dependencies:** P02
- **Subtasks:**
  - Audit historical finished-task evidence.
  - Verify and attach evidence: D01-D24 have linked implementation/test evidence and confirmed owners; unsupported cards move to In Review.
- **Acceptance criteria:** D01-D24 have linked implementation/test evidence and confirmed owners; unsupported cards move to In Review.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P06 - Rehearse buyer browsing, checkout and documents

- **Hierarchy:** FarSell > QA > Buyer rehearsal > Task P06 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 6 hours
- **Start:** 2026-11-18 | **Deadline:** 2026-11-20 17:00 Asia/Manila
- **Dependencies:** P05
- **Subtasks:**
  - Rehearse buyer browsing, checkout and documents.
  - Verify and attach evidence: Two-account walkthrough records successful purchase/invoice and rejects foreign addresses and unavailable stock.
- **Acceptance criteria:** Two-account walkthrough records successful purchase/invoice and rejects foreign addresses and unavailable stock.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P07 - Rehearse seller approval and mixed-shop fulfillment

- **Hierarchy:** FarSell > QA > Seller rehearsal > Task P07 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2026-11-23 | **Deadline:** 2026-11-25 17:00 Asia/Manila
- **Dependencies:** P06
- **Subtasks:**
  - Rehearse seller approval and mixed-shop fulfillment.
  - Verify and attach evidence: Approval, publication and fulfillment succeed; stale edits fail and other-shop records remain hidden.
- **Acceptance criteria:** Approval, publication and fulfillment succeed; stale edits fail and other-shop records remain hidden.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P08 - Rehearse rider approval, claim and delivery proof

- **Hierarchy:** FarSell > QA > Rider rehearsal > Task P08 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2026-11-26 | **Deadline:** 2026-11-30 17:00 Asia/Manila
- **Dependencies:** P07
- **Subtasks:**
  - Rehearse rider approval, claim and delivery proof.
  - Verify and attach evidence: Assigned delivery succeeds; expired offers, wrong rider and invalid PIN/proof are rejected.
- **Acceptance criteria:** Assigned delivery succeeds; expired offers, wrong rider and invalid PIN/proof are rejected.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P09 - Implement dedicated restricted Logistics role

- **Hierarchy:** FarSell > Superadmin > Permissions > Task P09 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** High | **Estimate:** 4 hours
- **Start:** 2026-11-13 | **Deadline:** 2026-11-16 17:00 Asia/Manila
- **Dependencies:** P03
- **Subtasks:**
  - Implement dedicated restricted Logistics role.
  - Verify and attach evidence: Logistics can dispatch and monitor but cannot change global roles, settings, products or admin security.
- **Acceptance criteria:** Logistics can dispatch and monitor but cannot change global roles, settings, products or admin security.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P10 - Build separate Superadmin and Logistics navigation

- **Hierarchy:** FarSell > Web UI > Role navigation > Task P10 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2026-11-17 | **Deadline:** 2026-11-18 17:00 Asia/Manila
- **Dependencies:** P09
- **Subtasks:**
  - Build separate Superadmin and Logistics navigation.
  - Verify and attach evidence: Each role sees allowed workspace links and direct forbidden URLs fail server-side.
- **Acceptance criteria:** Each role sees allowed workspace links and direct forbidden URLs fail server-side.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P11 - Verify all five roles against permission matrix

- **Hierarchy:** FarSell > QA > Role isolation > Task P11 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** High | **Estimate:** 4 hours
- **Start:** 2026-11-25 | **Deadline:** 2026-12-01 17:00 Asia/Manila
- **Dependencies:** P09,P10
- **Subtasks:**
  - Verify all five roles against permission matrix.
  - Verify and attach evidence: Every forbidden role/action case rejects access without data mutation or private-data disclosure.
- **Acceptance criteria:** Every forbidden role/action case rejects access without data mutation or private-data disclosure.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P12 - Verify buyer, seller and rider management controls

- **Hierarchy:** FarSell > Superadmin > User management > Task P12 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2026-12-02 | **Deadline:** 2026-12-03 17:00 Asia/Manila
- **Dependencies:** P03
- **Subtasks:**
  - Verify buyer, seller and rider management controls.
  - Verify and attach evidence: Admin search works; suspension revokes access without deleting order history; unauthorized role changes fail.
- **Acceptance criteria:** Admin search works; suspension revokes access without deleting order history; unauthorized role changes fail.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P13 - Verify category, product and review moderation

- **Hierarchy:** FarSell > Superadmin > Moderation > Task P13 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2026-12-04 | **Deadline:** 2026-12-07 17:00 Asia/Manila
- **Dependencies:** P12
- **Subtasks:**
  - Verify category, product and review moderation.
  - Verify and attach evidence: Invalid changes fail; moderation preserves order history and records accountable changes.
- **Acceptance criteria:** Invalid changes fail; moderation preserves order history and records accountable changes.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P14 - Complete bounded order report dataset and export

- **Hierarchy:** FarSell > Superadmin > Reports data > Task P14 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2026-11-17 | **Deadline:** 2026-11-19 17:00 Asia/Manila
- **Dependencies:** P03
- **Subtasks:**
  - Complete bounded order report dataset and export.
  - Verify and attach evidence: Date/status totals reconcile with fixture orders and export neutralizes spreadsheet formula text.
- **Acceptance criteria:** Date/status totals reconcile with fixture orders and export neutralizes spreadsheet formula text.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P15 - Build report filters and summary screen

- **Hierarchy:** FarSell > Superadmin > Reports UI > Task P15 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2026-11-20 | **Deadline:** 2026-11-23 17:00 Asia/Manila
- **Dependencies:** P14
- **Subtasks:**
  - Build report filters and summary screen.
  - Verify and attach evidence: Screen totals and CSV match the selected period and remain admin-only.
- **Acceptance criteria:** Screen totals and CSV match the selected period and remain admin-only.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P16 - Implement validated fee and notification settings

- **Hierarchy:** FarSell > Superadmin > Settings data > Task P16 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2026-11-19 | **Deadline:** 2026-11-23 17:00 Asia/Manila
- **Dependencies:** P03
- **Subtasks:**
  - Implement validated fee and notification settings.
  - Verify and attach evidence: Only Superadmin saves allowlisted valid values; fee changes never rewrite historical snapshots.
- **Acceptance criteria:** Only Superadmin saves allowlisted valid values; fee changes never rewrite historical snapshots.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P17 - Build settings form and validation feedback

- **Hierarchy:** FarSell > Superadmin > Settings UI > Task P17 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 3 hours
- **Start:** 2026-11-24 | **Deadline:** 2026-11-25 17:00 Asia/Manila
- **Dependencies:** P16
- **Subtasks:**
  - Build settings form and validation feedback.
  - Verify and attach evidence: Invalid values remain unsaved with visible errors; saved values reload correctly.
- **Acceptance criteria:** Invalid values remain unsaved with visible errors; saved values reload correctly.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P18 - Verify and refine store-profile editing

- **Hierarchy:** FarSell > Seller > Store profile > Task P18 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 3 hours
- **Start:** 2026-11-26 | **Deadline:** 2026-11-27 17:00 Asia/Manila
- **Dependencies:** P07
- **Subtasks:**
  - Verify and refine store-profile editing.
  - Verify and attach evidence: Seller changes only their shop; public storefront contains only intended business information.
- **Acceptance criteria:** Seller changes only their shop; public storefront contains only intended business information.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P19 - Implement bounded seller promotion rules

- **Hierarchy:** FarSell > Seller > Promotions data > Task P19 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** Normal | **Estimate:** 6 hours
- **Start:** 2026-11-23 | **Deadline:** 2026-11-26 17:00 Asia/Manila
- **Dependencies:** P03
- **Subtasks:**
  - Implement bounded seller promotion rules.
  - Verify and attach evidence: Expired, invalid and other-shop discounts fail; active discount uses authoritative server pricing.
- **Acceptance criteria:** Expired, invalid and other-shop discounts fail; active discount uses authoritative server pricing.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P20 - Build promotion editor and active-price display

- **Hierarchy:** FarSell > Seller > Promotions UI > Task P20 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2026-11-27 | **Deadline:** 2026-12-01 17:00 Asia/Manila
- **Dependencies:** P19
- **Subtasks:**
  - Build promotion editor and active-price display.
  - Verify and attach evidence: Seller manages own promotion and buyers see correct effective price and validity.
- **Acceptance criteria:** Seller manages own promotion and buyers see correct effective price and validity.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P21 - Test promotion timing and checkout totals

- **Hierarchy:** FarSell > QA > Promotions > Task P21 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 3 hours
- **Start:** 2026-12-08 | **Deadline:** 2026-12-09 17:00 Asia/Manila
- **Dependencies:** P20
- **Subtasks:**
  - Test promotion timing and checkout totals.
  - Verify and attach evidence: Boundary dates behave correctly and storefront/cart/checkout agree on authoritative discount.
- **Acceptance criteria:** Boundary dates behave correctly and storefront/cart/checkout agree on authoritative discount.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P22 - Implement delivery queue and operational filters

- **Hierarchy:** FarSell > Logistics > Monitoring data > Task P22 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2026-11-26 | **Deadline:** 2026-11-30 17:00 Asia/Manila
- **Dependencies:** P09
- **Subtasks:**
  - Implement delivery queue and operational filters.
  - Verify and attach evidence: Ready, assigned and delayed filters match stored state; output contains only necessary operational fields.
- **Acceptance criteria:** Ready, assigned and delayed filters match stored state; output contains only necessary operational fields.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P23 - Build dispatch board and shipment-event view

- **Hierarchy:** FarSell > Logistics > Monitoring UI > Task P23 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2026-12-01 | **Deadline:** 2026-12-03 17:00 Asia/Manila
- **Dependencies:** P22
- **Subtasks:**
  - Build dispatch board and shipment-event view.
  - Verify and attach evidence: Logistics filters city/status and reads chronological events without global-admin controls.
- **Acceptance criteria:** Logistics filters city/status and reads chronological events without global-admin controls.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P24 - Build manual eligible-rider assignment form

- **Hierarchy:** FarSell > Logistics > Assignment UI > Task P24 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2026-12-04 | **Deadline:** 2026-12-07 17:00 Asia/Manila
- **Dependencies:** P23
- **Subtasks:**
  - Build manual eligible-rider assignment form.
  - Verify and attach evidence: Ineligible riders are rejected; competing assignment shows a useful error without double assignment.
- **Acceptance criteria:** Ineligible riders are rejected; competing assignment shows a useful error without double assignment.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P25 - Implement delivery-performance report dataset

- **Hierarchy:** FarSell > Logistics > Reports data > Task P25 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2026-12-01 | **Deadline:** 2026-12-02 17:00 Asia/Manila
- **Dependencies:** P22
- **Subtasks:**
  - Implement delivery-performance report dataset.
  - Verify and attach evidence: Period/status counts reconcile with fixtures and exports omit unnecessary private fields.
- **Acceptance criteria:** Period/status counts reconcile with fixtures and exports omit unnecessary private fields.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P26 - Build delivery-report filters and export action

- **Hierarchy:** FarSell > Logistics > Reports UI > Task P26 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 3 hours
- **Start:** 2026-12-08 | **Deadline:** 2026-12-09 17:00 Asia/Manila
- **Dependencies:** P25
- **Subtasks:**
  - Build delivery-report filters and export action.
  - Verify and attach evidence: Screen and CSV agree for selected period and access is limited to permitted operations roles.
- **Acceptance criteria:** Screen and CSV agree for selected period and access is limited to permitted operations roles.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P27 - Verify concurrent checkout and rider claims on MySQL

- **Hierarchy:** FarSell > QA > Concurrency > Task P27 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** High | **Estimate:** 6 hours
- **Start:** 2026-12-09 | **Deadline:** 2026-12-14 17:00 Asia/Manila
- **Dependencies:** P24
- **Subtasks:**
  - Verify concurrent checkout and rider claims on MySQL.
  - Verify and attach evidence: Simultaneous requests cannot oversell one remaining unit or assign two riders to one shipment.
- **Acceptance criteria:** Simultaneous requests cannot oversell one remaining unit or assign two riders to one shipment.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P28 - Verify scheduled dispatch and expired-offer retry

- **Hierarchy:** FarSell > QA > Scheduler > Task P28 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 3 hours
- **Start:** 2026-12-14 | **Deadline:** 2026-12-15 17:00 Asia/Manila
- **Dependencies:** P22
- **Subtasks:**
  - Verify scheduled dispatch and expired-offer retry.
  - Verify and attach evidence: Dispatch runs at intended interval and advances expired offers without duplicate active assignments.
- **Acceptance criteria:** Dispatch runs at intended interval and advances expired offers without duplicate active assignments.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P29 - Verify proof, history and earnings boundaries

- **Hierarchy:** FarSell > QA > Rider history > Task P29 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2026-12-16 | **Deadline:** 2026-12-17 17:00 Asia/Manila
- **Dependencies:** P08
- **Subtasks:**
  - Verify proof, history and earnings boundaries.
  - Verify and attach evidence: History is rider-scoped; proof stays private; recorded earnings are distinguished from actual payouts.
- **Acceptance criteria:** History is rider-scoped; proof stays private; recorded earnings are distinguished from actual payouts.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P30 - Specify versioned buyer and rider API contracts

- **Hierarchy:** FarSell > API > Contract > Task P30 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** High | **Estimate:** 4 hours
- **Start:** 2026-12-03 | **Deadline:** 2026-12-04 17:00 Asia/Manila
- **Dependencies:** P03,P04
- **Subtasks:**
  - Specify versioned buyer and rider API contracts.
  - Verify and attach evidence: Every mobile screen maps to endpoints with authentication, pagination and example success/error responses.
- **Acceptance criteria:** Every mobile screen maps to endpoints with authentication, pagination and example success/error responses.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P31 - Implement token login, logout and revocation

- **Hierarchy:** FarSell > API > Authentication > Task P31 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** High | **Estimate:** 6 hours
- **Start:** 2026-12-07 | **Deadline:** 2026-12-09 17:00 Asia/Manila
- **Dependencies:** P30
- **Subtasks:**
  - Implement token login, logout and revocation.
  - Verify and attach evidence: Valid permitted accounts receive tokens; invalid credentials fail; logout invalidates current token.
- **Acceptance criteria:** Valid permitted accounts receive tokens; invalid credentials fail; logout invalidates current token.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P32 - Initialize navigation and shared API client

- **Hierarchy:** FarSell > Flutter > Foundation > Task P32 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2026-12-09 | **Deadline:** 2026-12-11 17:00 Asia/Manila
- **Dependencies:** P04,P30
- **Subtasks:**
  - Initialize navigation and shared API client.
  - Verify and attach evidence: App runs on target device and handles timeout and server errors without crashing.
- **Acceptance criteria:** App runs on target device and handles timeout and server errors without crashing.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P33 - Build sign-in and secure session handling

- **Hierarchy:** FarSell > Flutter > Authentication > Task P33 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2026-12-14 | **Deadline:** 2026-12-16 17:00 Asia/Manila
- **Dependencies:** P31,P32
- **Subtasks:**
  - Build sign-in and secure session handling.
  - Verify and attach evidence: Tokens use platform secure storage; restart restores valid session; logout/revocation returns to sign-in.
- **Acceptance criteria:** Tokens use platform secure storage; restart restores valid session; logout/revocation returns to sign-in.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P34 - Complete product detail, filter and pagination endpoints

- **Hierarchy:** FarSell > API > Catalogue > Task P34 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** High | **Estimate:** 4 hours
- **Start:** 2026-12-10 | **Deadline:** 2026-12-11 17:00 Asia/Manila
- **Dependencies:** P30
- **Subtasks:**
  - Complete product detail, filter and pagination endpoints.
  - Verify and attach evidence: Unpublished products/inactive shops remain hidden and invalid filters return defined errors.
- **Acceptance criteria:** Unpublished products/inactive shops remain hidden and invalid filters return defined errors.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P35 - Build product list, search and detail screens

- **Hierarchy:** FarSell > Flutter > Catalogue > Task P35 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2026-12-16 | **Deadline:** 2026-12-18 17:00 Asia/Manila
- **Dependencies:** P32,P34
- **Subtasks:**
  - Build product list, search and detail screens.
  - Verify and attach evidence: Buyer filters and opens products; loading, pagination, empty and request-failure states work.
- **Acceptance criteria:** Buyer filters and opens products; loading, pagination, empty and request-failure states work.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P36 - Implement authenticated cart endpoints

- **Hierarchy:** FarSell > API > Cart > Task P36 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** High | **Estimate:** 5 hours
- **Start:** 2026-12-14 | **Deadline:** 2026-12-16 17:00 Asia/Manila
- **Dependencies:** P31,P34
- **Subtasks:**
  - Implement authenticated cart endpoints.
  - Verify and attach evidence: Only current buyer's cart changes; excessive quantities fail and totals use authoritative prices.
- **Acceptance criteria:** Only current buyer's cart changes; excessive quantities fail and totals use authoritative prices.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P37 - Build add, update and remove cart interface

- **Hierarchy:** FarSell > Flutter > Cart > Task P37 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2026-12-21 | **Deadline:** 2026-12-23 17:00 Asia/Manila
- **Dependencies:** P33,P35,P36
- **Subtasks:**
  - Build add, update and remove cart interface.
  - Verify and attach evidence: Confirmed changes update totals; failed changes never show an unconfirmed success state.
- **Acceptance criteria:** Confirmed changes update totals; failed changes never show an unconfirmed success state.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P38 - Implement address and idempotent checkout endpoints

- **Hierarchy:** FarSell > API > Checkout > Task P38 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** High | **Estimate:** 7 hours
- **Start:** 2026-12-17 | **Deadline:** 2026-12-22 17:00 Asia/Manila
- **Dependencies:** P36
- **Subtasks:**
  - Implement address and idempotent checkout endpoints.
  - Verify and attach evidence: Foreign addresses fail; repeated submission cannot create duplicate orders or double-reserve stock.
- **Acceptance criteria:** Foreign addresses fail; repeated submission cannot create duplicate orders or double-reserve stock.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P39 - Build address selection and checkout confirmation

- **Hierarchy:** FarSell > Flutter > Checkout > Task P39 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 6 hours
- **Start:** 2026-12-24 | **Deadline:** 2026-12-28 17:00 Asia/Manila
- **Dependencies:** P37,P38
- **Subtasks:**
  - Build address selection and checkout confirmation.
  - Verify and attach evidence: Buyer confirms server total and receives one order; timeout supports safe recovery without duplicate submission.
- **Acceptance criteria:** Buyer confirms server total and receives one order; timeout supports safe recovery without duplicate submission.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P40 - Implement buyer order history and shipment resources

- **Hierarchy:** FarSell > API > Orders > Task P40 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** High | **Estimate:** 4 hours
- **Start:** 2026-12-23 | **Deadline:** 2026-12-24 17:00 Asia/Manila
- **Dependencies:** P38
- **Subtasks:**
  - Implement buyer order history and shipment resources.
  - Verify and attach evidence: Only own orders are returned; timeline matches web; pagination is deterministic.
- **Acceptance criteria:** Only own orders are returned; timeline matches web; pagination is deterministic.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P41 - Build order history and shipment timeline screens

- **Hierarchy:** FarSell > Flutter > Orders > Task P41 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2026-12-29 | **Deadline:** 2026-12-30 17:00 Asia/Manila
- **Dependencies:** P39,P40
- **Subtasks:**
  - Build order history and shipment timeline screens.
  - Verify and attach evidence: Buyer reopens placed order and refreshes current events; no other buyer's data appears.
- **Acceptance criteria:** Buyer reopens placed order and refreshes current events; no other buyer's data appears.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P42 - Implement availability and eligible-offer endpoints

- **Hierarchy:** FarSell > API > Rider requests > Task P42 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** High | **Estimate:** 5 hours
- **Start:** 2026-12-16 | **Deadline:** 2026-12-28 17:00 Asia/Manila
- **Dependencies:** P31,P22
- **Subtasks:**
  - Implement availability and eligible-offer endpoints.
  - Verify and attach evidence: Only approved available riders receive offers; pre-claim private fields stay hidden; competing claims have one winner.
- **Acceptance criteria:** Only approved available riders receive offers; pre-claim private fields stay hidden; competing claims have one winner.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P43 - Build availability and accept-decline offer screens

- **Hierarchy:** FarSell > Flutter > Rider requests > Task P43 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2026-12-31 | **Deadline:** 2027-01-04 17:00 Asia/Manila
- **Dependencies:** P33,P42
- **Subtasks:**
  - Build availability and accept-decline offer screens.
  - Verify and attach evidence: Unavailable riders stop receiving requests and expired offers cannot be accepted.
- **Acceptance criteria:** Unavailable riders stop receiving requests and expired offers cannot be accepted.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P44 - Implement pickup and delivery-proof endpoints

- **Hierarchy:** FarSell > API > Rider actions > Task P44 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** High | **Estimate:** 5 hours
- **Start:** 2026-12-29 | **Deadline:** 2026-12-31 17:00 Asia/Manila
- **Dependencies:** P42
- **Subtasks:**
  - Implement pickup and delivery-proof endpoints.
  - Verify and attach evidence: Only assigned rider transitions permitted states; invalid proof/PIN leaves shipment unchanged.
- **Acceptance criteria:** Only assigned rider transitions permitted states; invalid proof/PIN leaves shipment unchanged.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P45 - Build assigned delivery details and completion screens

- **Hierarchy:** FarSell > Flutter > Rider delivery > Task P45 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2027-01-05 | **Deadline:** 2027-01-07 17:00 Asia/Manila
- **Dependencies:** P43,P44
- **Subtasks:**
  - Build assigned delivery details and completion screens.
  - Verify and attach evidence: Assigned rider completes pickup/proof flow with actionable errors and no secret leakage.
- **Acceptance criteria:** Assigned rider completes pickup/proof flow with actionable errors and no secret leakage.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P46 - Expose paginated notification and read endpoints

- **Hierarchy:** FarSell > API > Notifications > Task P46 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** High | **Estimate:** 4 hours
- **Start:** 2026-12-28 | **Deadline:** 2027-01-01 17:00 Asia/Manila
- **Dependencies:** P31,P40
- **Subtasks:**
  - Expose paginated notification and read endpoints.
  - Verify and attach evidence: Read operations affect current user only and unread totals agree with stored state.
- **Acceptance criteria:** Read operations affect current user only and unread totals agree with stored state.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P47 - Build notification inbox and offline retry states

- **Hierarchy:** FarSell > Flutter > Notifications > Task P47 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2027-01-04 | **Deadline:** 2027-01-08 17:00 Asia/Manila
- **Dependencies:** P33,P46
- **Subtasks:**
  - Build notification inbox and offline retry states.
  - Verify and attach evidence: Buyer reads updates and opens related order; offline retry works without claiming background push.
- **Acceptance criteria:** Buyer reads updates and opens related order; offline retry works without claiming background push.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P48 - Test token, role, ownership and retry failures

- **Hierarchy:** FarSell > QA > API security > Task P48 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 6 hours
- **Start:** 2027-01-04 | **Deadline:** 2027-01-06 17:00 Asia/Manila
- **Dependencies:** P40,P44,P46
- **Subtasks:**
  - Test token, role, ownership and retry failures.
  - Verify and attach evidence: Invalid tokens and foreign IDs reveal no private records; repeated checkout cannot duplicate orders.
- **Acceptance criteria:** Invalid tokens and foreign IDs reveal no private records; repeated checkout cannot duplicate orders.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P49 - Run buyer device end-to-end acceptance

- **Hierarchy:** FarSell > QA > Mobile buyer > Task P49 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2027-01-11 | **Deadline:** 2027-01-13 17:00 Asia/Manila
- **Dependencies:** P41,P47
- **Subtasks:**
  - Run buyer device end-to-end acceptance.
  - Verify and attach evidence: Target device completes purchase and safely recovers from timeout, lost network and invalid input.
- **Acceptance criteria:** Target device completes purchase and safely recovers from timeout, lost network and invalid input.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P50 - Run rider device end-to-end acceptance

- **Hierarchy:** FarSell > QA > Mobile rider > Task P50 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2027-01-08 | **Deadline:** 2027-01-14 17:00 Asia/Manila
- **Dependencies:** P45,P48
- **Subtasks:**
  - Run rider device end-to-end acceptance.
  - Verify and attach evidence: Target device completes delivery and rejects wrong rider, expired offer and revoked session.
- **Acceptance criteria:** Target device completes delivery and rejects wrong rider, expired offer and revoked session.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P51 - Reconcile notifications, returns and reports

- **Hierarchy:** FarSell > QA > Returns and reporting > Task P51 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2026-12-18 | **Deadline:** 2026-12-22 17:00 Asia/Manila
- **Dependencies:** P15,P21,P26
- **Subtasks:**
  - Reconcile notifications, returns and reports.
  - Verify and attach evidence: Known fixture totals reconcile; duplicate reimbursement fails; private return evidence stays restricted.
- **Acceptance criteria:** Known fixture totals reconcile; duplicate reimbursement fails; private return evidence stays restricted.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P52 - Fix responsive and keyboard-access defects

- **Hierarchy:** FarSell > Web UI > Accessibility > Task P52 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 5 hours
- **Start:** 2026-12-23 | **Deadline:** 2027-01-12 17:00 Asia/Manila
- **Dependencies:** P10,P17,P24
- **Subtasks:**
  - Fix responsive and keyboard-access defects.
  - Verify and attach evidence: Critical five-role forms work with keyboard, labeled errors and agreed mobile width in both themes.
- **Acceptance criteria:** Critical five-role forms work with keyboard, labeled errors and agreed mobile width in both themes.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P53 - Configure staging, worker and scheduler

- **Hierarchy:** FarSell > Release > Staging > Task P53 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** High | **Estimate:** 5 hours
- **Start:** 2027-01-07 | **Deadline:** 2027-01-18 17:00 Asia/Manila
- **Dependencies:** P27,P28,P48
- **Subtasks:**
  - Configure staging, worker and scheduler.
  - Verify and attach evidence: HTTPS, production assets and readiness work; a queued event and scheduled dispatch process successfully.
- **Acceptance criteria:** HTTPS, production assets and readiness work; a queued event and scheduled dispatch process successfully.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P54 - Rehearse database and private-file restoration

- **Hierarchy:** FarSell > Release > Recovery > Task P54 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** High | **Estimate:** 4 hours
- **Start:** 2027-01-19 | **Deadline:** 2027-01-20 17:00 Asia/Manila
- **Dependencies:** P53
- **Subtasks:**
  - Rehearse database and private-file restoration.
  - Verify and attach evidence: Isolated backup restores expected records/files without touching live data and preserves evidence privacy.
- **Acceptance criteria:** Isolated backup restores expected records/files without touching live data and preserves evidence privacy.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P55 - Review production security and payment boundaries

- **Hierarchy:** FarSell > Release > Security gate > Task P55 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** High | **Estimate:** 5 hours
- **Start:** 2027-01-19 | **Deadline:** 2027-01-21 17:00 Asia/Manila
- **Dependencies:** P48,P53
- **Subtasks:**
  - Review production security and payment boundaries.
  - Verify and attach evidence: MFA and debug-off checks pass; no critical access issue remains; COD/demo is never presented as live gateway transfer.
- **Acceptance criteria:** MFA and debug-off checks pass; no critical access issue remains; COD/demo is never presented as live gateway transfer.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P56 - Run five-role acceptance and blocker retest

- **Hierarchy:** FarSell > Release > Acceptance > Task P56 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** High | **Estimate:** 6 hours
- **Start:** 2027-01-22 | **Deadline:** 2027-01-26 17:00 Asia/Manila
- **Dependencies:** P49,P50,P51,P52,P54,P55
- **Subtasks:**
  - Run five-role acceptance and blocker retest.
  - Verify and attach evidence: Critical scripts pass with evidence; blocking defects are resolved and remaining limits are recorded.
- **Acceptance criteria:** Critical scripts pass with evidence; blocking defects are resolved and remaining limits are recorded.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P57 - Prepare setup, schema and API handoff

- **Hierarchy:** FarSell > Documentation > Technical guide > Task P57 > subtasks below
- **Owner:** Karl Benedict Boongaling
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2027-01-22 | **Deadline:** 2027-01-25 17:00 Asia/Manila
- **Dependencies:** P55
- **Subtasks:**
  - Prepare setup, schema and API handoff.
  - Verify and attach evidence: Teammate can set up isolated copy non-destructively and identify roles, relationships and API errors.
- **Acceptance criteria:** Teammate can set up isolated copy non-destructively and identify roles, relationships and API errors.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P58 - Prepare illustrated web and mobile guides

- **Hierarchy:** FarSell > Documentation > User guide > Task P58 > subtasks below
- **Owner:** Mark Jopher Domanico
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2027-01-13 | **Deadline:** 2027-01-14 17:00 Asia/Manila
- **Dependencies:** P41,P45,P52
- **Subtasks:**
  - Prepare illustrated web and mobile guides.
  - Verify and attach evidence: Guide covers all five roles and selected mobile flows with screenshots and error recovery matching actual screens.
- **Acceptance criteria:** Guide covers all five roles and selected mobile flows with screenshots and error recovery matching actual screens.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P59 - Assemble rubric evidence and demonstration script

- **Hierarchy:** FarSell > Presentation > Evidence pack > Task P59 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** Normal | **Estimate:** 4 hours
- **Start:** 2027-01-27 | **Deadline:** 2027-01-28 17:00 Asia/Manila
- **Dependencies:** P56,P57,P58
- **Subtasks:**
  - Assemble rubric evidence and demonstration script.
  - Verify and attach evidence: All six required planning outputs are linked and five-role/mobile demonstration is repeatable.
- **Acceptance criteria:** All six required planning outputs are linked and five-role/mobile demonstration is repeatable.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

### P60 - Confirm submission acceptance and board closure

- **Hierarchy:** FarSell > Release > Final handover > Task P60 > subtasks below
- **Owner:** Frances Kyle Mary Villamor
- **Status:** To Do | **Priority:** High | **Estimate:** 3 hours
- **Start:** 2027-01-29 | **Deadline:** 2027-02-01 17:00 Asia/Manila
- **Dependencies:** P59
- **Subtasks:**
  - Confirm submission acceptance and board closure.
  - Verify and attach evidence: Team signs off; only accepted evidenced cards are Done and unresolved work remains visible.
- **Acceptance criteria:** Team signs off; only accepted evidenced cards are Done and unresolved work remains visible.
- **Evidence:** Attach acceptance result plus reviewed test output, screenshot or artifact before Done.

## Required submission and rubric checklist

- Project board: populate all 84 cards, retaining finished work and evidence.
- Feature breakdown: use Project > Module > Feature > Task > Subtask hierarchy on each card.
- Task assignment matrix: CSV maps every task to a named owner; responsibility section covers each discipline.
- Timeline: CSV provides estimated hours, start/end dates and dependencies; milestone section provides gates.
- System module map: above, including all five roles, Flutter, Laravel API and database.
- Team responsibility matrix: above, including backend, frontend, mobile, database, UI/UX, QA, documentation, deployment and proposed lead.

For the rubric, demonstrate WHAT, WHO, WHEN, HOW, DEPENDENCY and DONE on actual board cards. A document alone does not complete the workspace requirement. This revision addresses the criteria but does not guarantee a grade.

## Sources and limits

- Teacher PDF: D:/Development_Workspace_Task_Planning_Milestone (1).pdf; all six pages extracted and read.
- Teacher rubric screenshot: 25/100, all eight criteria marked Needs Improvement.
- CONTEXT_HANDOVER.md: historical implementation and role ownership.
- docs/buyer-and-seller-updates.md: November 3 implementation and historical test results.
- docs/account-protection.md: security controls and boundaries.
- routes/web.php, routes/api.php, app/Enums/UserRole.php and existing tests/Feature filenames: current surface/role/API evidence.

This is a planning deliverable. Application code, dependencies, database, live accounts, external board and deployments were not changed. Tests were not rerun.
