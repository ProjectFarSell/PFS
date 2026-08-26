# FarSell Mobile Setup: Capacitor

This project should validate its marketplace workflow on the responsive Laravel web app first. The recommended mobile path for the first release is a separate Capacitor client that calls the Laravel API. That keeps the mobile app native where it matters (camera, push notifications, background location later) without duplicating business rules.

Capacitor is the chosen option because it targets Android and iOS from web technologies. Do not turn the Laravel Blade pages into an always-online WebView wrapper; build the rider/customer mobile screens against the API once the API contract is stable.

## 1. Finish the web/API prerequisites

Before starting the mobile client, complete these FarSell tasks:

1. Start the Laravel application and seed the database as described in `README.md`.
2. Confirm `http://localhost/api/v1/health` returns JSON.
3. Confirm `http://localhost/api/v1/catalog` returns a paginated product list.
4. In the mobile milestone, install Laravel Sanctum and add token-based login. Do not send browser-session cookies or passwords as stored mobile state. Sanctum is Laravel's recommended authentication option for first-party mobile clients.

## 2. Install the mobile prerequisites

Install these on the development machine:

- Node.js LTS.
- Android Studio with Android SDK, an emulator, and the Android SDK command-line tools.
- Xcode on macOS only, when the team is ready to build iOS.

Use a physical Android device only after the API is reachable over HTTPS or through an approved development tunnel. Never point a production build at a local HTTP API.

## 3. Create the Capacitor client

Run these commands in a sibling directory to this Laravel project. The bundle identifier is an example; change it before shipping.

```powershell
cd ..
npm create vite@latest farsell-mobile -- --template vanilla-ts
cd farsell-mobile
npm install
npm install @capacitor/core @capacitor/cli @capacitor/android @capacitor/ios
npx cap init FarSell com.farsell.app --web-dir=dist
npx cap add android
npx cap add ios
```

The iOS command works only on macOS with Xcode. Keep the Laravel server and this mobile project as separate repositories or top-level folders; they are deployed independently but share the API contract.

## 4. Configure the API address

Create `src/config.ts` in the mobile app:

```ts
export const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? 'https://api.example.com/api/v1';
```

Create `.env.local` for each developer. For an Android emulator talking to Laravel on the same computer, use:

```text
VITE_API_BASE_URL=http://10.0.2.2:8000/api/v1
```

`10.0.2.2` is the Android emulator's alias for the computer running Laravel. This is development-only: production requires an HTTPS API URL. For an iOS simulator, use the host URL appropriate to the simulator and machine; a real device needs a reachable HTTPS endpoint.

## 5. Build and open Android

```powershell
npm run build
npx cap sync android
npx cap open android
```

In Android Studio, select an emulator or attached device and press Run. Each time web code, plugins, or `capacitor.config.ts` changes, run `npm run build` followed by `npx cap sync` again.

## 6. Implement the first mobile vertical slice

Build only this flow first:

1. Health check and catalogue list from `GET /api/v1/catalog`.
2. Product details.
3. Sanctum device login that returns a scoped token.
4. Rider application status and assigned-delivery list.
5. Rider delivery-state updates, protected by role and state-transition rules.

Store the device token with Capacitor's secure-storage solution chosen by the team; do not use `localStorage` for production credentials. Give customer and rider tokens different abilities, and provide a revoke-device option in account settings.

## 7. Add native features only after the vertical slice works

- Camera: rider document capture and delivery proof.
- Push notifications: order assigned, pickup reminder, delivery exception.
- Geolocation: foreground delivery navigation first; background tracking requires a separate privacy, battery, and consent review.
- Offline queue: allow a rider's status update to retry safely with an idempotency key.

## 8. Release checklist

- API is HTTPS and has rate limits, request validation, and error monitoring.
- The mobile API token is stored securely and can be revoked.
- All rider actions are authorization-tested server-side.
- Android release signing is configured; iOS uses an Apple Developer signing profile.
- Mobile test builds cover Android emulator, a real Android device, and iOS when applicable.

Reference the official [Capacitor documentation](https://capacitorjs.com/docs) for platform-specific installation and the [Laravel Sanctum mobile authentication guide](https://laravel.com/docs/master/sanctum#mobile-application-authentication) when implementing login.
