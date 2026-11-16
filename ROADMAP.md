# ROADMAP

## Phase 1 — Minimal launch ✅ built (PRs 1–19, 2026-11-16)
Google sign-in, post an ad, browse + search, ad page, My Ads, safety (reports, approval of every ad, filters, limits, bans), Super Admin (shadcn), feature switches, English with instant language switch (Somali-ready), SEO + speed, scale-ready base (R2, queues, search interface, /api/v1), demo data, docs. Runs on **both VPS and shared hosting** (decision #13).

### Phase 1 additions (owner request 2026-11-16)
- ✅ (PR 15) **"Recommended for you"** on the home page (like Jiji): remember the categories and ads a visitor looked at and show similar ads. Guests: kept **only in their own browser** (no server tracking, no IP; page stays cacheable). Signed-in users: can also be saved to their account. Own feature switch `recommendations`. Build right after browse + ad pages (PR 10).

## Phase 2 — Money + trust (build only when owner says)
- Boosts (Top / VIP / Diamond): seller pays the owner outside the site; **admin activates** (no payment code, decision #14)
- Verified badge (phone + ID)
- Business shop pages with monthly plans (activated by admin)
- In-app chat
- Favourites + saved-search alerts
- Banner ads (activated by admin)
- Ad stats (views, calls, WhatsApp clicks)

## Phase 3 — Growth (build only when owner says)
- Flutter app + push notifications
- Somali language: texts written (PR 17); turn ON after a native speaker review
- Arabic
- Diaspora features
- Smart price suggestions
- Seller ratings (real buyers only)
- Delivery partners
- Auto-post to social media
- More regions (other countries) via region switches

## Ideas
- ✅ Admin Health page (PR 12). ✅ "Move to VPS" checklist (docs/scaling.md, PR 19).
- ✅ (PR 21) Admin "Edited" badge in the approval queue showing what changed since the last approval.
- ✅ (PR 22) Match demo photos to the brand/type in the title.
- Help search box and "Was this helpful? yes/no" counts (no personal data) once there are more articles.
- Somali review inside the admin: a page where a reviewer edits Somali texts next to the English and ticks "checked" (no CSV round trip).
- ✅ (PR 23) Weekly email to the owner: new ads, users, reports, top categories.
- Saved "Recommended for you" across devices for signed-in users (opt-in).
- Auto-trust for good sellers (skip approval after N approved ads) — switch built, OFF per decision #9.
