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

## Small choices (taken by Claude, can be changed)

| Date | Choice |
|------|--------|
| 2026-11-16 | Ad expiry default 60 days; post limit 5 ads/day per user. |
| 2026-11-16 | Versions: Laravel 13 (latest stable), PHP 8.3, MySQL 8.4 LTS, Redis 7. Tests run on MySQL like production. |
| 2026-11-16 | Local ports: site 8095, Mailpit 8096, MySQL 33095. |
| 2026-11-16 | Repo is public for one day (2026-11-16) for CI testing; owner makes it private on 2026-11-17. |
| 2026-11-16 | Language switch without reload: page HTML is fetched for the new language in the background and swapped in; the chosen language is saved in a cookie. Each language keeps its own cached pages and URL prefix for SEO. |
