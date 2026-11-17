# ROADMAP

## Phase 1 — Minimal launch ✅ built (PRs 1–19, 2026-11-16)
Google sign-in, post an ad, browse + search, ad page, My Ads, safety (reports, approval of every ad, filters, limits, bans), Super Admin (shadcn), feature switches, English with instant language switch (Somali-ready), SEO + speed, scale-ready base (R2, queues, search interface, /api/v1), demo data, docs. Runs on **both VPS and shared hosting** (decision #13).

### Phase 1 additions (owner request 2026-11-16)
- ✅ (PR 15) **"Recommended for you"** on the home page (like Jiji): remember the categories and ads a visitor looked at and show similar ads. Guests: kept **only in their own browser** (no server tracking, no IP; page stays cacheable). Signed-in users: can also be saved to their account. Own feature switch `recommendations`. Build right after browse + ad pages (PR 10).

## Phase 2 — Money + trust ✅ built, switched OFF (PRs 31–38, 2026-11-17; see docs/features.md)
- Boosts (Top / VIP / Diamond): seller pays the owner outside the site; **admin activates** (no payment code, decision #14)
- Verified badge (phone + ID)
- Business shop pages with monthly plans (activated by admin)
- In-app chat
- Favourites + saved-search alerts
- Banner ads (activated by admin)
- Ad stats (views, calls, WhatsApp clicks)

## Phase 3 — Growth: ✅ built and OFF (PRs 39–43): price guide, seller ratings, auto-post, diaspora help, write API. Still open below
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
- Diaspora: daily exchange rates from a free rate service (with the typed rates as a fallback); pay a seller from abroad through a trusted agent.
- Seller ratings: the seller can reply once under a rating; "Report this rating" button for sellers.
- Price guide for buyers on the ad page ("lower than most similar ads"), once there is enough real data.
- API: OpenAPI (Swagger) file made from the routes, and "Sign in with Google" inside the app (no copy-paste key).
- Chat: email or push when a message waits for more than an hour; "Report this chat" for staff; photos in chat.
- Refunds for boosts (reverse ledger lines, end the boost) and pay for shop plans with the same receipt flow.
- Ad stats on busy sites: count views in Redis and write to the database once a minute (fewer writes).
- Verified badge on ad cards in lists, and a "Verified sellers only" filter.
- Save button (bookmark) on ad cards in lists, not only on the ad page.
- Short video clips (30 s) in the admin guide for approving ads and banning users.
- Help search box and "Was this helpful? yes/no" counts (no personal data) once there are more articles.
- Somali review inside the admin: a page where a reviewer edits Somali texts next to the English and ticks "checked" (no CSV round trip).
- ✅ (PR 23) Weekly email to the owner: new ads, users, reports, top categories.
- Saved "Recommended for you" across devices for signed-in users (opt-in).
- Auto-trust for good sellers (skip approval after N approved ads) — switch built, OFF per decision #9.
