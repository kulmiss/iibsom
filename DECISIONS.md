# DECISIONS

Every question, the owner's answer, and the date. Newest at the bottom.

| # | Date | Question | Answer |
|---|------|----------|--------|
| 1 | 2026-11-16 | Site name? | **Iibsom** (domain check for .com / .so still to do) |
| 2 | 2026-11-16 | Countries at launch? | **Somalia only** (focus: Mogadishu). Region switches built so others can be added later. |
| 3 | 2026-11-16 | Currency? | **USD only.** |
| 4 | 2026-11-16 | Where does the code live? | Owner creates a **private GitHub repo** and gives access. |
| 5 | 2026-11-16 | Admin address? | **Separate subdomain** (admin.iibsom.com), own login + 2FA. |
| 6 | 2026-11-16 | Phone numbers? | Accept **+252 only** at launch; stored in one format (E.164). |
| 7 | 2026-11-16 | Cities? | **Somalia cities only.** |
| 8 | 2026-11-16 | Categories? | Products **plus Services and Jobs** in Phase 1. |
| 9 | 2026-11-16 | Ad approval? | **Every ad must be approved by an admin.** (No auto-trust at launch; the "trusted user" switch exists but is OFF.) |
| 10 | 2026-11-16 | Languages? | **English only at launch.** Built so Somali (and later Arabic) can be added easily, and switching language must happen **without a page reload**. |

| 11 | 2026-11-16 | Which cities ON at launch? | **Only Mogadishu ON.** All other Somalia cities exist but are OFF; admin can switch each on. Somaliland cities not added. |
| 12 | 2026-11-16 | Domain? | Owner **already owns iibsom.com** (currently on shared hosting). Site itself will run on VPS + Cloudflare. |
| 13 | 2026-11-16 | VPS or shared hosting? | **Both.** Same code; a hosting mode in `.env` (`HOSTING_MODE=vps` or `shared`) switches the fallbacks. Deploy guides for both, tests in both modes, admin health page. Replaces the earlier "NOT shared hosting" line. |
| 14 | 2026-11-16 | Payments? | **No payments through the system** (site like jiji.com). Paid extras (boosts, shop plans, banner ads) are paid to the owner outside the site (EVC/Zaad etc.); an **admin activates** them in the admin panel. No payment code. |
| 15 | 2026-11-16 | Homepage style? | **Inspired by jiji.com layout** (big search with location picker, category list with icons + ad counts, popular categories, trending ads) but in **Iibsom's own blue + yellow**. Built in PR 4 with categories. |
| 16 | 2026-11-16 | Phone home page? | **Like Jiji mobile:** location + search on one row, 4-column category tiles starting with "Post ad" and "Trending", bottom bar (Home, Saved*, Sell, Messages*, Profile; *only when switched on). Icons instead of photos. |
| 17 | 2026-11-16 | Remember what people browse? | **Yes**: "Recommended for you" with similar ads (like Jiji). Guests: history stays in their browser only (privacy, cacheable pages). Planned as PR 10. |
| 18 | 2026-11-16 | City picker with one city? | **Hide it** when only one city is ON; it appears automatically when a 2nd city is switched on. |
| 19 | 2026-11-16 | Updated brief arrived (regions all three, USD+SLSH, Somali first, approve only first 2 ads, payments in Phase 2). Which wins? | **Stay with the earlier answers:** Somalia only (Mogadishu ON), USD only, English first (Somali-ready), every ad approved, no payments in the system. Everything else in the updated brief applies (LATER.md, HOSTING_MODE, admin template + themes, demo data with real photos, deploy guides, iib:demo-reset). |

## Open questions (must be answered before going live)

| # | Asked | Question | Status |
|---|-------|----------|--------|
| C | 2026-11-16 | Is anything else using iibsom.com on the shared hosting now (email, old website)? | Waiting |
| H1 | 2026-11-16 | Which control panel does the host use (cPanel, Plesk, DirectAdmin…) and who is the host? | Waiting |
| H2 | 2026-11-16 | Which PHP versions can be chosen on the host? (8.3+ needed) | Waiting |
| H3 | 2026-11-16 | Is there SSH access, and can cron jobs be added? | Waiting |
| S1 | 2026-11-16 | Phone + SMS login (switch OFF for later): which SMS provider? (e.g. Hormuud/Somtel SMS gateway, Africa's Talking, Twilio) | Waiting, not needed for launch |

## Small choices (taken by Claude, can be changed)

| Date | Choice |
|------|--------|
| 2026-11-16 | Ad expiry default 60 days; post limit 5 ads/day per user. |
| 2026-11-16 | Versions: Laravel 13 (latest stable), PHP 8.3, MySQL 8.4 LTS, Redis 7. Tests run on MySQL like production. |
| 2026-11-16 | Local ports: site 8095, Mailpit 8096, MySQL 33095. |
| 2026-11-16 | Shared hosting mode defaults: database for cache/sessions/queue, cron runs the queue every minute (`--stop-when-empty`), GD for images if Imagick is missing, database search only. |
| 2026-11-16 | Feature switches: every feature has a key in `config/features.php` with a default; Super Admin changes are stored in the database. Phase 2/3 features default OFF. OFF = 404 on its routes. |
| 2026-11-16 | Repo is public for one day (2026-11-16) for CI testing; owner makes it private on 2026-11-17. |
| 2026-11-16 | System fonts only (no web fonts): faster on slow 3G, nothing extra to download. |
| 2026-11-16 | URLs: default language (English) has no prefix; other languages get a prefix (`/so/...`). Choice saved in a cookie; the page swaps language in place without a reload. |
| 2026-11-16 | Logo text is lowercase "iibsom" (with a capital I it looks like "libsom"). Brand colours: Somali-flag blue + warm yellow. |
| 2026-11-16 | Phone numbers: Somali mobiles only (start 6/7/9, 9 digits), typed any way (0615…, +252 61…), saved as +252XXXXXXXXX. "I use WhatsApp on this number" is ON by default. |
| 2026-11-16 | Sign-in events go to `storage/logs/security.log` (90 days) until the admin activity log exists. |
| 2026-11-16 | Language switch without reload: page HTML is fetched for the new language in the background and swapped in; the chosen language is saved in a cookie. Each language keeps its own cached pages and URL prefix for SEO. |
| 2026-11-16 | Edits go back to the approval queue (decision #9 covers edits). Category can't be changed on edit (post a new ad instead). Sold ads can't be edited. |
| 2026-11-16 | Renew: allowed for expired ads and live ads ending within 7 days; renew does NOT need approval again (text unchanged). |
| 2026-11-16 | Delete from My Ads = soft delete (staff can still see it for reports/fraud). |
| 2026-11-16 | Spam: bad-word list (admin editable, whole words, look-alike letters), no web links in ads, hidden bot-trap field, plus the existing limits (5 ads/day, duplicates, rate limits, blocked phones). Each has its own switch. Starter list is short English; owner adds Somali words. |
| 2026-11-16 | "Recommended for you": history is kept in the browser only, for guests AND signed-in users (no server history at all: simpler, private, pages stay cacheable). Last 30 ads + top 20 categories; ad views count double. "Clear my history" button. |
| 2026-11-16 | Demo photos: Pexels/Unsplash need an API key, so the tool uses them when `PEXELS_API_KEY`/`UNSPLASH_ACCESS_KEY` is set, otherwise Openverse (CC0 / Public Domain / CC BY only, credited in CREDITS.md). 92 photos (12 MB) are committed so nobody has to download them; photos with people or children were removed by hand. |
| 2026-11-16 | Demo data: 324 ads, 64 users, 18 reports, 2 demo staff (moderator + support) with 2FA; same result every run (fixed seed). "Delete all demo data" now also removes demo staff (never the person deleting). Each photo is resized once and shared by the ads that use it. |
| 2026-11-16 | Somali texts written by Claude for every screen, catalogue and page; marked "needs native review". Somali stays OFF (decision #10) until the owner turns it on after review. Admin panel stays English (staff only). A test makes sure every new English text also gets a Somali text. |
| 2026-11-16 | Seeders now only ADD missing names/languages; they never overwrite names an admin edited (before, they reset names to English). |
| 2026-11-16 | Guest pages (home, category, search, ad, pages) send no cookies and save no session; server page cache 2 min for clean URLs only (filters/search queries are not kept), cleared on any change. Switch `performance.page_cache` ON. |
| 2026-11-16 | Rate limits for guests use HMAC(APP_KEY, IP), never the IP; database sessions keep IP/browser only for signed-in users. |
| 2026-11-16 | `/api/v1` is read-only and ON by default (switch `api`): categories, cities, ads list/detail; no phone numbers or emails; 60 requests/minute. |
| 2026-11-16 | Session cookies are encrypted by default and Secure on production by default. `TRUSTED_PROXIES` in .env for Cloudflare. |
| 2026-11-16 | Shared hosting deploy = upload a zip made by `tools/make-release` on the owner's PC (no Node/Composer needed on the host). AWS SDK trimmed to S3 only (zip ~40 MB). |
| 2026-11-16 | Final merge: the top PR (#19) is based on `main`, so one merge brings PRs 7–19. The stacked PRs #7–#18 can then be closed. |
| 2026-11-16 | Quality gates (owner's "zero mistakes" rules): Larastan **level 8** with zero errors and no baseline or ignore rules; Lighthouse in CI (phone): performance ≥ 85, accessibility/best-practices/SEO ≥ 95/95/90 ("is-crawlable" skipped: only the live site may be indexed); PR template with the 1–9 checklist. Level 9 (strict "mixed") not used: it would need casts everywhere for Laravel's untyped request data. |
| 2026-11-16 | Text with broken characters (invalid UTF-8) is refused with 400 for every request (it crashed posting before). |
| 2026-11-16 | Admin app asks `GET /admin/api/session` first (200 with `user: null` when signed out), so the sign-in page has no failed request in the console. |
| 2026-11-17 | "Edited" badge: the approved version of each ad is saved on approval (`approved_snapshot`); an ad waiting again after the seller changed it shows "Edited" + old/new side by side. Old approved ads were filled in by the migration. Staff edits of a live ad count as approved. Switch `ads.edit_changes` (ON). |
| 2026-11-17 | Demo photos match each title: every demo title has photo words ("toyota land cruiser", "goats"); an ad only gets photos of its own kind, otherwise none (services/jobs and 28 titles with no good free photo). 164 photos, 800 px, 11 MB, each checked by eye: no people close-up, never children. Rejected photos are listed in database/demo/photos-rejected.json so the tool never takes them again. Demo brands follow the title (iPhone → Apple) or "Other", never a random wrong brand. |
| 2026-11-17 | Weekly summary email: Monday 07:00 Mogadishu time, last week Monday→Monday (Mogadishu), to up to 5 addresses in Settings → General (empty = none). Never sent twice for the same week. Approvals counted from the activity log. "Send me a test now" in the admin. Switch `reports.weekly_email` (ON; does nothing until an address is saved). English (it is for the owner). |
| 2026-11-17 | Rate limits now have one counter per page (Laravel shared one counter per user across all pages: uploading 12 photos blocked posting). The same page in Somali shares the English counter. |
| 2026-11-17 | App name is lowercase "iibsom" everywhere (emails, page titles): a capital I looks like "l". |
| 2026-11-17 | SEO landing pages: category × city pages get "Cars in Mogadishu" titles, breadcrumbs (+ BreadcrumbList data) and links; brand pages at /cars/mogadishu/toyota (brand words have no digits, ad addresses end in digits, so they never clash). Pages with 0 ads, filters or page 2+ are "noindex". Sitemap lists category×city and brand pages that have live ads. Switch `seo.landing_pages` (ON). |
| 2026-11-17 | Installable website (PWA): manifest + app icons (blue "ii" with the yellow dot) + service worker that NEVER stores web pages (only the offline page, icons and ad photos, max 80). Offline: a small English + Somali page. /build files rely on the browser cache (1-year header) instead of the worker. Switch `pwa` (ON); OFF removes the worker from phones on their next visit. No "Install" button of our own: phones offer "Add to home screen". |
