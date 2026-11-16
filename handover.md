# Handover

_Last updated: 2026-11-16 (PR 25)_

## Current state
- **PR 1 (foundation):** Laravel 13 base, Docker for local (port 8095), CI (tests on MySQL, Pint, Composer/npm audit, gitleaks, Docker build), project memory files. Public site shows a "coming soon" page.
- **PR 2 (switches + hosting):** feature switches (`config/features.php`, `feature()` helper, `@feature` Blade, `feature:` route middleware = 404 when OFF), settings (`setting()` helper), VPS/shared hosting mode (`HOSTING_MODE`), health check (`php artisan iibsom:health`), security headers + strict CSP with nonce. CI runs tests in both modes.
- **PR 3 (languages + layout):** English-only at launch, Somali-ready (`lang/so`), `/so/...` URLs, in-place language switch (no reload, cookie), hreflang/canonical, CSV export for native review, mobile-first layout (header, footer, home hero), system fonts, Windows hint in health check. Docs: docs/translations.md.
- **PR 4 (locations + categories + home):** regions → cities → areas (Somalia; only Mogadishu ON; 18 Mogadishu districts), 15 main categories / 52 sub categories with custom fields (cars, houses, land, phones, laptops, livestock, jobs), `Catalog` cached lists, Jiji-style home in Iibsom blue/yellow, Lucide icons (`icon()` helper). Seeders are production-safe and can run again.
- **PR 5 (phone home):** Jiji-mobile style tiles, one-row search, bottom bar following switches. Removed stray `dump.rdb`.
- **PR 7 (Google sign-in):** Socialite Google (state check, verified email only, banned blocked, session regenerated, language kept), welcome form (name, city, +252 phone + WhatsApp, Terms), account + profile edit, `profile.complete` middleware for posting, security log. Guide: docs/google-login.md.
- **PR 8 (post an ad):** `ads` + `ad_photos` tables (indexes, fulltext), 2-step form (category → one form with custom fields), photos shrunk on the phone, uploaded one by one, background job (auto-rotate, re-encode = no GPS/EXIF, 3 sizes WebP + AVIF if possible) to the photos disk (R2 live), reorder, draft saved on the phone, every ad pending (decision #9), duplicate check, 5 ads/day, `iibsom:ads:expire` hourly.
- **PR 9 (Super Admin foundation):** `admin/` = shadcn-admin template (pinned, MIT, cleaned), built to `public/admin-app`, served at `/admin` with CSP nonces. Staff login (password + **required TOTP 2FA**, recovery codes, rate limits, idle timeout 60 min), roles + permissions (`config/admin.php`), dashboard API + charts, activity log, brand colour, themes, Ctrl+K, phone cards, reusable `ServerDataTable`. `php artisan iibsom:staff`. Screens for ads/users/reports/categories/etc. are placeholders → next PRs.
- **PR 10 (moderation):** admin ads list + approval queue (cover photos, detail panel, approve, reject with reason presets, edit, soft delete, bulk), users (search by phone, ban with take-down + block phone, unban), blocked phones (customers can't use them), reports queue (take ad down closes all its reports, dismiss). Live-ad counters on categories/cities kept up to date (demo never counted). Photo host allowed in CSP.
- **PR 11 (catalogue + switches + settings):** categories editor (tree, add/edit/on-off, icon picker, popular, new/used, delete only without ads), custom fields editor (text/number/list/yes-no, units, required, filter), regions/cities/areas with switches (new cities start OFF), feature switches page (grouped, search, ON/OFF counts, "what changes" confirm, reset to default), Settings → General (expiry days, ads/day, photos/ad, languages). Route names unique (route:cache works).
- **PR 12 (site content + staff + health):** pages table + public `/about /safety /terms /privacy` (Markdown, HTML escaped, EN/SO, starter text, never overwritten by seeder), admin page editor with preview, home banners (upload → 2 WebP sizes on photos disk, safe links only, dates, on/off, cached), staff management (add with one-time password, role, reset 2FA/password, remove; self + last Super Admin protected), System health page, Demo data settings (show switch recounts totals; delete all with typed DELETE).
- **PR 13 (browse + search + ad page):** `AdSearch` interface (MySQL full-text now, Meilisearch later), `/search` and `/{category}/{city?}` with price, condition, sort and custom field filters, ad page `/{category}/{city}/{slug-id}` (gallery with zoom, number shown only after a tap, WhatsApp, share, safety tips, report, similar ads), JSON-LD + Open Graph, `sitemap.xml`, `robots.txt` blocks everything outside production, home shows latest 12 ads.
- **PR 14 (My Ads + safety):** `/account/ads` tabs (live, waiting, rejected with reason, sold/ended), edit (same form, photos kept/reordered/removed, back to approval), mark sold, renew (expired or ending in 7 days), soft delete. `SpamGuard`: bad words (admin list), no links, bot trap. Switches: `ads.my_ads`, `safety.*`.
- **PR 15 (Recommended for you):** `for-you.js` remembers viewed ads/categories in localStorage only; home asks `/for-you?c[]=&v[]=` (validated, nothing stored) and shows "Recommended for you" + "Recently viewed"; clear button; switch `recommendations`. Language swap fires `page:swapped`.
- **PR 16 (demo data):** `php artisan iib:demo-reset` (refuses in production) → `DemoSeeder`: 324 ads, 64 users, 18 reports, 2 demo staff with 2FA, 110 days of history, real free-licence photos from `database/demo/photos` (`tools/download-demo-photos`, CREDITS.md). `DemoData::deleteAll()` used by admin. Guide: docs/demo-data.md.
- **PR 17 (Somali):** all site texts in Somali (`lang/so`: site, account, ads, reports, validation, pagination), Somali names for categories/fields/places (`database/seeders/data/somali.php`) and the 4 pages. Seeders only add missing languages (admin edits are safe). Somali stays OFF until a native speaker checks it. Test fails if a new English text has no Somali text.
- **PR 18 (speed + security):** `GuestPages` middleware (guest pages: no session saved, no cookies, `Cache-Control: public`), server page cache (switch `performance.page_cache`, cleared on every change, fresh CSP nonce), `PrivateDatabaseSessionHandler` (no guest IP), HMAC-IP rate limits, read-only `/api/v1` (switch `api`), switch-crawl test, query budgets, secure-cookie default, `TRUSTED_PROXIES`. Docs: performance.md, security.md.
- **PR 19 (docs):** deploy-shared.md, deploy-vps.md, cloudflare-r2.md, scaling.md, google-login.md (renamed), README, `tools/make-release` (upload zip for shared hosting, AWS SDK trimmed to S3), 15-minute check in TESTING_GUIDE. **Phase 1 is complete.**
- **PR 20 (quality gates):** Larastan level 8 = 0 errors (`vendor/bin/phpstan analyse`), Lighthouse in CI (`bash tools/lighthouse`), PR checklist template. Fixed: broken-UTF-8 input crashed posting (now 400 everywhere); admins could not remove a Somali city/area name; admin sign-in page had failed requests (favicon path, `/me` 401 → `/session`); ad page: preload matched the wrong image format, contrast and photo-button labels.
- **PR 21 (Edited badge):** `AdSnapshot` saves the approved version; the admin queue shows "Edited" and the ad panel shows what changed (title, description, price, condition, place, fields, photos). Switch `ads.edit_changes`. Demo data has 6 edited examples.
- **PR 22 (matching demo photos):** photo words per demo title (`database/demo/content.php`), photos by kind, rejected list, brand follows title. Fixed: ads without photos showed a tag icon instead of their category icon on category/search pages.
- **PR 23 (weekly email):** `iibsom:weekly-summary` (Monday 07:00 Mogadishu, once per week), `WeeklySummary` numbers, admin field + test button. Fixed: shared rate-limit counter across pages (posting could be blocked after photo uploads); app name lowercase.
- **PR 24 (SEO landing pages):** `browse.brand` route + `Landing` helper (brands, top brands, other cities), better titles/breadcrumbs/JSON-LD, empty pages noindex, sitemap landing URLs. Fixed: active language pill had too little contrast. Lighthouse now also checks a brand page and /so.
- **PR 25 (installable website):** `public/manifest.webmanifest`, `public/icons/*`, `public/sw.js` (VERSION constant: bump it to drop old caches), `public/offline.html`, `resources/js/pwa.js`; Apache/nginx headers for sw.js and /build.
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
13. **PR 13:** Browse, search (DB full-text behind an interface), ad page, SEO
14. **PR 14:** My Ads + safety
15. **PR 15:** "Recommended for you"
16. **PR 16:** Demo data with real photos
17. **PR 17:** Somali translations
18. **PR 18:** Speed + security check
19. **PR 19:** Docs + release zip
20. **PR 20:** Quality gates.
21. **PR 21:** "Edited" badge.
22. **PR 22:** Demo photos that match each title.
23. **PR 23:** Weekly summary email.
24. **PR 24:** SEO landing pages.
25. **PR 25:** Installable website / PWA.
26. **PR 26:** Somali review sheet with "where" + phone pictures.
27. **PR 27:** Help centre for customers + help email setting; fixes: category address clashes, tests vs real DB, page cache after updates, brand page cookies.
28. **PR 28:** Super Admin guide: "?" on every admin page, Guide page, print + PDF.
29. **PR 29:** Welcome tour (3 slides) after the first sign-in.
30. **PR 30:** First-time setup wizard for the Super Admin. Part A done.
31. **PR 31:** Favourites / Saved ads (Phase 2, OFF) (this one). Next: Part B (Phase 2, OFF), Part C (Phase 3), Part D review — owner's big job list of 2026-11-16.

## Known notes
- The local PHP Docker image could not be built inside Claude's sandbox (the Alpine package site is blocked there). CI builds it on GitHub to prove it works.

## Before going live (open questions in DECISIONS.md)
- C, H1, H2, H3: host details (panel, PHP version, SSH/cron, what else uses iibsom.com).
