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
| 25 | Footer | Turn OFF the "Contact form" switch. Empty "Help email for customers" in Settings, save; then fill it again. Turn the switch ON again | Switch OFF: "Contact us" (an email link) shows only while the email is filled in. Switch ON: "Contact us" always opens the contact form | `site.new_footer` + `site.contact_form` |
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
| 39 | Main flow | In My ads: Renew (shows near the end date), then Close ad → "Sold on iibsom" → Close ad | End date moves; status becomes Sold and "Closed: Sold on iibsom" shows | – |
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
| J17 | Bell | Post an ad as a customer; in the admin approve it; back on the site look at the bell | Red 1 on the bell; the list says "Your ad … is approved and live"; the number goes away after opening | `notifications` |
| J18 | Bell | Reject another ad with a reason | "Your ad … was not approved: <reason>" | `notifications` |
| J19 | Settings | Account → Notifications → "Choose what you get": untick "My ad is approved", Save; approve another ad | No new line in the bell for it | `notifications` |
| J20 | Price drop | Saved ads ON. Buyer saves an ad; seller lowers the price; staff approve the change | Buyer's bell: "Price dropped…"; Saved ads shows "Cheaper: $X → $Y" | `favourites.price_drop` |
| J21 | Call back | Signed in as a buyer: ad page → "Request a call from the seller"; then try again | "Done. The seller has your number…"; the seller's bell shows your name and number (tap = call) and an email (MAIL_MAILER=log: storage/logs); the second try says you already asked | `contact.call_back` |
| J22 | Switch | Turn OFF `notifications` | The bell goes back to saved searches (if ON); /account/notifications answers 404 | – |
| J23 | Seller page | Ad page → seller box → "N ads →" | Page with the seller's name, city, badges and all their live ads (no phone, no email) | `sellers.page` |
| J24 | Follow | Signed in: seller page → Follow; Account → Sellers I follow | Button says Following; the seller is in the list; Stop following removes it | `sellers.follow` |
| J25 | Follow | While following, the seller posts a new ad and staff approve it | Your bell: "<seller>, whom you follow, has a new ad…" | `sellers.follow` + `notifications` |
| J26 | Saved searches | Saved searches ON: Rename a search; tap "Pause alerts" | New name shows; "Alerts paused" and no email/bell for it until "Turn alerts on" | `saved_searches.manage` |
| J27 | Close ad | My ads → Close ad → pick "Sold somewhere else" → Close ad | Ad is Sold; "Ended" tab shows "Closed: Sold somewhere else"; admin ad panel shows the reason | `ads.close_reason` |
| J28 | Close ad | Close another with "Not selling any more", then Renew it | It ends at once (not on the site); Renew puts it back | `ads.close_reason` |
| J29 | Move to top | A live ad older than 7 days: tap "Move to top"; tap again | It is first in its category list; the second time you see the date it is possible again | `ads.free_bump` |
| J30 | Help | Help → "Posting rules" and "How to buy on iibsom"; also in Somali | Both articles open; a not-approved ad in My ads has "Read our posting rules" | `help.posting_rules`, `help.how_to_buy` |
| J31 | Contact | Footer → Contact us; send once with a too short message, then a real one | First: red box, what you typed is still there. Then: "Thank you…" | `site.contact_form` |
| J32 | Contact (admin) | /admin as Support → Messages → "Answer by email", then Done | Your email program opens to the customer; the message moves to Done (Moderators do not see Messages) | `site.contact_form` |
| J33 | No drugs | Post an ad with "Fresh khat" in the title (also try Somali "qaadka"); then edit a live ad and add "tramadol" | Refused: "Drugs are not allowed on iibsom…" (Somali: "Daroogo laguma oggola…"). "Iska qaad" or "weed killer" are fine | `safety.no_drugs` |
| J34 | No drugs | Open an ad → Report this ad | "Drugs (khat, cannabis, pills…)" is a reason | `safety.no_drugs` |
| J35 | No drugs (admin) | Settings → General → Drug words: add a word, Save; Ads → filter "Drugs" | The new word is refused when posting; the filter shows drug-checked and drug-reported ads with a red badge | `safety.no_drugs` |
| J36 | No drugs (old ads) | `php artisan iibsom:drugs:check --dry-run`, then without `--dry-run` | Lists matching live ads; then they wait for approval with the Drugs mark (nothing deleted). Approve = mark goes away | `safety.no_drugs` |
| J37 | Chat drugs | Chat ON: in a chat write "Do you have fresh khat?" (Somali: "Ma haysaa qaadka cusub?"), Send; then "Can we chat tomorrow?" | First: not sent, kind note above the box, your text stays. Second: sent. "Iska qaad", "Qatar" are fine | `safety.no_drugs` + `chat` |
| J38 | Chat drugs (admin) | Admin → Users → State → "Drugs (chat)"; open the person; "Clear mark" | The sender has a red Drugs badge and the word; their ads show under Ads → Drugs; Clear mark removes it (Support cannot) | `safety.no_drugs` |
| J39 | Dark mode | Phone set to dark (or footer → Dark); open home, a category, an ad, Post an ad, My ads, Contact, a wrong address | Everything dark and easy to read (no white boxes, footer text readable); Auto follows the phone; Light keeps it light after reload | `site.dark_mode` |
| J40 | Empty states | Search for `zzqqxx`; open Saved ads / Saved searches / Sellers I follow with none; My ads with no ads | A small drawing, one line of help and one action (Clear filters / Post an ad / All ads) | — |
| J41 | Friendly 404 | Open `/no-such-page` in English and Somali | Drawing, search box that works, Back to home | — |
| J42 | Rich home | Open the home page as a guest, phone and computer, EN + SO, light + dark | Promise line, popular searches (tap one: results), "Post an ad free", popular places with counts (tap: that district's ads), How it works, Why people use iibsom with true numbers, safety promise | `home.*` |
| J43 | Install card | On an Android phone in Chrome open the home page | A small "Add iibsom to your phone" card; Add opens the phone's install; Not now hides it (stays hidden after reload) | `pwa.install_prompt` |
| J44 | Ad photos | Open an ad with 3+ photos; tap a small picture; tap the big photo; swipe / arrow keys; close | Jumps to that photo; full screen shows "2 / 5", swipes, pinch zoom works on a phone; closing stays on that photo | `ads.gallery_plus` |
| J45 | Phone contact bar | On a phone open someone else's ad; scroll down; tap Call, then WhatsApp | Price + buttons stay above the bottom bar; Call shows the number and opens the phone app; WhatsApp opens WhatsApp; not on your own ad | `ads.sticky_contact` |
| J46 | More on the ad page | Long description; seller with other ads; look at 2–3 ads then open another | "Read more" opens the full text; "More from <seller>" shows only their other live ads; "Recently viewed" lists the earlier ads, not this one | `ads.read_more`, `ads.more_from_seller`, `ads.recently_viewed` |
| J47 | Quick filters | Open Cars on a computer; Sort → Cheapest; Price → type a max → Show ads; Condition → Used; then on a phone | Each choice keeps the others (look at the address); one drop-down open at a time, Escape closes; on a phone only Sort is on the bar | `search.quick_bar` |
| J48 | Helpful no results | Cars → New + Price from 90,000 | "Try without one of these" buttons (each drops one) and "Latest in Cars" | `search.rich_empty` |
| J49 | Category intro | Open Cars (no filters), EN + SO | One short line: how many ads, lowest–highest price; gone when a filter is used | `seo.intro` |
| J50 | Post an ad, nicer | Post → type "taleefan" / "car" in the search; pick Cars; read the tips; type a title and price, tick Negotiable, add a photo; post | Steps show where you are; the search shows only matching categories; the preview card changes as you type; the Post button never covers a field; the thank-you page explains Move to top, and Share shows only when the ad is live | `post.*` |
| J51 | My account dashboard | Sign in as a seller with ads; open My account (EN + SO) | Live / waiting ads, views and calls of 30 days, unread messages, saved, followers — each opens its page; Post an ad button | `account.dashboard` |
| J52 | Seller page | Open a seller with ads in 2+ categories; tap a category chip; Cheapest; Most expensive | Cover band; only that category's ads; order changes; the header still says all their ads | `sellers.page_plus` |
| J53 | How we keep you safe | Footer → Support → How we keep you safe (EN + SO) | The article opens; the home safety promise links to it | `help.how_we_keep_safe` |
| J54 | Dark mode, readable everywhere | Footer → Dark; look at the Sell button, the home "Post an ad free" button, a help article's links | Dark text on yellow; links light blue and easy to read | `site.dark_mode` |
| J55 | Report while signed in, profile not finished | Sign in with a new account, skip "Almost done", open someone's ad → Report this ad → Send | "Thank you" on the ad page (no jump to "Almost done"); Post an ad still asks to finish the profile | `ads.reports` |
| J56 | Ad page extras | Open a car ad with many details (EN + SO): tap Show all details; tap the brand; back, tap "Already sold? Let us know"; sign in as someone else and tap "Post an ad like this" | All details show; the brand opens all its ads; the report box opens with "Already sold" chosen; Post an ad opens in Cars with the same brand and condition (title and price empty) | `ads.post_like_this`, `ads.field_links`, `ads.mark_unavailable`, `ads.details_more` |
| J57 | Usual price | Open a car ad (EN + SO) in a category with 5+ priced ads; then one with a very low price | "Similar … ads usually ask $X – $Y (from N ads)" + "in the usual range"; the very low one shows the amber "see the item before you pay" line; hidden when there are fewer than 5 ads | `ads.market_price` |
| J58 | All categories | Home → "All" tile (phone) or "All categories" (computer); type "car", then "gaari", then "zzz"; switch to Somali and type again | Matching groups open; "zzz" says no category; counts match the category pages; search still works after the language switch | `catalog.finder` |
| J59 | Phone top bar on the ad page | Phone: open an ad (EN + SO); tap Share; tap Save (Saved ads ON, signed in); tap Back | Bar shows ‹ + category name + Share + Save + language; share sheet (or "Link copied"); both Save buttons change; Back opens the category | `ads.phone_top_bar` |
| J60 | Discover more | Open a car ad with brand, condition and district; tap each "Discover more" link | Every link opens a list that has this ad; Somali words in Somali | `ads.discover_more` |
| J61 | Number on Saved | Saved ads ON, signed in: save an ad from a card and from an ad page; un-save | The number on Saved (top bar on computer, bottom bar on phone) goes up and down at once; 0 = no number | `account.saved_count` |
| J62 | Language switch on pages with buttons | Ad page → switch language → tap "Show phone number", a photo, "Already sold?"; same on Post an ad and My ads | Page loads in the new language and every button works | (fix) |
| J63 | Message box on the ad page | Chat ON, signed in as a buyer: open an ad, write "Hello" in "Write to …", Send; then try "fresh khat" | "Hello" is in the chat with the seller; the drug text is not sent, the note shows under the box and the text stays | `chat.ad_box` |
| J64 | Stars on ad cards | Seller ratings ON; a seller with ratings: open Cars, search, home; then their seller page | "★ 4.5 (2)" on that seller's cards; no stars on the seller's own page | `ads.card_stars` |
| J65 | Answer and Helpful on ratings | As the seller open My ratings → Answer → Post; as someone else tap Helpful twice; as the buyer change the rating | Answer shows once (no second form); Helpful on/off with the number; after the change the answer and Helpful are gone | `ratings.replies`, `ratings.likes` |
| J66 | Admin → Home page | Admin → Website → Home page; switch "popular searches" OFF; open the home page (phone + computer, EN + SO); switch it ON again | The "Popular:" row is gone, the line above and "Post an ad free" stay; it is back after ON; Activity log says "Switched “Home: popular searches” OFF" | `admin.home_page` |
| J67 | Place picker | Home: tap the place box next to the search (EN + SO, phone + computer); type "hod"; pick a district; Search | Districts A–Z with counts; search finds it; the box shows "District, City"; results are only from that district | `search.location_picker` |
| J68 | "2 years on iibsom" on cards | Cars list with a seller who joined 2+ years ago (and one who joined this year) | "2 years on iibsom" on the old seller's cards only | `ads.card_years` |
| J69 | Category list stays in view | Computer: home page, scroll down the ads; hover a category | The list stays at the top of the screen; the sub-category panel still opens in full | `home.sticky_categories` |
| J70 | Website colour | Admin → Settings → Appearance → Website colour: pick the green dot, Save; open the site (light + dark, phone + computer); try a light yellow; then "Back to iibsom blue" + Save | Top bar, buttons and links green, readable in light and dark; yellow is refused with "Use … instead"; blue again after reset | `site.custom_color` |
| J71 | Hide ads after reports | Sign in as 3 different customers and report the same ad; then as staff open Reports → "Put ad back"; repeat and "Take ad down" | After the 3rd report the ad is gone from the site, the seller gets a bell message and sees "Hidden for now" in My ads (Waiting tab); Put back = live again, reports closed; Take down = rejected | `reports.auto_hide` |

## Automatic run

A Playwright script ran all 43 rows on 2026-11-18 (EN + SO, 360/390/414 + desktop, light + dark), and checked every page for console errors, failed requests, PHP warnings, slow pages (> 1.5 s), sideways scrolling, cut-off text and untranslated texts. Results and bugs found: DECISIONS.md (2026-11-18, PR 56–60).
