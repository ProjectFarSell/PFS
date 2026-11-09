# Account protection and security controls

## Role-based sign-in checks

### Sign-in methods

Every fresh password login or Google sign-in now requires the account's second step:

| Role | Required second step |
| --- | --- |
| Buyer / Rider | Fresh email code or Firebase SMS to the phone already saved on the account |
| Seller / Admin | Authenticator app (TOTP), with single-use recovery codes as a fallback |

Contact verification performed earlier is not a sign-in challenge. Email login codes use their own purpose-specific table, are hashed, expire after ten minutes, allow five attempts, and work once for the requesting account/session/contact. Resends require a minute, replace the old code and are rate limited. Codes never appear in the page, JSON, or audit events. The normal mail sandbox can capture them locally; real inbox delivery requires SMTP or another configured mail provider. This synchronous sign-in message does not require the order notification worker.

After passing, the user returns to the originally requested page, including checkout. Protected requests stay blocked until the second step is complete. Sign-out, a new password/Google login, a fresh remembered-login session, contact changes, role changes or session revocation invalidate the relevant check. Role changes select the new role's method. Existing active buyer/rider sessions without a challenge marker must also complete OTP. Existing admin authenticator enrollments remain usable; sessions may need to verify again.

The sign-in page never accepts an arbitrary replacement phone/email. New accounts without a saved phone use email first; users can then save and verify a phone through their profile. If neither saved destination is accessible, use independently verified support recovery rather than substituting another number on the challenge page. No public bypass or contact-replacement recovery endpoint is added here.

Phone sign-in requires Firebase Phone Auth/reCAPTCHA, configured authorized domains and the existing Firebase public project settings. The backend verifies the signed project's ID token, a matching account phone, `phone` sign-in provider, and an authentication time at or after this challenge was requested. A refreshed token from an older Firebase session is rejected. Used phone proofs cannot be consumed again. SDK authentication is kept in memory and signed out after verification. Automated tests sign RSA fixtures; real SMS still depends on the project's Firebase configuration/quota and should be tested with its configured test phone numbers first. See [Firebase token verification](https://firebase.google.com/docs/auth/admin/verify-id-tokens).

Default settings in both environment examples are `ADMIN_MFA_REQUIRED=true`, `SELLER_MFA_REQUIRED=true`, and `LOGIN_OTP_REQUIRED=true`. Keep all three enabled for deployment. Local `.env` credentials are not changed by this implementation. Apply `php artisan migrate`, rebuild the frontend, and clear configuration cache before checking the flow.

Sellers follow the authenticator enrollment and recovery-code process below, too. A seller's password or Google account confirms enrollment identity; after enrollment, password/Google confirmation alone cannot replace the authenticator challenge. This follows the password-then-second-factor shape described in [GitHub's sign-in documentation](https://docs.github.com/en/authentication/securing-your-account-with-two-factor-authentication-2fa/accessing-github-using-two-factor-authentication); GitHub passkeys, security keys, trusted-device and mobile approval features are not implemented.

The operator recovery command also supports sellers through `php artisan ops:reset-mfa seller@example.com`. Independently verify ownership first; type the exact requested confirmation. It clears the authenticator, revokes all sessions and remembered logins, and requires enrollment again. The original `ops:reset-admin-mfa` command remains an alias-compatible entry point. Production/staging still require `--confirm-production`.

Expired sign-in challenge records are pruned daily after a one-day grace period by the scheduler. Dedicated coverage is in `tests/Feature/RoleSignInSecurityTest.php`; business-flow tests disable the interactive gates explicitly so they can test their own authorization and transactions.

## Install and first admin sign-in

After pulling, run `composer install`, `php artisan migrate`, and `npm run build` (or use Vite). Restart long-running workers. Preserve APP_KEY: it encrypts both delivery PINs and authenticator secrets. The new security migration is additive and retains accounts/orders.

Admin MFA is required by default, including local development. The first time an admin visits a protected page, FarSell opens **Account security**. Confirm the password or linked Google account, start authenticator setup, manually add the displayed key in an authenticator app (time-based, six digits, 30 seconds), then enter a matching code. No setup key is sent to an external QR service. Save the eight recovery codes immediately; they are displayed once. The database keeps encrypted authenticator secrets and hashes of recovery codes. Do not include the setup key or recovery codes in screenshots or presentations.

Setup now offers a locally generated QR code, with manual entry as a fallback. New keys contain 160 random bits encoded as 32 Base32 characters, using SHA-1, six digits and a 30-second period. Scan the current QR code into a new FarSell entry instead of reusing an old unconfirmed entry. Repeated setup clicks retain the same active key. Old unconfirmed 103-character setups are retired when the setup page is refreshed; enabled authenticators retain their existing secret. A hidden setup identifier rejects stale browser-tab submissions. Compatibility tests check published RFC 6238 vectors, provisioning settings, QR image generation and preservation of enabled legacy keys.

Future admin sessions must pass an authenticator or unused recovery code. Used time steps and recovery codes cannot be reused. Attempts are rate limited. Replacing recovery codes invalidates all previous codes. The app has no web route to disable admin MFA. Keep phone/server clocks synchronized. A fresh admin login or remembered cookie alone does not pass the MFA gate.

`ADMIN_MFA_REQUIRED=true` is included in environment examples. The production release check fails if admin MFA or recent-confirmation protection is disabled. Ordinary business tests explicitly disable interactive challenges in their fixture setup; the dedicated account-security tests turn them on and exercise bypass attempts.

## Sensitive actions

All authenticated admin writes outside the security/sign-out/OAuth callback routes require identity confirmation within five minutes. Enrolled admins confirm with an authenticator/recovery code. An expired confirmation sends the admin to Account security before making changes; return and submit the original form again afterward. Original POST bodies and uploaded files are not replayed automatically.

Google-linked users can confirm ownership through a new OAuth exchange using the existing registered callback URL. The returned Google subject must match the signed-in account. This does not satisfy the admin MFA challenge. No additional Google redirect URL is needed. Password confirmation is also available for users with a known local password. Authenticator codes and delivery PINs are excluded from validation-error old-input storage.

## Devices and suspension

All roles can open **Edit profile → Account security & signed-in devices**. Database sessions show browser descriptions and recent activity for the current account only. **Sign out other devices** requires identity confirmation, advances the account security version and rotates its remember token. The current session remains usable; older session versions are rejected on their next request even if an in-flight request rewrites an old session row. Device listing requires database sessions; version-based revocation also works with other session drivers. An already executing request cannot be undone by revocation.

Admins open **Dashboard → Account protection**, search name/email and submit a suspension reason. Suspension revokes sessions and remembered logins, blocks password and Google sign-in and hides an owned shop. Suspended riders are excluded from delivery eligibility. Existing orders, deliveries, money records and evidence remain intact. Coordinate outstanding work before suspending a seller/rider; this does not automatically reassign shipments or refund customers. Admin accounts and self-suspension are blocked in this interface.

Restoring access advances the security version again so old sessions remain revoked. The admin may explicitly choose to make a restored seller's shop visible; otherwise it remains hidden. Users must sign in again.

## Audit trail

**Admin dashboard → Audit trail** shows actor account ID, timestamp, target and selected before/after fields for categories, product/variant stock and prices, application decisions, shop visibility, refund status/amount, roles and suspension/version changes. Security confirmation, recovery-code use/regeneration, device revocation and operator MFA recovery are also recorded. Changes made through these model/service paths are covered; this is not a database-wide change capture system. Direct SQL or bulk updates can bypass Eloquent observers.

Passwords, authenticator secrets, recovery codes, delivery PINs, request bodies and uploaded documents are excluded from these audit records. The website provides no edit/delete action and the model blocks ordinary update/delete calls. A database operator still has database privileges: use restricted DB accounts, protected backups and external log retention when hosting. These records are not cryptographically tamper-proof. No automatic retention deletion is configured.

## Photo processing

New product photos, review images, delivery proof, return evidence and rider document images are decoded and re-encoded with GD. Only JPEG, PNG and WebP are processed. Dimensions are bounded to 8,192 per side and 16 megapixels before decoding. Existing per-flow byte limits remain (4 MB, or 5 MB for rider document images). Fresh image output does not retain EXIF/GPS metadata or trailing payloads; filenames are random and private evidence stays private.

Existing stored images are not rewritten. PDF documents and review videos retain their existing validation/storage paths; they are not sanitized or malware-scanned by this image processor. Keep PHP/GD updated, enforce web-server upload limits and review private storage permissions before hosting. Photo orientation encoded only in EXIF may need manual correction before upload because metadata is removed.

Rider submissions are limited to five per minute. If one document fails after earlier uploads have been saved, the transaction rolls back and those new files are removed; previous accepted documents are retained.

## Lost phone and all recovery codes

An operator with authorized server access must independently verify the administrator identity before running:

```text
php artisan ops:reset-admin-mfa admin@example.com
```

Production/staging also requires `--confirm-production` and typing the exact requested RESET phrase. This revokes all devices, clears the authenticator, records the operator-reset event and requires enrollment at the next sign-in. It does not recover passwords or restore suspended accounts. No actual admin has been enrolled or reset automatically by this implementation.

## References

Verification on 2026-11-03: the complete 291-test backend suite passed (2,305 assertions), followed by 35 targeted tests including the additional upload-cleanup regression. All nine frontend tests, production build, asset budgets, formatting, template/route compilation and an isolated encrypted restore rehearsal passed. The rehearsal verified 43 tables without modifying the live database. The additive security migration was applied locally. Verification did not perform real admin authenticator enrollment, an operator reset or a production deployment.

- [OTPHP documentation](https://github.com/Spomky-Labs/otphp/blob/11.5.x/doc/index.md)
- [OWASP MFA guidance](https://cheatsheetseries.owasp.org/cheatsheets/Multifactor_Authentication_Cheat_Sheet.html)
- [OWASP session management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [OWASP upload guidance](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
