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
| 13 | 2026-11-16 | VPS or shared hosting? | **Both.** Same code; a hosting mode in `.env` (`IIBSOM_HOSTING=vps` or `shared`) switches the fallbacks. Deploy guides for both, tests in both modes, admin health page. Replaces the earlier "NOT shared hosting" line. |
| 14 | 2026-11-16 | Payments? | **No payments through the system** (site like jiji.com). Paid extras (boosts, shop plans, banner ads) are paid to the owner outside the site (EVC/Zaad etc.); an **admin activates** them in the admin panel. No payment code. |
| 15 | 2026-11-16 | Homepage style? | **Inspired by jiji.com layout** (big search with location picker, category list with icons + ad counts, popular categories, trending ads) but in **Iibsom's own blue + yellow**. Built in PR 4 with categories. |
| 16 | 2026-11-16 | Phone home page? | **Like Jiji mobile:** location + search on one row, 4-column category tiles starting with "Post ad" and "Trending", bottom bar (Home, Saved*, Sell, Messages*, Profile; *only when switched on). Icons instead of photos. |
| 17 | 2026-11-16 | Remember what people browse? | **Yes**: "Recommended for you" with similar ads (like Jiji). Guests: history stays in their browser only (privacy, cacheable pages). Planned as PR 10. |
| 18 | 2026-11-16 | City picker with one city? | **Hide it** when only one city is ON; it appears automatically when a 2nd city is switched on. |

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
