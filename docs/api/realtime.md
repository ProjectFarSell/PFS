# Mobile push and Reverb event contract

Version1, November 13, 2026. Backend implementation exists; provider/staging/device checks remain pending. There are no rider-location events.

## Connect and authorize

Reverb uses the Pusher protocol. The team supplies the public WSS host/port/app key to Flutter; the app secret stays on Laravel. Send the expected origin configured in REVERB_ALLOWED_ORIGINS. Connect using protocol7, read `pusher:connection_established`, then POST `/api/v1/broadcasting/auth` with Bearer access token, socket_id and channel_name. Subscribe with the returned auth signature.

- `private-user.{opaque_user_id}`: current user only; Rider must be approved.
- `private-order.{opaque_order_id}`: owning Buyer or approved internal Rider assigned to an active shipment in that order.
- All other channels/cross-user requests return403. Expired/revoked/changed-identity tokens return401. Missing provider configuration returns 503 for an otherwise authorized channel.

Authorization is checked on subscription. Logout/role changes must close the native connection and clear subscriptions; generic invalidations already in flight cannot serve private resource data. Every refetch is authenticated again. Reverb client-originated events are disabled; possession of the public app key is not authorization.

## Events

`order.placed`, `order.status_changed`, `shipment.status_changed`, `offer.created`, `offer.expired`, `offer.taken`, `shipment.assigned`, `shipment.cancelled`, `chat.message_created`.

Offer events go to the intended Rider's private user channel, not the Buyer's order channel. Other order/shipment events can use the authorized order channel as well as intended user channels; a client subscribed to both must deduplicate.

```json
{
  "event_id": "559c17ac-b899-4804-b2b4-83d61d0302eb",
  "type": "offer.created",
  "occurred_at": "2026-11-13T05:00:00Z",
  "order_id": "5a4bbd8f-d973-4936-b17d-f917cbfd1954",
  "shipment_id": "e4813538-67f1-4fb0-ae21-bfd3e8e87bc5",
  "offer_id": "5ef04c08-3338-4338-ad16-5fb6f0ad8490",
  "expires_at": "2026-11-13T05:02:00Z"
}
```

Only event_id/type/occurred_at are always required. Resource IDs are included only where relevant. Offer creation also includes expires_at. No names, addresses, phone numbers, PINs/hashes, credentials, payment amounts or proof-photo URLs appear here. Events are invalidation hints; never reconstruct authoritative shipment state from a push or an old cached event.

## FCM payload

FCM HTTPv1 notification title/body are generic (FarSell update or delivery offer). `data` carries the event contract as strings. Android offer priority is high, TTL is remaining offer lifetime and provider collapse identifiers are opaque. APNs uses expiry/collapse headers for future iOS support.

Use `PUT /devices/{installation_id}` after login and delete it on signout. Register one current FCM token per installation/app, provide enabled event types, and re-register after token rotation. Rebinding changes ownership/version under database protection. Logout removes only bindings still attached to that token; a prior user's logout cannot erase the new account's binding. UNREGISTERED disables the current binding. These flags do not replace native OS notification permission handling.

## Reliability / reconnect

Domain-event rows commit with web/API order and shipment mutations; enqueue happens after commit. The worker retries, the minute scheduler recovers queue outages, and domain keys plus per-binding records avoid normal duplicate sends. An accepted provider request followed by a network/process failure can still produce duplicate delivery: deduplicate `event_id` on the device.

After reconnect/resume, reauthorize current subscriptions and refetch offers/shipments/orders. Use exponential reconnect backoff with jitter; retain the last event-ID cache without credentials. Foreground polling is a recovery mechanism, not a substitute for verified background Rider push.

Offers last120seconds. The worker drops expired/unavailable offer creation events. Native clients must also compare server time/expiry, show no actionable expired offer, and call the decision endpoint to verify current eligibility. Late/offline pushes never extend an offer or bypass atomic assignment.

## Non-production test trigger

With an authenticated installation/subscription already configured:

```text
php artisan mobile:test-event OPAQUE_USER_UUID
```

Only local/testing/staging; sends a generic `shipment.status_changed` invalidation to that user. It creates no order, payment, GPS record or proof. The operator explicitly invokes it; this implementation did not send a live provider notification. Use real domain actions in an isolated QA database to test offer timing and event ownership.

See `docs/mobile/backend-runbook.md` for workers, provider settings, test accounts, alerts and release gates.

Chat events carry only event metadata and conversation_id (UUID), sent to the owning Buyer user channel. Message body, sender identity and personal content are fetched through the owner-scoped chat API; never put them in FCM/socket payloads. The native Buyer subscribes to its private user channel, refetches after reconnect and also refreshes active chat in the foreground for local testing without a socket provider.
