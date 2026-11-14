# Rider Android app: setup and check flow

The separate Flutter project is `FarSell-Mobile-Rider` (`com.farsell.rider`). Buyer remains `FarSell-Mobile` (`com.farsell.buyer`); both call this Laravel backend. The Rider plan does not add GPS, routing, warehouse management, automatic offline writes, or automatic COD remittance.

## Provider configuration

Buyer and Rider can share a Firebase project or use separate projects. These optional backend settings select a dedicated Rider project:

```dotenv
MOBILE_RIDER_FIREBASE_PROJECT_ID=
MOBILE_RIDER_FCM_PROJECT_ID=
MOBILE_RIDER_FCM_CREDENTIALS=
MOBILE_RIDER_ANDROID_PACKAGE=com.farsell.rider
MOBILE_RIDER_ANDROID_SHA256=
```

The credential value is an absolute path to a backend-only service-account file. Keep private files in ignored `.secrets`; never put them in Flutter. Existing Buyer `MOBILE_FIREBASE_PROJECT_ID` / `MOBILE_FCM_*` and website Firebase settings remain independent. Unset Rider overrides preserve shared-project behavior. Once a dedicated Rider FCM project is configured, its own key is required; the backend will not use the Buyer private key as a fallback.

Phone token audience/issuer are selected using the trusted ticket/token user's role. Sending `app` or `project_id` in verification JSON cannot switch projects. Device registration validates the role/app before storage, and push selects credentials from that stored app. Restart queue workers after changing environment configuration.

Register the Rider Android package and this machine's debug SHA-1/SHA-256 in the chosen Firebase project. Enable Phone for phone OTP; test numbers avoid real SMS during QA. The app's ignored `mobile-config.local.json` must contain its own Android Firebase settings, the reachable API URL and optional public Reverb settings. Run `.\run.cmd -d emulator-5554` in the Rider folder. Main app registration values and service-account keys must never be copied into the wrong app.

Android asset links can list both packages with their own configured release fingerprints. A real HTTPS domain and matching app intent host are required for verified links. Local API/FCM setup does not establish production app-link verification.

## Manual acceptance sequence

1. Start the backend (`composer dev` or API + mobile worker + scheduler separately), Android emulator and Rider app. Start Reverb when testing socket updates.
2. Sign in with an existing Rider. Ordinary accounts complete email or configured phone OTP; explicitly exempt local demo accounts follow existing demo rules. Wrong-role accounts are rejected. Pending/missing profiles show approval status without delivery actions.
3. As an approved Rider, enable availability. Prepare a real QA order through seller acceptance/ready-for-pickup. Only internal Rider offers should appear; Lalamove jobs remain separate.
4. Check the two-minute server-calibrated offer countdown. Decline one offer; accept another. Verify expired/taken offers cannot be accepted and two Riders cannot claim the same shipment. Availability off must preserve accepted deliveries.
5. Open the assigned delivery and check pickup/drop-off, items/SKUs and timeline. Confirm pickup. The buyer can now see the PIN; Rider responses and screens must not disclose it.
6. At handover enter recipient, buyer-provided PIN and note. Confirm COD only after cash is collected. Try a wrong PIN, then the correct one. Test camera/gallery proof and validation limits. The result becomes delivered, without earnings credit yet.
7. Confirm receipt from the Buyer app/website. Refresh Rider history/earnings and verify exactly one credit under the shipment's existing fee policy.
8. With another QA shipment, report an unreachable/refused/wrong-variant/address failure. Dispatch schedules a retry or return on the website; refresh Rider instructions. No earnings are credited for a failed attempt/return.
9. Test interrupted pickup/delivery requests: manually retry the unchanged submission with the retained UUID. Ambiguous proof forms lock editing. There is no automatic offline submission; check authoritative status before restarting after a force-close.
10. Enable push, background the app, and trigger a QA offer. Verify delivery within seconds, expiry handling, notification tap, token refresh, permission denied, reconnect and account switch. API validate-only success does not prove this device acceptance.

## Automated evidence from this implementation

Rider Flutter tests cover app-role login, pending access, server-clock offer expiry, safe event/link parsing, account isolation, session restoration, COD gating, same-key multipart retries, validation/throttling and pagination. Backend Rider/security/provider/messaging/release tests cover transitions, PIN limits, replay, ownership, separate Firebase project isolation and both Android association entries. Real background push, phone SMS/camera and simultaneous MySQL claims remain device/staging acceptance checks.
