# Flutter backend handoff

Backend implementation authorized November 13, 2026; contract version `1.0.0-dev.3`. The additive [buyer profile editor](../mobile/buyer-profile.md) brings the documented API to 64 operations.

Read [the OpenAPI contract](openapi.yaml), [scope](../mobile/mvp-scope.md), [decisions](decisions.md), [event contract](realtime.md) and [backend runbook](../mobile/backend-runbook.md).

Implemented: buyer/rider tokens and OTP, public mobile products, persistent mobile cart and guest merge, address/location APIs, checkout quotes/idempotent orders, Buyer history/private proof/receipt, Rider availability/offers/assignment transitions/failures/earnings, device binding, FCM outbox, Reverb private-channel authorization, QA fixtures, maintenance, support/app-link handlers, operator health/alerts and CI response-schema checks.

Existing public health/catalogue and website flows remain supported. The main Flutter buyer app now consumes the new product/auth/cart/address/checkout/order APIs. Device cart merge is explicit. The Rider app is deferred as a separate project. See ../mobile/buyer-check-flow.md for the buyer check flow; live provider/device sign-off remains pending.

Provider flags default off. No staging domain, production deployment, Firebase sending credential, actual background device acceptance, app-link platform validation or proof-retention approval is claimed. Follow [the runbook](../mobile/backend-runbook.md) before release. The original document's incorrect Done assumptions are corrected in [the audit report](audit-report.md).

No commits or push were requested for this implementation.

Buyer extension update: 15 additive operations now cover Google identity verification, shared chat, public reviews/media, feedback submission, cancellation, returns/evidence and notification inbox. Read [buyer-extensions.md](../mobile/buyer-extensions.md) for main-app setup. Provider credentials, hosting, deep-link validation and signed-release acceptance remain external gates. No Rider app screens were built.
