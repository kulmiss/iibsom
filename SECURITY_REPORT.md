# iibsom security report

**Date:** 2026-11-17 · **Code:** all PRs up to PR 45 · **Standard:** OWASP ASVS 4.0, level 2 · **Done by:** Claude (AI developer), for the owner.

This is a check of the code, not of the live server (the site is not live yet). Steps that need the owner are marked ⚠️.

## In short

- **No serious problem found.** 4 small problems were found and **fixed in PR 45**, each with a test.
- Every Phase 2/3 feature (ID photos, payments, chat, ratings, API keys, auto-post…) was looked at again.
- Tools: 0 known weak packages (`composer audit`, `npm audit`, `pnpm audit`). 0 secrets in git (gitleaks, whole history). 0 Larastan errors (level 8).
- **Before going live** you must do the ⚠️ steps at the end.

## Fixed in PR 45

| # | Problem | Risk | Fix | Test |
|---|---------|------|-----|------|
| 1 | A **HEAD** request (bots send many) to a guest page started a session: a database row and 2 cookies each time. | Low: filling the sessions table; goes against "nothing saved for guests". | HEAD is handled like GET for guests (no session). It is never stored in or read from the page cache, because a HEAD answer has no body. | `GuestPrivacyTest::test_head_requests_are_cookie_free_and_never_empty_the_cache` (failed before) |
| 2 | Laravel's built-in `/storage/{path}` routes were on for the **private** disk (ID photos, receipts). They only work with a signed link, and the site never makes one. | Low: extra way in that is not needed. | `serve => false` for the private disk: no web address at all. | `SecurityAuditTest::test_private_files_have_no_web_route` (failed before) |
| 3 | 2 changing routes had no rate limit: sign out, and "remove a photo while posting". | Low. | Rate limits added. A test now fails if any new changing route has none. | `SecurityAuditTest::test_every_changing_customer_route_has_a_rate_limit` (failed before) |
| 4 | Terms/Privacy links on the welcome form opened a new tab without `rel="noopener"`. | Very low (new browsers do it anyway). | Added. | — |

## ASVS level 2, area by area

✅ = checked and OK (with tests where it can be tested) · ⚠️ = owner step · ➖ = not used by iibsom

| ASVS area | Result | Notes |
|---|---|---|
| V1 Design | ✅ | One Laravel app; admin API under `/admin/api`; feature switches turn whole parts off (404). Shared hosting and VPS run the same code. |
| V2 Sign-in | ✅ | Customers: Google only (state check, verified email, banned refused, new session). Staff: password + **required 2FA** (TOTP), recovery codes, wrong codes slowed down, failed sign-ins logged (email hashed). API: personal keys, only a SHA-256 hash stored, 1 year, max 5, can be deleted at once. |
| V3 Sessions | ✅ | Encrypted, HttpOnly, SameSite=Lax, Secure on the live site, new session at sign-in, staff signed out after 60 min idle. Guests get **no session** (now also for HEAD). The API never uses cookies. |
| V4 Access control | ✅ | Staff: roles + permissions on **every** admin API route (test checks none is missing). Customers: every action checks the owner; someone else's ad, photo, chat, key, rating or payment answers 404 (tests for each). |
| V5 Input and output | ✅ | All input validated; text shown escaped; Markdown escapes HTML and unsafe links; JSON-LD escaped; chat and ratings plain text; bad UTF-8 refused; attack inputs (script, SQL, `../`) tested. Raw SQL only with fixed text or bound values (checked one by one). |
| V6 Cryptography | ✅ | Laravel `Crypt` (AES-256) for ID photos and receipts; bcrypt for staff passwords; `hash_equals` for API keys. ⚠️ Keep `APP_KEY` safe and never change it on the live site (waiting ID photos could no longer be read). |
| V7 Errors and logs | ✅ / ⚠️ | Security log (sign-ins, keys made/deleted), admin activity log (every staff action), no keys or tokens in logs (auto-post errors are cleaned). ⚠️ `APP_DEBUG=false` live (System health shows a red line if not). |
| V8 Data protection | ✅ | No IP kept for guests; rate limits use an HMAC of the IP; phone numbers only after a tap; ID photos deleted after the decision; staff cannot read chats; ratings show the buyer's first name only; `no-store` on the API key page. |
| V9 Communication | ⚠️ | HSTS is sent on HTTPS. ⚠️ Cloudflare SSL "Full (strict)", `TRUSTED_PROXIES` set. |
| V10 Bad code | ✅ | No secrets in git (gitleaks in CI); dependencies checked in CI; the admin template is pinned. |
| V11 Business logic | ✅ | Daily ad limit (website + API), duplicate ads, one payment per transaction ID (database key), boosts approved in a locked transaction (tested: 3 at once → 1 OK), double-entry money book with a check command, one rating per chat, auto-post once per ad. |
| V12 Files | ✅ | Real image check, size limits, every image re-encoded (no hidden data), private files encrypted and with no web address, photos on R2 (not on the app server). |
| V13 API | ✅ | Read part: no personal data. Write part: switch OFF by default, Bearer key, read/write rights, same checks as the website, rate limits with their own counters. |
| V14 Settings | ✅ / ⚠️ | Strict CSP with a fresh nonce on every page (also from the page cache), frame-ancestors none, nosniff, Referrer-Policy, Permissions-Policy, COOP. |

## Accepted risks (known and OK for now)

- **`style-src 'unsafe-inline'`** in the CSP: needed for a few inline styles (chart bars). Scripts are fully locked (nonce + strict-dynamic), so this does not allow running code.
- **Ad stats beacon has no CSRF token** (by design, so guest pages stay cookie-free). The worst a fake call can do is add 1 to a number only the seller sees; it is rate-limited.
- **Cloudflare cache** may show the same CSP nonce to everyone for up to 2 minutes (pages escape all user text, so there is nowhere to inject a script).
- **Telegram** puts the bot key in the request address (that is how Telegram works). It is only sent to api.telegram.org over HTTPS and is removed from any error we save.
- **Admin times** are shown in the staff member's own browser time zone.

## ⚠️ Owner steps before going live

1. Make the GitHub repository **private** (also in LATER.md).
2. `.env` on the server: `APP_ENV=production`, `APP_DEBUG=false`, a new `APP_KEY` (made once, kept safe, backed up), `TRUSTED_PROXIES` when behind Cloudflare.
3. Cloudflare: SSL "Full (strict)", "Always use HTTPS".
4. Make your Super Admin with `php artisan iibsom:staff` and set up 2FA at once; give staff their own accounts (never share).
5. Turn off or shorten the web server's access logs in the hosting panel (they keep visitor IPs).
6. Daily backups of the database and `storage/app/private` (keep them encrypted, off the server).
7. Check **Admin → System health** shows no red lines.

## How to check again

```
php artisan test                 # 520+ tests, incl. SecurityAuditTest and GuestPrivacyTest
vendor/bin/phpstan analyse       # Larastan level 8
composer audit && npm audit --omit=dev && (cd admin && pnpm audit --prod)
gitleaks detect -c .gitleaks.toml
```

More detail per feature: `docs/security.md`.
