# Testing guide

How to test iibsom by hand, row by row. Do each row in **English and Somali**, on a phone width (360, 390 or 414) and on a computer.
Before you start: `php artisan serve --port=8095` and `php artisan schedule:work` both running; demo data on (`php artisan iib:demo-reset`).

The same rows run automatically in a real browser (see "Automatic run" below).

| # | Area | What to do | What you should see | Switch needed |
|---|---|---|---|---|
| 1 | Setup | Sign in at /admin with your Super Admin (email + password), then set up 2FA with an authenticator app. The first time, the 5-step setup wizard opens: tick Somali, set the help email, press Next to the end, Finish | Dashboard opens; Somali is ON (the site shows the EN/SO globe) | – |
| 2 | Setup | Admin → Switches: turn ON "In-website chat", "Ad stats" and "Saved search alerts" (needed for rows 16, 27, 29, 31, 35) | Switches save; the site still works | – |
| 3 | Language | Click the globe (EN) in the header, choose Soomaali; then switch back | Same page switches at once; header, page, footer and phone bottom bar all change; address gets /so/ | `site.language_switch` |
| 4 | Language | Computer, keyboard only: Tab to the globe, Enter, Escape | Menu opens and closes; current language has a tick; focus goes back to the globe | `site.language_switch` |
| 5 | Categories | Computer home: move the mouse over a category in the left list | A side panel shows its sub categories; it closes when the mouse leaves | – |
| 6 | Categories | Phone home: tap a category (e.g. Vehicles) | Its page opens with sub category tiles | – |
| 7 | Categories | Open Vehicles, then the Cars tile | Tiles with icons, breadcrumb iibsom / Vehicles / Cars, "N results for Cars in Somalia" | – |
| 8 | Categories | Admin → Categories: move one with the arrows or by dragging, change an icon, turn one OFF | Site home shows the new order and icon; the OFF one is gone | – |
| 9 | Categories | Turn ON "Hide empty sub categories" | Sub categories with no ads disappear from home, tiles and filters | `catalog.hide_empty` |
| 10 | Filters | Computer, Property: choose "Last 7 days" and a max price, press "Show N results"; then click a quick price range | The button number changes while you choose; the page shows exactly that number; prices are within the range | `search.more_filters` |
| 11 | Filters | Phone: tap Filters, choose "Last 30 days", tap "Show N results"; open again and press × | Full-screen sheet; the button stays on screen; closes after the tap; results filtered; × closes it | `search.more_filters` |
| 12 | Filters | With several filters on, press × on one chip, then "Clear all" | × removes only that filter; Clear all removes all (clean address) | `search.more_filters` |
| 13 | Filters | Try "Only with photos", an area of Mogadishu, and sort Cheapest / Most expensive / Most viewed | Only ads with photos; area works; prices in the right order (Most viewed needs Ad stats ON) | `search.more_filters` |
| 14 | Filters | Switch Grid ↔ List (phone and computer), reload | The choice is remembered | `search.more_filters` |
| 15 | Filters | Copy the address with filters, open it in a new tab | Same filters and results | – |
| 16 | Filters | Signed in: on Property in Mogadishu with an area, "Only with photos" and a max price, press "Save this search" | It appears in My saved searches with all those filters in its name | `saved_searches` |
| 17 | Guest report | Signed OUT, open an ad → Report → choose a reason, add a note → Send | Thank-you message; no cookie is set | `ads.guest_reports` |
| 18 | Guest report | Send a report within 1 second of opening the form; then send 6 reports in a row | The quick one shows the normal thanks but is NOT saved (robots learn nothing); the 6th shows "Too many tries" in your language | `ads.guest_reports` |
| 19 | Guest report | Admin → Reports | Reporter shows "Guest" with the contact; the ad with most reports is on top | `ads.guest_reports` |
| 20 | Footer | Scroll to the bottom on a computer | Coast drawing, columns About / Support / Popular, languages, © 2026 | `site.new_footer` |
| 21 | Footer | Phone: tap each footer title | Sections are closed first and open on tap | `site.new_footer` |
| 22 | Footer | Open every footer link (About, Terms, Privacy, Cookie info, Copyright, Safety tips, Help, popular links) | Each page opens in English and Somali | `site.new_footer` |
| 23 | Footer | Admin → Settings → General → Footer: add a Facebook link, untick Terms, Save | Facebook icon appears in the footer, Terms link is gone | `site.new_footer` |
| 24 | Footer | Try a bad link like http://facebook.com/x or https://facebook.com.evil.com/x | Error message; not saved | `site.new_footer` |
| 25 | Footer | Empty "Help email for customers" in Settings, save; then fill it again | "Contact us" shows only while the email is filled in | `site.new_footer` |
| 26 | Seller badges | Open an ad of a seller who joined more than 1 month ago | "N months/years on iibsom" under the seller name (nothing for newer sellers) | `sellers.badges` |
| 27 | Seller badges | With chat ON, the seller answers 3 different buyers within an hour each | "Replies fast" under the seller name (can take up to 6 hours to show) | `sellers.badges + chat` |
| 28 | Ad cards | Look at ads with 2 or more photos in a list | Camera icon with the photo count | `ads.card_badges` |
| 29 | Ad cards | An ad with 100+ views (Ad stats ON) | "Popular" label on its card | `ads.card_badges + ad_stats` |
| 30 | Header | Signed in on a computer | "My ads" button in the header (on phones: in Account) | `site.header_icons` |
| 31 | Header | Another user sends you a chat message | Messages icon shows a red number (phone: on the bottom bar) | `site.header_icons + chat` |
| 32 | How to sell | Home: tap "How to sell" | Article with 6 steps; links inside work; Somali version too | `help.how_to_sell` |
| 33 | How to sell | Signed in, open Post an ad | Link "New to selling? 6 tips to sell fast" | `help.how_to_sell` |
| 34 | Safety tip | Ad page: tap "Show number" | Number appears with a yellow safety tip | `contact.safety_tip` |
| 35 | Quick chat | Signed in as a buyer, ad page: tap "Is it still available?", then Send | Chat opens with the text in the box; nothing is sent until you tap Send | `chat.quick_replies + chat` |
| 36 | Watermark | Turn ON "Mark on photos", post an ad with a new photo, wait 1 minute (schedule:work must run), then turn it OFF | Big photo has a small "iibsom" in the corner; list picture is clean | `photos.watermark` |
| 37 | Main flow | Signed out: on the sign-in page use the yellow "Test sign-in" box (your own computer only) with a new email, fill "Almost done", then post an ad with 2 photos | Ad waits for checking; photos are ready within a minute (schedule:work running) | `login.test_accounts` |
| 38 | Main flow | Admin → Ads → Waiting: Approve it | Ad is live on the site and in search | – |
| 39 | Main flow | In My ads: Renew (shows near the end date), then Mark as sold | End date moves; status becomes Sold | – |
| 40 | Roles | Sign in to /admin as the demo moderator and the demo support account (password + 2FA) | Only their own menus; Settings opens their own part (Appearance / 2FA), not the site settings | – |
| 41 | All screens | Open the main pages (home, categories, search, ad, help, pages, sign-in, account, post) on phone widths 360, 390, 414 (F12 → phone icon) and on a computer | No sideways scrolling, nothing cut off, no errors | – |
| 42 | All screens | Set your phone/computer to dark mode and repeat row 41 | Pages still readable (the site stays light) | – |
| 43 | Offline | Turn off the internet (or stop php artisan serve), open a new page; then turn it on and press Try again | "You are offline" page in your language; Try again brings the page back | – |

## Jiji round (PRs 63–68)

| # | Area | What to do | What you should see | Switch |
|---|---|---|---|---|
| J1 | Search suggestions | Home: type "to" in the search box | A list: matching categories (with "Category") and words like "toyota"; the typed part is bold | `search.suggestions` |
| J2 | Search suggestions | Open Cars, type "toy" in the top search (phone: tap the magnifier first) | First row "toy in Cars"; Up/Down moves, Enter opens; Escape closes the list | `search.suggestions` + `site.header_search` |
| J3 | Recent searches | Search for something, go back to home, tap the empty search box; then tap Clear | "Your recent searches" with your search; Clear empties it (kept on this phone only) | `search.suggestions` |
| J4 | Header search | Any page except home, on a computer and on a phone | Computer: box in the header. Phone: magnifier opens the box under the header. On a category page the box says "Search in Cars" | `site.header_search` |
| J5 | Switches | Turn OFF `search.suggestions`, then `site.header_search` | First: the boxes still search, no list. Second: no search in the header | – |
| J6 | New words | Approve a new ad "Suzuki Every van", wait until the next night (or run `php artisan iibsom:search-terms`), type "suz" | "suzuki" is offered; a word that is only in a waiting or rejected ad is never offered | `search.suggestions` |
| J7 | Save on cards | Saved ads ON. Signed out: tap the round Save button on a card | Sign-in page opens | `ads.card_save` + `favourites` |
| J8 | Save on cards | Signed in: tap Save on a card, reload, then open Account → Saved ads | Button turns blue and stays blue after reload; the ad is in Saved ads; tap again = removed | `ads.card_save` + `favourites` |
| J9 | Cards | Open Cars; switch to the list view (☰) | Cards show "Used"/"New"; list view shows the first words of the description | `ads.card_details` |
| J10 | Show more | Open All ads (more than 24 ads), scroll down, tap "Show more ads" | 24 more ads appear under the others; no page reload; the button goes away on the last page | `lists.show_more` |
| J11 | Popular now | Turn ON Ad statistics; open the home page | "Popular now" row with up to 8 most viewed ads of the last 30 days, above "Latest ads" | `home.popular_now` + `ad_stats` |
| J12 | Switches | Turn OFF each of the 4 switches above | That part disappears; lists still have page numbers | – |
| J13 | Cars | Post a car and pick a Body type; then filter Cars by Body type | The ad shows the body type; the filter finds it (Somali: "Nooca jirka") | – |
| J14 | Phones | Post a mobile phone and pick RAM | RAM shows on the ad and as a filter | – |
| J15 | Views | Ad statistics ON; open an ad that has views | "123 views" next to the date, for everyone | `ads.public_views` + `ad_stats` |
| J16 | Make an offer | Chat ON, signed in as a buyer: ad page → "Make an offer" → type 4500 → "Write the offer" | Chat opens with "Hello, I would like to offer $4,500…" in the box; nothing is sent until you tap Send | `chat.offers` + `chat` |

## Automatic run

A Playwright script ran all 43 rows on 2026-11-18 (EN + SO, 360/390/414 + desktop, light + dark), and checked every page for console errors, failed requests, PHP warnings, slow pages (> 1.5 s), sideways scrolling, cut-off text and untranslated texts. Results and bugs found: DECISIONS.md (2026-11-18, PR 56–60).
