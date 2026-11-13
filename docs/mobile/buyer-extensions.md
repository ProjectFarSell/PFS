# Buyer app feature extensions

The main project remains the buyer app. No Rider app screens were built.

Implemented code now includes native Google sign-in, shared website/app chat history, public product reviews, product/service/rider feedback with optional photos/video, buyer cancellation, return/refund request tracking and private evidence, notification inbox, optional FCM receiving/device registration, optional Reverb private-user subscriptions, and restricted HTTPS product/order app links.

API extension: `../api/openapi.yaml`, revision `1.0.0-dev.2`. Additions preserve existing operations and reuse shared website services. New records use UUID aliases; the numeric website keys remain internal. Cancelling or requesting returns does not create a separate mobile policy. Refunds remain reviewed and recorded by admin; no automatic money movement was added.

## Try it

- Use the shared header for Cart and Chat on buyer pages. Notifications also has its own bottom navigation tab.
- Profile > Notifications or Returns and refunds. The Profile-only Settings gear sits immediately left of Cart and opens account settings.
- Product > Customer reviews or Chat with seller.
- Order > Cancel order when the server allows it (before assignment/active courier booking).
- Completed shipment > Rate products and delivery. Anonymous reviews hide your account name; reviews and their attachments are public.
- Delivered/completed item > Return / refund. Requests/evidence remain private to authorized parties. Refresh the case to see admin decisions.
- Profile > Settings > Enable phone notifications. This needs configured Firebase and notification permission; the inbox works independently.

## Mobile configuration

Use a local, ignored `mobile-config.local.json` in the [FarSell-Mobile repository](https://github.com/KarlBRB/FarSell-Mobile) root with **public client settings only**, then run from that repository:

```powershell
flutter run --dart-define-from-file=mobile-config.local.json
```

Example contents (placeholders only):

```json
{
  "API_BASE_URL": "http://10.0.2.2:8000/api/v1",
  "GOOGLE_SERVER_CLIENT_ID": "YOUR_WEB_OAUTH_CLIENT_ID.apps.googleusercontent.com",
  "FIREBASE_API_KEY": "YOUR_FIREBASE_CLIENT_API_KEY",
  "FIREBASE_APP_ID": "YOUR_ANDROID_FIREBASE_APP_ID",
  "FIREBASE_PROJECT_ID": "YOUR_PROJECT_ID",
  "FIREBASE_MESSAGING_SENDER_ID": "YOUR_SENDER_ID",
  "REVERB_URL": "ws://10.0.2.2:8080",
  "REVERB_APP_KEY": "YOUR_PUBLIC_REVERB_APP_KEY"
}
```

Omit settings for providers you are not enabling yet. Native Google requires an Android OAuth client registered for the package/signing SHA-1 and a Web OAuth client ID as serverClientId. Laravel verifies that audience using MOBILE_GOOGLE_CLIENT_ID, falling back to GOOGLE_CLIENT_ID; it never trusts an email supplied by Flutter. Existing email/phone OTP still follows primary Google sign-in for real buyers.

Firebase phone and FCM must refer to the backend's project. Android Firebase resources are derived from the same public Dart build defines so native initialization also works for background delivery. The service-account private key belongs only on Laravel through MOBILE_FCM_CREDENTIALS. Never place it, SMTP credentials, a Google client secret, Reverb secret or `.env` in the app.

Laravel push/realtime flags remain off unless the team configures and enables them. Run `php artisan mobile:work`, the scheduler and `php artisan reverb:start` as described in backend-runbook.md. Native socket auth uses the bearer token and private-user UUID channel. Set REVERB_ALLOWED_ORIGINS to the API origin host actually sent by the app (include 10.0.2.2 for emulator tests). Public app key alone does not authorize a channel. Reconnect refetches data; duplicate events are ignored. Chat also refreshes while foregrounded to support local testing without Reverb.

## HTTPS links and release signing

Set APP_LINK_HOST to the team's actual HTTPS hostname and APPLICATION_ID to the final package ID in the build defines. Rebuild: the Gradle manifest uses the same defines as Dart. Accepted links are only `https://HOST/app/products/UUID` and `https://HOST/app/orders/UUID`; query strings, foreign hosts, non-HTTPS schemes, rider paths and non-UUID targets are rejected. Orders always require sign-in and backend ownership checks. Missing domain configuration uses a non-production `.invalid` placeholder and does not claim verified links.

Configure Laravel MOBILE_ANDROID_PACKAGE and MOBILE_ANDROID_SHA256 with the release package/certificate and validate `/.well-known/assetlinks.json` on the real domain. iOS project/store setup is not included in this Android app.

Release builds fail without all four signing environment variables: FARSELL_KEYSTORE (absolute keystore path), FARSELL_STORE_PASSWORD, FARSELL_KEY_ALIAS and FARSELL_KEY_PASSWORD. Passwords and keys stay private; do not commit them. Debug builds remain available. No release keystore, production domain, store listing or provider account was invented or published.

Review-photo/video limits are reported by the backend from its upload/post limits. Increase PHP upload/post settings deliberately if the team needs the website's full 4 MB photo / 20 MB video caps. The client checks attachment sizes before reading full files and again before submission.

## Remaining external acceptance

Real SMTP/SMS, Google account chooser with the registered Android OAuth client, foreground/background FCM and permission denial, WSS reconnect on actual networks, HTTPS association/platform validation, signed release installs, approved retention/privacy/store declarations, and production hosting need team configuration/device evidence. Mocked-provider and local widget checks do not establish these.

## Latest extension verification

- Backend: all 420 tests passed (3,577 assertions).
- Flutter: analysis clean; all 29 tests passed, including shared navigation, Profile-only settings, safe chat retries, same-second message order and recovery of missed history after a reconnect.
- Android: the final debug APK built successfully.
- Contract: all 60 operations resolve; Laravel route/view compilation and Pint passed.
- Local database: encrypted backup verified, additive migration applied and existing row counts preserved.
- Real Google chooser/SMS/FCM/WSS, app-link platform validation, private release signing and hosting still need the team's configuration and device acceptance. These checks do not constitute a live release sign-off.

The final buyer APK was installed and cold-started on emulator-5554. The storefront visually loaded the real catalogue (20 products on the first page) and no Android/Flutter startup errors were reported. No real Google sign-in, OTP, order placement or cloud push was performed during this smoke check.
