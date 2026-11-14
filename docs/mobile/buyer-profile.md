# Buyer profile editing

In the standalone FarSell-Mobile app, tap Profile, then the avatar to open Edit Profile. The page follows the supplied reference with a photo card and grouped details, using FarSell colors and the shared Cart/Chat header.

- Name: required, up to 120 characters.
- Bio: optional, up to 280 characters; can be cleared.
- Gender: optional; Male, Female, Non-binary, Prefer not to say, or Not set.
- Birthday: optional date from 1900 through today; day/month are masked in the summary. It can be changed or cleared.
- Phone/email: masked in the summary, shown in full only when editing. Changes require a fresh password confirmation or confirmation with the same linked Google account. Changed contacts lose verification. Other mobile sessions become invalid; the current session remains usable.
- Avatar: gallery upload, JPEG/PNG/WebP, at most 2 MB and 4096 pixels per dimension. The app resizes selected images. Interrupted uploads can be retried with the same request key; the app refreshes profile data after upload.

Profile data is read/written through authenticated buyer-only endpoints:

| Method | API path | Purpose |
|---|---|---|
| GET | `/api/v1/buyer/profile` | Read own profile |
| PATCH | `/api/v1/buyer/profile` | Save allowed fields |
| GET | `/api/v1/buyer/profile/avatar` | Read own avatar bytes |
| POST | `/api/v1/buyer/profile/avatar` | Replace avatar; UUID Idempotency-Key required |

The additive migration `2026_11_14_100000_add_optional_buyer_profile_details.php` adds nullable avatar, bio, gender and birthday fields. It does not seed or replace account data. Session responses include these fields explicitly for the account owner; optional personal details are hidden from generic User serialization.

Automated checks cover profile persistence and validation, ownership/role checks, contact confirmation bound to the same user/access token, stale other sessions, Google subject matching, avatar retries and the phone-layout editor flow. Live photo picker and Google confirmation still require interactive device acceptance.
