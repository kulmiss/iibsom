# Visitor side: honest audit (Job 2, step 1)

Date: 2026-11-18. Checked as a first-time visitor, a buyer and a seller; phone (360/390/414) and computer;
Somali and English. Ideas were compared with the best classifieds sites (Jiji, OLX, Dubizzle, Avito, Facebook
Marketplace) — **ideas only**: no texts, pictures, logos, colours or layouts were copied.

Scores are out of 10: **Look / Ease / Trust**. "After" is filled in when the PR for that page is done.

| Page | What feels off | What is missing | Before (L/E/T) | After (L/E/T) | PR |
|---|---|---|---|---|---|
| Home | Hero is thin (one question, no promise); many cards show a dull grey box (no photo); only 2 lists | One-line promise, popular-search chips, "Post an ad free", popular cities, how it works, trust strip, recently viewed, safety promise | 6 / 7 / 5 | 8 / 9 / 8 | 75 |
| Category | Long sidebar of selects; sort is at the bottom; sub-category chips cut off on computer ("Boats"); the sidebar search repeats the header search | Quick filters on top (price, condition, city), sort next to grid/list, short intro text, rich empty results | 6 / 6 / 6 | 8 / 8 / 8 | 74, 77 |
| Search (no results) | Plain dashed box with two lines | Drawing, "Clear filters", "Post an ad", popular in this category | 4 / 4 / 5 | 8 / 8 / 7 | 74, 77 |
| Ad cards | Rows do not line up (one- vs two-line titles, optional New/Used line) | Same height and same lines in every card; nicer "no photo" look | 6 / 7 / 6 | 8 / 8 / 7 | 74 |
| Ad page | On phones the contact buttons come after the long description; no thumbnails; plain share | Sticky price + contact bar on phones, thumbnails + full-screen swipe/zoom, key-details icons, "read more", "more from this seller", recently viewed | 6 / 6 / 6 | 8 / 9 / 8 | 76 |
| Seller page | Plain list of ads | Ratings with reviews, contact, filters on the seller's ads, cover | 5 / 6 / 5 | | 79 |
| Post an ad | On phones the sticky "Post" button covers fields; no progress or tips beside the form | Step progress, tips, price help, live card preview, share on success | 6 / 6 / 6 | 8 / 9 / 8 | 78 |
| My ads | Many stacked buttons; "Free move to top again on…" looks like a button but is only information; "+POST AN AD" in capitals unlike all other buttons | Tidy actions, a calm info line, one button style | 5 / 6 / 7 | | 74, 79 |
| My account | Plain list of links | Small dashboard: stats, saved, searches, following, chats | 5 / 6 / 6 | | 79 |
| Saved / searches / following (empty) | Plain dashed box | Friendly drawing + one action | 4 / 5 / 6 | 8 / 8 / 7 | 74 |
| 404 and error pages | Plain text and one button | Drawing, a search box, way home; error pages also in dark | 4 / 5 / 5 | 8 / 8 / 7 | 74 |
| Whole site | No dark mode; buttons in two styles (capitals and normal) | Dark mode that follows the phone + a switch; one button style; one focus ring; grey loading cards | 6 / 7 / 6 | 8 / 8 / 7 | 74 |
| Phone bottom bar (Somali) | "Xayeysiisyada la keydiyey" breaks onto 2 lines | Short label | 5 / 6 / 6 | 8 / 8 / 7 | 74 |

## Screenshots (before)

Phone (390 px) and computer, English; home also in Somali. Full set (EN + SO, signed in and out, 116 pages) is
kept by the QA scripts and can be made again with `audit/capture.py`.

| Page | Phone | Computer |
|---|---|---|
| Home (EN) | ![](ux-audit/before/home-en-phone.webp) | ![](ux-audit/before/home-en-pc.webp) |
| Home (SO) | ![](ux-audit/before/home-so-phone.webp) | ![](ux-audit/before/home-so-pc.webp) |
| Category | ![](ux-audit/before/category-en-phone.webp) | ![](ux-audit/before/category-en-pc.webp) |
| Search, no results | ![](ux-audit/before/search-empty-en-phone.webp) | ![](ux-audit/before/search-empty-en-pc.webp) |
| Ad page | ![](ux-audit/before/ad-en-phone.webp) | ![](ux-audit/before/ad-en-pc.webp) |
| Seller | ![](ux-audit/before/seller-en-phone.webp) | ![](ux-audit/before/seller-en-pc.webp) |
| Post an ad | ![](ux-audit/before/post-form-en-phone.webp) | ![](ux-audit/before/post-form-en-pc.webp) |
| My ads | ![](ux-audit/before/my-ads-en-phone.webp) | ![](ux-audit/before/my-ads-en-pc.webp) |
| My account | ![](ux-audit/before/account-en-phone.webp) | ![](ux-audit/before/account-en-pc.webp) |
| 404 | ![](ux-audit/before/404-en-phone.webp) | ![](ux-audit/before/404-en-pc.webp) |

## Screenshots (after)

| PR | What | Picture |
|---|---|---|
| 74 | Category, computer: chips wrap, cards line up, calm "No photo" | ![](ux-audit/after/category-en-pc.webp) |
| 74 | No results (computer, English) | ![](ux-audit/after/search-empty-en-pc.webp) |
| 74 | No results, Somali, dark, 360 px | ![](ux-audit/after/search-empty-so-dark-phone.webp) |
| 74 | 404, dark, 390 px | ![](ux-audit/after/404-en-dark-phone.webp) |
| 74 | My ads, Somali, 360 px | ![](ux-audit/after/my-ads-so-phone.webp) |
| 75 | Home, phone (EN, light) | ![](ux-audit/after/home-en-phone.webp) |
| 75 | Home, computer (SO, dark) | ![](ux-audit/after/home-so-dark-pc.webp) |
| 76 | Ad page, phone (SO): details with pictures, contact bar | ![](ux-audit/after/ad-so-phone.webp) |
| 76 | Ad page, computer (EN, dark): small pictures, more from seller | ![](ux-audit/after/ad-en-dark-pc.webp) |
| 76 | Full-screen photos | ![](ux-audit/after/ad-viewer-phone.webp) |
| 77 | Category: intro, quick filters (price open) | ![](ux-audit/after/category-quick-pc.webp) |
| 77 | No results with "try without" + latest (SO, dark) | ![](ux-audit/after/no-results-so-dark-phone.webp) |
| 78 | Post: category search (SO) | ![](ux-audit/after/post-choose-so-phone.webp) |
| 78 | Post: steps, tips, live preview (EN, dark) | ![](ux-audit/after/post-form-en-dark-phone.webp) |
| 78 | After posting: share + move to top (SO, dark) | ![](ux-audit/after/post-done-so-dark-phone.webp) |

## Plan (one small PR each, stacked)

74 design system (this audit, dark mode, one button style, tidy cards, empty states, friendly errors) ·
75 home · 76 ad page · 77 category and search · 78 post an ad · 79 account, seller, "How we keep you safe" ·
80 speed, accessibility and a full re-run.
