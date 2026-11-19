# Handover

_Last updated: 2026-11-19 (PR 84)_

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
- **PRs 26–30 (Part A):** Somali review sheet, Help centre, Super Admin guide + PDF, welcome tour, setup wizard.
- **PRs 31–38 (Part B, Phase 2, all OFF):** favourites, saved searches, ad stats, verified badge, shop pages, paid boosts (test mode, ledger), banner ads, chat.
- **PRs 39–43 (Part C, Phase 3, all OFF):** write API + keys, price guide, seller ratings, auto-post, help for buyers abroad.
- **PRs 44–47 (Part D):** bug hunt (Somalia-time dates), security audit (SECURITY_REPORT.md), load test 100k ads (docs/load-test.md), docs.
- **PRs 56–62 (checklist run):** 43 checks run in a real browser, 6 bugs fixed (saved-search filters, guest-report time stamp, friendly error pages, phone grid/list, staff Settings, local test sign-in), admin build tools dev-only, one-line home panel.
- **PRs 63–68 (Jiji round, docs/jiji-study.md):** PR 63 search suggestions (`search.suggestions`), recent searches (on the phone only), search in the header of every page (`site.header_search`). Terms table `search_terms` rebuilt nightly by `iibsom:search-terms` and by demo reset/delete. PR 64 Save button on cards (`ads.card_save`; state from `<meta name="card-save">`, card-save.js), New/Used + list-view text (`ads.card_details`), "Show more ads" (`lists.show_more`, show-more.js, fires `cards:added`), "Popular now" (`home.popular_now`, `App\Support\Ads\PopularNow`), demo views (DemoSeeder::stats). PR 65 Body type (Cars) + RAM (Mobile Phones) via `CategorySeeder::addMissing()`, public views on ad pages (`ads.public_views`), Make an offer (`chat.offers`, `?offer=` fills the chat box), chat box grows. PR 66 notifications bell (`notifications`; `inbox_items`, `App\Support\Notify\Inbox`, settings in `users.notify_settings`), price drop (`favourites.price_drop`, `favourites.price_when_saved`), call back (`contact.call_back`, CallBackMail), nightly prune. PR 67 seller pages (`sellers.page`, SellerPageController, noindex, cached for guests), follow (`sellers.follow`, table `follows`, `Inbox::followedAd` on first publish), rename/pause saved searches (`saved_searches.manage`, `saved_searches.muted`). PR 68 close with a reason (`ads.close_reason`, `ads.closed_reason`), free move to top every 7 days (`ads.free_bump`, `ads.bumped_at`; alerts skip bumped ads). PR 69 help articles posting-rules + how-to-buy (`help.posting_rules`, `help.how_to_buy`), contact form (`site.contact_form`, ContactController stateless, table `contact_messages`, admin Messages, permission `messages.manage`). PR 70 security check: Save/Follow use insertOrIgnore (double taps), call requests use a short lock; checklist rows 25 and 39 updated. PR 71 speed check at 100k ads (docs/performance.md), index `ads(status, views)` for Popular now. PR 72 no drugs: `SpamGuard::drugWord` (setting `safety.drug_words`, switch `safety.no_drugs`), report reason `drugs`, `ads.flag`/`flag_word`, admin filter `flag=drugs`, command `iibsom:drugs:check`. PR 73 chat drug check (`ChatController::send`, `users.flag*`, admin Users state `drugs`, clear-flag).
- Which switch does what, and what to turn on first: **docs/features.md**.
- Nothing is deployed yet.
- Owner runs locally with **Laragon** on Windows (see docs/local-setup.md → A).
- **PR 74 (visitor side: audit + design system):** docs/ux-audit.md (scores + screenshots), dark mode (`site.dark_mode`; `resources/js/theme.js`, head script sets `<html data-theme>`; dark colours in one block of `resources/css/app.css`: use normal classes, `bg-white` = `var(--surface)`), `<x-empty-state>` component, friendly 404, card title lines + no-photo box, `.skeleton-card`, one focus ring, Sell button in normal case.
- **PR 75 (rich home):** `App\Support\Home\HomeSections` (popular searches, places, facts, featured, trusted sellers; 10-minute cache keyed by the page-cache version), `partials/home-more.blade.php`, install card in `resources/js/pwa.js`. 7 new switches (`home.hero_plus`, `home.places`, `home.how_it_works`, `home.trust_strip`, `home.featured`, `home.trusted_sellers`, `pwa.install_prompt`).
- **PR 76 (rich ad page):** `resources/js/ad-page.js` (shared `reveal()` for the number, contact bar, thumbnails, full-screen viewer `[data-viewer]`, read more), `AdPageController` (`fromSeller`, `following`, `FIELD_ICONS`), for-you.js `data-exclude`. 5 switches: `ads.gallery_plus`, `ads.sticky_contact`, `ads.read_more`, `ads.more_from_seller`, `ads.recently_viewed`.
- **PR 77 (category + search):** `browse/_quick-bar.blade.php`, `Landing::priceRange()`, `fallback` + `intro` in `BrowseController::results()`, drop-down closing in `filters.js`. Switches `search.quick_bar`, `search.rich_empty`, `seo.intro`.
- **PR 78 (post an ad):** `ads/_post-steps.blade.php`, category search + `initPreview()` in `resources/js/post-ad.js` (also loaded on the choose page), share on the done page via `copy-text.js` (`[data-share] [data-copy-link]`). Switches `post.steps`, `post.category_search`, `post.tips`, `post.preview`, `post.done_plus`.
- **PR 79 (account, seller, safety):** `App\Support\Users\AccountSummary`, dashboard in `account/index.blade.php`, seller page filters in `SellerPageController` (`c`, `sort`), help article `resources/help/{en,so}/5-how-we-keep-you-safe.md` (`:checked_line` filled in `HelpCentre::fill()`). Switches `account.dashboard`, `sellers.page_plus`, `help.how_we_keep_safe`.
- **PR 80 (speed + accessibility):** `<html class="js">` set in `<head>` (use `hidden [.js_&]:block` for JavaScript-only parts), `$eager` on `ads._card`, dark-mode colour fixes at the end of `app.css`, mod_deflate in `public/.htaccess`. Lighthouse/axe numbers in docs/performance.md. To measure like production locally, put a gzip front before `php artisan serve`.
- **PR 81 (report without profile):** the `ad.report` route moved out of the `profile.complete` group in `routes/web.php` (still `auth`). Test: `ReportWithoutProfileTest`.
- **PR 82 (ad page extras):** `PostAdController::likeThis()` (`?like=`), `url` in `AdPageController::fieldRows()`, mark-sold + details-more in `resources/js/ad-page.js`. Switches `ads.post_like_this`, `ads.field_links`, `ads.mark_unavailable`, `ads.details_more`.
- **PR 83 (usual price):** `AdPageController::marketPrice()` (uses `PriceHint::for()`, now one query), box under the price in `ads/show.blade.php`, texts `ads.market.*`. Switch `ads.market_price`.
- **PR 84 (finder, phone ad bar, discover, saved count):** `CategoryFinderController` + `browse/categories.blade.php` + `resources/js/finder.js` (`/categories`, switch `catalog.finder`). Phone ad bar: the ad page fills `@section('phone_bar')`; `partials/header.blade.php` shows it on phones and hides the normal row (`ads.phone_top_bar`); Share = `[data-share-sheet]` in `copy-text.js`; compact Save = `ads/_save-button` with `compact`. `AdPageController::discoverMore()` (`ads.discover_more`). Saved count: `$savedCount` in the layout, `partials/saved-count.blade.php`, `resources/js/saved-count.js` (listens to `favourite:changed`, sent by `favourite.js` and `card-save.js`). **Language switch rule:** a page with its own script must carry `<meta name="page-script" content="…">` (after its `@vite`), so the switch reloads it fully; tested in `PageScriptReloadTest`.

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
31. **PR 31:** Favourites / Saved ads (Phase 2, OFF).
32. **PR 32:** Saved searches + daily email alerts (Phase 2, OFF).
33. **PR 33:** Ad stats (Phase 2, OFF) + guest "Show number" no longer creates a session.
34. **PR 34:** Verified badge with encrypted ID photos + admin ID checks (Phase 2, OFF).
35. **PR 35:** Business shop pages + admin Shops (Phase 2, OFF).
36. **PR 36:** Paid boosts in TEST MODE: receipts, double-entry ledger, admin Payments (Phase 2, OFF).
37. **PR 37:** Banner ads in paid slots with targeting + click counts (Phase 2, OFF).
38. **PR 38:** In-website chat (Phase 2, OFF). Part B done.
39. **PR 39:** Write API with app access keys + `/categories/{slug}/fields` + docs/api.md (switch `api.write`, OFF).
40. **PR 40:** Price guide on the post/edit form (`PriceHint`, switch `price_suggestions`, OFF).
41. **PR 41:** Seller ratings from the chat + public ratings page + admin Ratings (switch `seller_ratings`, OFF).
42. **PR 42:** Auto-post new ads to Telegram / Facebook behind `SocialNetwork` (switch `social.autopost`, OFF; keys in .env; docs/social-autopost.md).
43. **PR 43:** Help for buyers abroad: safety note + approximate price in their money from typed rates (switch `diaspora`, OFF). Part C done.
44. **PR 44:** Final bug hunt: dates in Somalia time everywhere (`mdate()`), 150-page crawl with every switch ON (clean), query budgets for Phase 2/3 pages.
45. **PR 45:** Security audit (ASVS L2) + SECURITY_REPORT.md; fixed HEAD sessions, private-disk routes, 2 missing rate limits; `SecurityAuditTest`.
46. **PR 46:** Load test 100k ads / 10k users (docs/load-test.md, `iibsom:load-data`, `tools/load-test`); 3 indexes, cached list totals, dashboard from counters, deferred counters for bulk actions. 
47. **PR 47:** All docs updated (README, docs/features.md, deploy cron list, TESTING_GUIDE, ROADMAP). Owner's big job list of 2026-11-16 is done.
48. **PR 48:** Round 2 starts (owner's list of 2026-11-17): Jiji study (docs/jiji-study.md) + globe language menu; fix: bottom bar language after instant switch. 
49. **PR 49:** Report an ad without signing in (`GuestReports`, stateless, no IP; switch `ads.guest_reports` ON); admin queue: Guest label, contact, most-reported first. 
50. **PR 50:** Fuller category tree (+ data migration for old sites, Part type field), admin drag-and-drop order, new demo ads/photos; fix: delete demo data with test payments. 
51. **PR 51:** Home category side panel (desktop) + sub category tiles/chips on category pages, results line, `catalog.hide_empty` (OFF).
52. **PR 52:** Filters: left column (computer) / full-screen sheet with "Show N results" (phone), area, posted within, photos, verified, most viewed, price ranges, chips, grid/list; switch `search.more_filters` (ON). Fix: language switch reacted to `#` jumps.
53. **PR 53:** Big footer with the coast drawing, link columns, social pages and languages, set in Settings → General → Footer; new pages Cookie info + Copyright; switch `site.new_footer` (ON).
54. **PR 54:** Seller badges (years on iibsom, Replies fast), photo count + Popular on cards, My ads + unread number in the header; switches `sellers.badges`, `ads.card_badges`, `site.header_icons` (all ON). Fix: old view totals kept.
55. **PR 55:** How to sell article + buttons, safety tip at the phone number, quick chat questions (prefilled), photo mark; switches `help.how_to_sell`, `contact.safety_tip`, `chat.quick_replies` (ON), `photos.watermark` (OFF). Round 2 done; the top PR points at main.
56. **PR 56:** Saved searches keep area / only with photos / verified sellers (alerts use them). Found by the 43-row checklist run.
57. **PR 57:** Guest report form gets a fresh time stamp when it opens (cached pages); friendly 403/419/429/500/503 pages in both languages.
58. **PR 58:** Grid/list buttons on phones too.
59. **PR 59:** Staff without site-settings rights open Settings on Appearance (no broken page); phone settings menu follows the address.
60. **PR 60:** Test sign-in box on /login for your own computer only (APP_ENV=local, switch login.test_accounts); Google without keys explains instead of failing at Google.
61. **PR 61:** Admin: @tailwindcss/vite moved to devDependencies (build-only); production audit clean.
62. **PR 62:** Home side panel: one line per sub category (owner request).

## Known notes
- The local PHP Docker image could not be built inside Claude's sandbox (the Alpine package site is blocked there). CI builds it on GitHub to prove it works.

## Before going live (open questions in DECISIONS.md)
- C, H1, H2, H3: host details (panel, PHP version, SSH/cron, what else uses iibsom.com).
