# FarSell buyer app

The main Flutter project is the buyer shopping app. The Rider app will be a separate project; no Rider screens or Rider-app login are included here.

## Run locally

Start Laravel from PFS:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

In a second terminal:

```powershell
cd C:\Users\rexie\Desktop\Code\PFS\farsell_mobile
flutter pub get
flutter run
```

The debug default is `http://10.0.2.2:8000/api/v1` for the Android emulator. A physical phone on the same Wi-Fi uses your computer's LAN IP:

```powershell
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api/v1
```

Use your actual LAN IP. Allow the local development port in Windows Firewall if required. Native dependency changes require stopping the old app and running it again; hot reload alone cannot install plugins.

## Connected buyer flows

- Live products, search, sorting, cursor pagination, images, variants, stock refresh and SKU.
- Guest saved items and cart stored locally; saved items remain device-local.
- Buyer password login, buyer registration with privacy consent, email OTP, resend cooldown and session recovery.
- Firebase phone OTP integration, enabled only in builds with the mobile Firebase options configured (see below).
- Access tokens in platform secure storage, server logout/revocation, session expiry and account separation. Passwords and OTPs are not persisted.
- Account cart, current stock and quantity changes; explicit device-cart merge with rejected items retained for review.
- Address CRUD with cascading Philippine region/province/city/barangay directories and default address selection.
- Server checkout quotes, shipping, COD order placement and an encrypted pending request that reuses its idempotency key after interruption/restart.
- Buy now adds the selected item to the account cart and opens checkout. The checkout summary includes the current account cart; review all items before placing the order.
- Order history/details, shipment timelines, buyer-only in-transit PIN, courier tracking in the browser, private proof photo and receipt confirmation.
- Reviewed account deletion request after password reauthentication; privacy/support links and browser-based forgotten-password recovery.

The new contract is `../docs/api/openapi.yaml`. The main client uses UUID product/variant IDs, integer centavos and server cursors. The old legacy adapter remains for compatibility tests. Old device selections are resolved by exact listing slug and option values when merged, never by guessed database IDs.

## Email OTP

The Laravel backend needs a working SMTP mailer. For local tests, use a local SMTP catcher such as Mailpit, or the team's configured SMTP provider. Security codes deliberately cannot use Laravel's `log` mailer. No SMTP credentials belong in Flutter. Existing reserved demo-account exemptions remain server policy; this app does not bypass authentication itself.

## Phone OTP configuration

Email is available without a mobile Firebase configuration. Phone OTP uses the saved server-side phone number; it never accepts a user-supplied Firebase token pasted into a text box.

Register the main Android application in the same Firebase project used by the backend, enable Phone Auth, and register its debug/release SHA-1 and SHA-256 fingerprints. Provide these **client configuration** options at build time:

```powershell
flutter run `
  --dart-define=FIREBASE_API_KEY=YOUR_CLIENT_API_KEY `
  --dart-define=FIREBASE_APP_ID=YOUR_ANDROID_FIREBASE_APP_ID `
  --dart-define=FIREBASE_PROJECT_ID=YOUR_PROJECT_ID `
  --dart-define=FIREBASE_MESSAGING_SENDER_ID=YOUR_SENDER_ID
```

These are Firebase client identifiers, not a service-account private key. The app initializes Firebase with explicit options, so no server `.env` or service-account JSON is bundled. No real Firebase options were invented or copied into this project. The phone option stays disabled when options are missing. Test actual SMS/reCAPTCHA behavior on a configured device and use Firebase test phone numbers where appropriate.

Official references: [Flutter phone authentication](https://firebase.google.com/docs/auth/flutter/phone-auth), [Android phone setup](https://firebase.google.com/docs/auth/android/phone-auth), [secure storage](https://pub.dev/packages/flutter_secure_storage).

## Reliability and privacy

- Review server cart errors and refresh after an interrupted add; additive cart requests are not silently retried.
- An interrupted order must be checked using the saved request before another checkout. A stale quote requires a new explicit quote; the app never silently places an order at a changed price.
- Private delivery photos are fetched with the current bearer token into memory. Courier URLs open externally without bearer headers. No PIN is shown by the app unless the owning-buyer API returns it.
- Android application backup is disabled for secure local state. Release API calls require HTTPS; HTTP is permitted only for debug development.
- Native buyer extensions now include Google sign-in, chat, reviews/attachments, cancellation, returns/refunds, notification inbox, FCM reception, Reverb subscriptions and HTTPS app-link routing. See [buyer extension setup](../docs/mobile/buyer-extensions.md). Live Google/FCM/WSS/domain verification still requires team configuration. No live payment gateway or automatic refund transfer was added.
- This repository currently targets Android. Release signing and app-link code exist, but real signing material, app IDs/domain configuration, production hosting, platform validators and store acceptance remain team work. Release never uses the debug signing key.

## Validation

```powershell
flutter analyze
flutter test
flutter build apk --debug
```

Tests cover legacy storefront behavior plus buyer API money/IDs/cursors, OTP login, session isolation, secure-storage failure, order retry recovery, stale-quote handling and durable merge cleanup. Live SMTP/SMS/device acceptance requires the corresponding real configuration.

Buyer extension setup and testing: [docs/mobile/buyer-extensions.md](../docs/mobile/buyer-extensions.md).
