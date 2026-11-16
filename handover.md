# Handover

_Last updated: 2026-11-16 (PR 13)_

## Current state
- **PR 1 (foundation):** Laravel 13 base, Docker for local (port 8095), CI (tests on MySQL, Pint, Composer/npm audit, gitleaks, Docker build), project memory files. Public site shows a "coming soon" page.
- **PR 2 (switches + hosting):** feature switches (`config/features.php`, `feature()` helper, `@feature` Blade, `feature:` route middleware = 404 when OFF), settings (`setting()` helper), VPS/shared hosting mode (`HOSTING_MODE`), health check (`php artisan iibsom:health`), security headers + strict CSP with nonce. CI runs tests in both modes.
- **PR 3 (languages + layout):** English-only at launch, Somali-ready (`lang/so`), `/so/...` URLs, in-place language switch (no reload, cookie), hreflang/canonical, CSV export for native review, mobile-first layout (header, footer, home hero), system fonts, Windows hint in health check. Docs: docs/translations.md.
- **PR 4 (locations + categories + home):** regions → cities → areas (Somalia; only Mogadishu ON; 18 Mogadishu districts), 15 main categories / 52 sub categories with custom fields (cars, houses, land, phones, laptops, livestock, jobs), `Catalog` cached lists, Jiji-style home in Iibsom blue/yellow, Lucide icons (`icon()` helper). Seeders are production-safe and can run again.
- **PR 5 (phone home):** Jiji-mobile style tiles, one-row search, bottom bar following switches. Removed stray `dump.rdb`.
- **PR 7 (Google sign-in):** Socialite Google (state check, verified email only, banned blocked, session regenerated, language kept), welcome form (name, city, +252 phone + WhatsApp, Terms), account + profile edit, `profile.complete` middleware for posting, security log. Guide: docs/google-signin.md.
- **PR 8 (post an ad):** `ads` + `ad_photos` tables (indexes, fulltext), 2-step form (category → one form with custom fields), photos shrunk on the phone, uploaded one by one, background job (auto-rotate, re-encode = no GPS/EXIF, 3 sizes WebP + AVIF if possible) to the photos disk (R2 live), reorder, draft saved on the phone, every ad pending (decision #9), duplicate check, 5 ads/day, `iibsom:ads:expire` hourly.
- **PR 9 (Super Admin foundation):** `admin/` = shadcn-admin template (pinned, MIT, cleaned), built to `public/admin-app`, served at `/admin` with CSP nonces. Staff login (password + **required TOTP 2FA**, recovery codes, rate limits, idle timeout 60 min), roles + permissions (`config/admin.php`), dashboard API + charts, activity log, brand colour, themes, Ctrl+K, phone cards, reusable `ServerDataTable`. `php artisan iibsom:staff`. Screens for ads/users/reports/categories/etc. are placeholders → next PRs.
- **PR 10 (moderation):** admin ads list + approval queue (cover photos, detail panel, approve, reject with reason presets, edit, soft delete, bulk), users (search by phone, ban with take-down + block phone, unban), blocked phones (customers can't use them), reports queue (take ad down closes all its reports, dismiss). Live-ad counters on categories/cities kept up to date (demo never counted). Photo host allowed in CSP.
- **PR 11 (catalogue + switches + settings):** categories editor (tree, add/edit/on-off, icon picker, popular, new/used, delete only without ads), custom fields editor (text/number/list/yes-no, units, required, filter), regions/cities/areas with switches (new cities start OFF), feature switches page (grouped, search, ON/OFF counts, "what changes" confirm, reset to default), Settings → General (expiry days, ads/day, photos/ad, languages). Route names unique (route:cache works).
- **PR 12 (site content + staff + health):** pages table + public `/about /safety /terms /privacy` (Markdown, HTML escaped, EN/SO, starter text, never overwritten by seeder), admin page editor with preview, home banners (upload → 2 WebP sizes on photos disk, safe links only, dates, on/off, cached), staff management (add with one-time password, role, reset 2FA/password, remove; self + last Super Admin protected), System health page, Demo data settings (show switch recounts totals; delete all with typed DELETE).
- **PR 13 (browse + search + ad page):** `AdSearch` interface (MySQL full-text now, Meilisearch later), `/search` and `/{category}/{city?}` with price, condition, sort and custom field filters, ad page `/{category}/{city}/{slug-id}` (gallery with zoom, number shown only after a tap, WhatsApp, share, safety tips, report, similar ads), JSON-LD + Open Graph, `sitemap.xml`, `robots.txt` blocks everything outside production, home shows latest 12 ads.
- Nothing is deployed yet.
- Owner runs locally with **Laragon** on Windows (see docs/local-setup.md → A).

## How to run
See [docs/local-setup.md](docs/local-setup.md). Short version: `cp .env.example .env && docker compose up -d --build`, then open http://localhost:8095.

## Working rules (from the owner)
- Keep LATER.md (owner's checklist) up to date; show its top 5 in every "Reminders for you".
- Small PRs, tests for everything, run tests locally like CI before pushing.
- CI runs only on non-draft PRs. With stacked PRs, keep the lower ones as draft and only the top one ready.
- Never merge; the owner merges.
- Ask the owner on important questions; log answers in DECISIONS.md. Small choices: decide, and log them under "Small choices".
- Every report ends with "Reminders for you".

## What's next (planned PR order)
1. **PR 1:** Foundation
2. **PR 2:** Feature switches + settings + hosting modes + health + security headers
3. **PR 3:** Language system (English, Somali-ready, switch without reload) + base layout (mobile-first)
4. **PR 4:** Locations (Somalia regions/cities/areas, Mogadishu ON only) + data-driven categories + custom fields + Jiji-style home
5. **PR 5:** Phone home like Jiji mobile + bottom bar
6. **PR 6:** Hide city picker when only one city + roadmap "Recommended for you"
7. **PR 7:** Google sign-in + first-time profile (name, city, +252 phone, terms)
8. **PR 8:** Post an ad (photos → R2 via queue, drafts offline)
9. **PR 9:** Super Admin foundation: staff login + 2FA + roles, dashboard, activity log, themes, brand colour 
10. **PR 10:** Admin: ads approval queue + all ads, users (ban, block phone), reports
11. **PR 11:** Admin: categories + fields editor, cities/areas, feature switches, settings
12. **PR 12:** Admin: pages, banners, staff, system health, demo data controls
13. **PR 13:** Browse, search (DB full-text behind an interface), ad page, SEO (this one)
14. My Ads (edit, sold, renew, delete), reports, safety (bad words, honeypot, block phone)
15. "Recommended for you" (browsing history in the browser, similar ads)
16. Demo data with real photos (`iib:demo-reset`), deploy guides (shared + VPS), Cloudflare R2, scaling docs

## Known notes
- Public pages currently set an XSRF/session cookie; the page-cache PR must make guest pages cookie-free so Cloudflare can cache them.
- The local PHP Docker image could not be built inside Claude's sandbox (the Alpine package site is blocked there). CI builds it on GitHub to prove it works.

## Before going live (open questions in DECISIONS.md)
- C, H1, H2, H3: host details (panel, PHP version, SSH/cron, what else uses iibsom.com).
