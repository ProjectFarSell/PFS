# Launch acceptance and production go/no-go

Status: **NOT APPROVED — no production domain/hosting configured.** Cloudflare configuration will be handled by the team separately.

Release commit: __________  Environment URL: __________  Date/time (PHT): __________

P1 accountable/backend: __________  P2 UI approval: __________  P3 QA approval: __________

| Acceptance item | Evidence / actual result | Owner | Approved |
|---|---|---|---|
| Exact release commit and immutable build identified | | P1 | Pending |
| Backend, frontend, build, budgets and security audits pass on that artifact | | P1/P3 | Pending |
| Hosted production configuration reviewed; debug off; stable key; isolated secrets/data | | P1 | Pending |
| Real browser HTTPS/certificate/cookie checks pass | | Team | Pending |
| Real proxy/host chain verified; Cloudflare team work separately signed off | | Team | Pending |
| Production demo accounts/data absent; real administrator provisioned | | P1 | Pending |
| Approved pilot geography and participant list documented | | P3 with P1/P2 support | Pending |
| Buyer signup, privacy consent, chosen OTP, login/logout, profile and deletion request | | P2/P3 | Pending |
| Buyer/seller/rider/admin boundaries and private documents checked on hosted setup | | P1/P3 | Pending |
| Variant/SKU, cart, owned address, prices, COD checkout, stock reserve and rejection release | | P1/P3 | Pending |
| Seller accepts/readies, rider assignment/pickup, correct PIN/proof/COD handover, buyer completion | | P1/P3 | Pending |
| Earnings, feedback, order history, invoice and email delivery checked | | P1/P3 | Pending |
| Workers/scheduler supervised; /ready and external alerts tested | | P1 | Pending |
| Hosted performance/concurrency measurements accepted | | P1/P3 | Pending |
| Actual database AND local/cloud files restored to an isolated target; keys recoverable | | P1/P3 | Pending |
| Previous artifact, compatible schema/recovery strategy and rollback decision owner recorded | | P1 | Pending |
| On-call people, channels, hours and escalation coverage agreed | | Team | Pending |

`release:check` supplies technical evidence only. It cannot approve provider recovery, verify a trusted certificate, sign another team member's approval, or authorize production traffic.

## Decision record

Decision (GO / NO-GO): __________

Open P0/P1 findings and disposition: __________

Approval references: __________

Pilot opening / expansion conditions: __________

Previous artifact / rollback trigger: __________

On-call primary / backup / coverage hours (PHT) / contact channel: __________

## Observation and retrospective

Observation start/end: __________

Actual checkout success/error rate, rider update errors, queue age, p95 latency: __________

Incidents, request IDs, containment and recovery evidence: __________

Retrospective: what happened, what helped, and the follow-up owner/deadline: __________
