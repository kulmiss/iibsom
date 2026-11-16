# Handover

_Last updated: 2026-11-16 (PR 5)_

## Current state
- **PR 1 (foundation):** Laravel 13 base, Docker for local (port 8095), CI (tests on MySQL, Pint, Composer/npm audit, gitleaks, Docker build), project memory files. Public site shows a "coming soon" page.
- **PR 2 (switches + hosting):** feature switches (`config/features.php`, `feature()` helper, `@feature` Blade, `feature:` route middleware = 404 when OFF), settings (`setting()` helper), VPS/shared hosting mode (`IIBSOM_HOSTING`), health check (`php artisan iibsom:health`), security headers + strict CSP with nonce. CI runs tests in both modes.
- **PR 3 (languages + layout):** English-only at launch, Somali-ready (`lang/so`), `/so/...` URLs, in-place language switch (no reload, cookie), hreflang/canonical, CSV export for native review, mobile-first layout (header, footer, home hero), system fonts, Windows hint in health check. Docs: docs/translations.md.
- **PR 4 (locations + categories + home):** regions → cities → areas (Somalia; only Mogadishu ON; 18 Mogadishu districts), 15 main categories / 52 sub categories with custom fields (cars, houses, land, phones, laptops, livestock, jobs), `Catalog` cached lists, Jiji-style home in Iibsom blue/yellow, Lucide icons (`icon()` helper). Seeders are production-safe and can run again.
- **PR 5 (phone home):** Jiji-mobile style tiles, one-row search, bottom bar following switches. Removed stray `dump.rdb`.
- Nothing is deployed yet.
- Owner runs locally with **Laragon** on Windows (see docs/local-setup.md → A).

## How to run
See [docs/local-setup.md](docs/local-setup.md). Short version: `cp .env.example .env && docker compose up -d --build`, then open http://localhost:8095.

## Working rules (from the owner)
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
5. **PR 5:** Phone home like Jiji mobile + bottom bar (this one)
6. **PR 6:** Google sign-in + first-time profile (name, city, +252 phone, terms)
7. **PR 7:** Post an ad (photos → R2 via queue, drafts offline, approval queue)
8. **PR 8:** Browse, search (DB full-text behind an interface), ad page, SEO
9. **PR 9:** "Recommended for you" (browsing history in the browser, similar ads)
10. **PR 10:** My Ads, reports, safety (limits, bad words, duplicates, bans)
11. **PR 11+:** Admin (shadcn-admin) with 2FA + roles + Feature switches page + Health page, demo data, deploy guides (VPS + shared), scaling docs

## Known notes
- Public pages currently set an XSRF/session cookie; the page-cache PR must make guest pages cookie-free so Cloudflare can cache them.
- The local PHP Docker image could not be built inside Claude's sandbox (the Alpine package site is blocked there). CI builds it on GitHub to prove it works.

## Before going live (open questions in DECISIONS.md)
- C, H1, H2, H3: host details (panel, PHP version, SSH/cron, what else uses iibsom.com).
