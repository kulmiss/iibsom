# Security check (OWASP ASVS level 2, short version)

Checked 2026-11-16 (PR 18). ✅ = done and tested, ⚠️ = owner action before launch.

| Area | What we do |
|------|-----------|
| Sign-in | ✅ Google only for customers: state check, verified email only, banned users blocked, session renewed. Staff: password + **required 2FA (TOTP)**, recovery codes, 5 tries then wait, 60 min idle timeout |
| Access | ✅ Roles + permissions for staff; every customer action checks ownership (tests open other people's ads, photos, My Ads → 404) |
| Input | ✅ Broken characters (invalid UTF-8) refused with 400 everywhere; all forms validated; text shown with escaping; Markdown pages escape HTML; links in banners must be safe; search words cleaned |
| Spam / abuse | ✅ Rate limits on every POST and on "Show number"; 5 ads/day; duplicate check; bad words; no links; bot trap; blocked phones |
| Uploads | ✅ Real image check, size limits, re-encoded (no EXIF/GPS), originals deleted, stored on the photos disk (R2), never run as code |
| Browser | ✅ Strict CSP with per-request nonce (fresh even from the page cache), X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy, COOP, HSTS on HTTPS |
| Cookies | ✅ Encrypted sessions, HttpOnly, SameSite=Lax, Secure on the live site (default when `APP_ENV=production`); CSRF on all forms |
| Privacy | ✅ **No IP for visitors who are not signed in**: no session saved for guest pages, database sessions keep no IP/browser for guests, rate limits use an HMAC of the IP (never the IP) |
| API | ✅ Read-only, no cookies, no personal data (no phone/email), rate limited, switch to turn off |
| Secrets | ✅ Never in git (gitleaks in CI); `.env` only |
| Dependencies | ✅ `composer audit`, `npm audit`, `pnpm audit` in CI: 0 known problems today |
| Logs | ✅ Sign-in and staff actions logged (security log + admin activity log); staff IP kept, guests' never |
| Errors | ⚠️ Live site: `APP_DEBUG=false`, `APP_ENV=production` |
| HTTPS | ⚠️ Live site behind Cloudflare: SSL "Full (strict)", set `TRUSTED_PROXIES=*` so HTTPS and HSTS are detected |
| Web server logs | ⚠️ Hosting access logs usually keep IPs: turn them off or shorten them in the hosting panel if possible |

Known trade-off: a page kept by Cloudflare shows the same CSP nonce to everyone for up to 2 minutes. Pages escape all
user text, so there is no place to inject a script; the server page cache always gives a fresh nonce.
