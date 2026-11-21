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
- Bigger card for boosted ads (2 columns wide in the grid) for a livelier look that also earns money. Later, once paid boosts are ON (owner, 2026-11-20). Not uneven (masonry) cards: owner agreed to wait (page jumps, copies Jiji's look).
- Scale step 3 (PR 90 rating): Meilisearch for keyword search after about 200k live ads (the `AdSearch` interface is ready). Shared hosting → VPS mode after about 5–10k visits a day.
- Recommended categories on home (pictures of categories like the ones the visitor looked at), and a coloured frame on boosted ad cards (PR 87 list).
- Admin → Home page, level 2: move parts up or down, and change the title, the line under it and button texts (English + Somali). Level 3: a full page builder (PR 86).
- Tell the buyer (bell) when the seller answers their rating (PR 85).
- A multi-choice field type ("Features: NFC, 120Hz, IP68…") shown as a tidy list on ad pages (seen on Jiji, PR 82).
- Shop opening hours as days + times (not free text), so shop pages can say "Open now" (PR 82).
- A 600 px phone-size photo for ad pages, so ads with very large first photos also load in under 1.5 s on slow phones (PR 80).
- Touch-drag to reorder photos on phones (PR 78 kept the ‹ › buttons).
- An "updated on" date on ad pages (needs a separate edit date).
- One SVG sprite for icons, to make big pages (home) smaller before compression.
- Footer "Our apps" column (App Store / Google Play badges) once there is a phone app.
- Dark mode for the public site (the admin has one); the footer is already dark.
- Brand pages are the slowest list (≈60 ms at 100k ads) because the brand sits in each ad's JSON fields: a generated column + index would make them ≈20 ms (only needed with many more ads).
- "Request call back" button, notifications bell (ad approved, new message, price drop) — from the Jiji study (docs/jiji-study.md); being built in the Jiji round (PRs 65–66). Search suggestions: built in PR 63.
- Search suggestions with spelling help ("toyta" → "toyota") and Somali/English word pairs ("baabuur" ↔ "car") once there are more ads.
- ✅ Admin Health page (PR 12). ✅ "Move to VPS" checklist (docs/scaling.md, PR 19).
- ✅ (PR 21) Admin "Edited" badge in the approval queue showing what changed since the last approval.
- ✅ (PR 22) Match demo photos to the brand/type in the title.
- Diaspora: daily exchange rates from a free rate service (with the typed rates as a fallback); pay a seller from abroad through a trusted agent.
- Seller ratings: the seller can reply once under a rating; "Report this rating" button for sellers.
- Price guide for buyers on the ad page ("lower than most similar ads"), once there is enough real data.
- API: OpenAPI (Swagger) file made from the routes, and "Sign in with Google" inside the app (no copy-paste key).
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

## Not planned
- Chat: email or push when a message waits for more than an hour; "Report this chat" for staff; photos in chat. Owner does not want chat (2026-11-20); the built chat stays OFF.
