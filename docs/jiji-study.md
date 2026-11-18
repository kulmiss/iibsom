# Deep Jiji study (2026-11-18)

**How this was made.** jiji.com.et answers our browser with a Cloudflare "Performing security verification" page (checked again on 2026-11-18). We never get around such checks, so we could not click through the live site. Instead we read Jiji's own public help pages (FAQ on jiji.com.et and the same platform's FAQ on jiji.ng, jiji.co.ke, jiji.com.gh, jiji.co.tz), their app-store descriptions, and news articles about the company. Only **ideas and features** are taken; no texts, photos, ads, logos, colours, icons or layouts. iibsom builds everything in its own design and words.

Value = how much it helps people in Somalia (1–10). Effort: S = hours, M = about a day, L = several days.
Decision: **BUILD** (this round), **HAVE** (iibsom already has it), **LATER** (money/legal or needs a paid outside service: built behind a switch or written in LATER.md), **SKIP** (with the reason).

## 1. Header and search

| Feature | Where on Jiji | What it does | iibsom has it? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Search box on every page | Header | Search from anywhere | partly (home page and category filters only) | 10 | S | **BUILD** (PR 63: search in the header of every page; phones get a magnifier) |
| Suggestions while typing | Header search | Shows matching categories and popular words as you type | no | 8 | M | **BUILD** (PR 63) |
| Recent searches | Header search | Your last searches, one tap to repeat | no | 6 | S | **BUILD** (PR 63, kept on the phone only) |
| Search inside a category | Category pages | Search only in the category you are in | partly (filters form) | 6 | S | **BUILD** (PR 63: suggestions offer "in Cars") |
| Language switch | Header | Change language | yes | 9 | – | HAVE |
| Sign in / register | Header | Account | yes (Google; phone login switch) | 9 | – | HAVE |
| SELL button | Header + phone bottom bar | Post an ad | yes | 10 | – | HAVE |
| Saved ads icon | Header | Open favourites | yes (switch `favourites`) | 7 | – | HAVE |
| Messages with unread count | Header | Open chats | yes (number since PR 54) | 8 | – | HAVE |
| Notifications bell (inbox) | Header | Feed of events: new message, saved-search match, followed seller's new ad, feedback, ad status | no (our bell = saved searches) | 8 | L | **BUILD** (PR 66) |
| My adverts | Header / profile menu | Manage own ads | yes | 9 | – | HAVE |
| Profile menu | Header | Account pages | yes | 8 | – | HAVE |

## 2. Home

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Category list with icons and counts | Home | Browse by category | yes | 10 | – | HAVE |
| Sub category panel (hover / tap) | Home | Reach sub categories fast | yes (PR 51, one line since PR 62) | 9 | – | HAVE |
| "How to sell" promo | Home | Teach new sellers | yes (PR 55) | 7 | – | HAVE |
| Trending / recommended ads | Home | Popular and personal picks | partly ("For you"; "Popular" label) | 6 | S | **BUILD** (PR 64: "Popular now" row while Ad stats is ON) |
| Grid / list | Lists | Choose the look | yes (PR 52/58) | 5 | – | HAVE |
| Endless scrolling | Lists | More ads without pages | no (numbered pages) | 6 | M | **BUILD** (PR 64: "Show more ads" button; page links stay for no-JS and Google) |

## 3. Categories and filters

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Sub category tiles with icons | Category pages | Pick a sub category | yes | 9 | – | HAVE |
| Breadcrumbs | Category pages | Where am I | yes | 7 | – | HAVE |
| Result counts | Category pages | "N results" | yes | 7 | – | HAVE |
| Sort (newest, price, popular) | Lists | Order | yes | 7 | – | HAVE |
| "Any time" / posted within | Filters | New ads only | yes | 6 | – | HAVE |
| Region → city → area | Filters | Where | yes | 8 | – | HAVE |
| Price min/max + ranges with counts | Filters | Price | yes | 8 | – | HAVE |
| Cars: make, model, year, fuel, gear, colour, mileage, condition | Cars filters | Find the right car | yes | 9 | – | HAVE |
| Cars: body type | Cars filters | Saloon, SUV, pickup… | no | 6 | S | **BUILD** (PR 65) |
| Property: bedrooms, bathrooms, furnished, size, rent/sale | Property filters | Find a home | yes (rent and sale are separate sub categories) | 9 | – | HAVE |
| Phones: brand, storage, condition | Phone filters | Find a phone | yes | 8 | – | HAVE |
| Phones: RAM | Phone filters | Memory | no | 5 | S | **BUILD** (PR 65) |
| Number ranges (year from–to, mileage up to) | Filters | Ranges | yes | 7 | – | HAVE |
| Filters editable per category | Admin | Data-driven fields | yes (Super Admin → Categories) | 8 | – | HAVE |
| Instant number while filtering | Filters | "Show N results" | yes | 7 | – | HAVE |
| Saved searches | Lists | Get new matching ads | yes (switch) | 8 | – | HAVE |
| Rename / mute a saved search | Saved searches | Manage alerts | no (delete only) | 5 | S | **BUILD** (PR 66) |

## 4. Ad cards

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Price, title, location, time | Cards | Basics | yes | 10 | – | HAVE |
| Short description | List view cards | More info before opening | no | 5 | S | **BUILD** (PR 64, list view only) |
| Condition (New / Used) | Cards | At a glance | no | 5 | S | **BUILD** (PR 64) |
| TOP / VIP / boosted labels | Cards | Paid placement | yes (boosts, test mode) | 6 | – | HAVE |
| POPULAR label | Cards | Many views | yes (PR 54) | 5 | – | HAVE |
| "X years on Jiji" | Cards/seller | Trust | yes on the ad page (PR 54) | 6 | – | HAVE |
| Verified badge | Cards/seller | Trust | yes (switch `verified_badge`) | 8 | – | HAVE |
| Rating stars | Seller | Trust | yes (switch `seller_ratings`) | 7 | – | HAVE |
| Photo count | Cards | How many photos | yes (PR 54) | 5 | – | HAVE |
| Watermark on photos | Photos | Stop copying | yes (switch, OFF) | 5 | – | HAVE |
| Save (heart) on the card | Cards | Save without opening | no (ad page only) | 7 | S | **BUILD** (PR 64) |

## 5. Ad page

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Gallery with swipe, zoom, count | Ad page | See photos | yes | 9 | – | HAVE |
| Price + "Negotiable" | Ad page | Price | yes | 8 | – | HAVE |
| Details table | Ad page | Category fields | yes | 8 | – | HAVE |
| Description, location, posted time | Ad page | Basics | yes | 9 | – | HAVE |
| Number of views | Ad page | Interest | seller only (Ad stats) | 5 | S | **BUILD** (PR 65: shown to everyone while Ad stats is ON) |
| Seller card: name, years on site, verified, rating, other ads | Ad page | Trust | yes | 9 | – | HAVE |
| Response time ("replies fast") | Seller card | Trust | yes (PR 54) | 6 | – | HAVE |
| Last seen | Seller card | Activity | no | 4 | S | SKIP (privacy: shows when someone is online; "Replies fast" gives the same trust without that) |
| Show contact / call / WhatsApp | Ad page | Contact | yes | 10 | – | HAVE |
| Chat | Ad page | Contact | yes (switch) | 8 | – | HAVE |
| Quick message templates | Ad page | One-tap questions | yes (PR 55) | 7 | – | HAVE |
| Make an offer | Ad page | Send a price you would pay | no | 7 | S | **BUILD** (PR 65, through chat) |
| Request a call back | Ad page | Leave your number, seller calls you | no | 6 | M | **BUILD** (PR 65, needs sign-in; seller gets it in notifications) |
| Safety tips | Ad page | Avoid scams | yes | 10 | – | HAVE |
| Report abuse | Ad page | Report | yes (also guests) | 9 | – | HAVE |
| Share | Ad page | WhatsApp, copy link | yes | 8 | – | HAVE |
| Similar ads | Ad page | More choice | yes | 8 | – | HAVE |
| Seller closes ad ("mark unavailable") with a reason | My ads | Sold on iibsom / elsewhere / not selling | partly (Mark as sold, no reason) | 7 | S | **BUILD** (PR 67) |

## 6. Seller profile and shops

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Public seller page with all their ads | Seller name | See everything a seller offers | business shops only (switch `shops`); reviews page | 8 | M | **BUILD** (PR 66: every seller gets a page) |
| Company / enterprise shop pages | Shops | Business pages | yes (switch `shops`) | 7 | – | HAVE |
| Feedback with text | Seller page | Written reviews | yes (stars + text, switch `seller_ratings`) | 7 | – | HAVE |
| Feedback is checked before showing | Moderation | Stop fake reviews | yes (bad-word check + staff can hide) | 7 | – | HAVE |
| Follow a seller | Seller page | Get their new ads | no | 7 | M | **BUILD** (PR 66) |
| Offline store visit ("verified store") | Shops | Agent visits | no | 4 | L | SKIP for now (needs field staff; the verified badge covers ID) |

## 7. Sign-up and sign-in

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Google sign-in | Sign-in | No password | yes | 9 | – | HAVE |
| Phone + SMS code | Sign-in | No email needed | yes (switch, needs an SMS provider) | 9 | – | LATER (SMS provider; LATER.md) |
| Email + password | Sign-in | Classic | no | 4 | M | SKIP (passwords get reused and stolen; Google/phone are safer) |
| Facebook sign-in | Sign-in | Social | no | 5 | M | LATER (needs a Facebook app review; LATER.md) |
| Profile completion (name, phone, city) | After sign-up | Contact details | yes ("Almost done") | 9 | – | HAVE |
| ID check with selfie (outside service) | Verified ID | Automatic KYC | manual ID photo check | 6 | L | LATER (paid outside service; our manual check is behind `verified_badge`) |

## 8. Post an ad

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Category choice then form | Post | Right fields | yes | 10 | – | HAVE |
| Category fields | Post | Brand, year… | yes | 9 | – | HAVE |
| Photo rules (up to N, first is the main one) | Post | Good photos | yes | 8 | – | HAVE |
| Price guidance | Post | Fair price | yes (switch `price_suggestions`) | 6 | – | HAVE |
| Location | Post | City + area | yes | 9 | – | HAVE |
| Duplicate ad warning | Post | One product = one ad | yes | 7 | – | HAVE |
| Drafts | Post | Keep a half-done ad | yes (on the phone) | 6 | – | HAVE |
| Promote after posting | Post done | Offer boosts | yes when boosts are ON | 5 | – | HAVE |
| Moderation messages with a reason | My ads | Why declined | yes (reason shown) | 8 | – | HAVE |
| Help article per decline reason | Help | How to fix | partly (one posting-rules article missing) | 6 | S | **BUILD** (PR 68: "Posting rules" help article, linked from declined ads) |
| Ad end date + renew | My ads | Keep ads fresh | yes | 8 | – | HAVE |
| Free "bump up" every 7 days | My ads | Move a live ad back to the top | no | 7 | M | **BUILD** (PR 67) |
| Edit | My ads | Change an ad | yes | 9 | – | HAVE |

## 9. Premium services

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| TOP (top of results for 7/30 days) | Premium page | Paid placement | yes (Top / VIP / Diamond, test mode) | 7 | – | HAVE |
| Packages with auto-bump every N hours, more ad slots, website link | Premium page | Seller plans | partly | 6 | L | LATER (prices and plans are a money decision; LATER.md) |
| Upgrade with the remaining value | Premium | Switch plans | no | 4 | M | LATER (money) |
| Automatic renewal of plans | Premium | Card / mobile money | no | 4 | L | LATER (money, needs a payment provider) |
| Balance / wallet | Profile | Prepaid money | no | 5 | L | LATER (money/legal) |
| Pay-per-click campaigns | Premium | Bidding | no | 3 | L | SKIP (too early for Somalia's market size) |
| Free ad limits per category | Posting | Paid extra slots | daily limit only | 4 | M | LATER (money decision) |

## 10. Messages, notifications, saved

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Chat inside the site | Messages | Talk safely | yes (switch) | 8 | – | HAVE |
| Block in chat | Messages | Stop someone | yes | 8 | – | HAVE |
| Notifications inbox | Bell | All events in one place | no | 8 | L | **BUILD** (PR 66) |
| Choose which emails you get | Settings | Less email | partly (unsubscribe links) | 6 | M | **BUILD** (PR 66: notification settings) |
| Favourites | Saved | Save ads | yes (switch) | 7 | – | HAVE |
| "Price changed since you saved it" | Favourites | Price drop | no | 7 | M | **BUILD** (PR 66: on the saved list + a notification) |
| Push notifications | App | Phone alerts | no (no app yet) | 6 | L | LATER (with the mobile app) |
| SMS on new messages | Plans | Alerts by SMS | no | 5 | M | LATER (SMS provider) |
| Automatic sharing to the seller's own Facebook/Instagram | Profile | Free promotion | site-level auto-post only | 4 | L | LATER (needs Facebook app review per seller) |

## 11. Trust and safety

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Safety tips page | Help/footer | Avoid scams | yes | 10 | – | HAVE |
| Verified ID badge | Profile | Trust | yes (manual, switch) | 8 | – | HAVE |
| Fraud warnings before paying | Ad page / chat | Don't pay first | yes (tip at the number, PR 55) | 9 | – | HAVE |
| Report reasons | Report | Right queue | yes | 8 | – | HAVE |
| Blocked users | Chat / admin | Stop abuse | yes (chat block + admin ban) | 8 | – | HAVE |
| Community / posting rules page | Help | What is allowed | no | 6 | S | **BUILD** (PR 68) |
| Escrow "safe deal" with delivery | Checkout | Pay through the site | no | 6 | L | LATER (money/legal; needs a licence and a delivery partner) |

## 12. Help and pages

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Help / FAQ | Footer | Answers | yes | 9 | – | HAVE |
| How to sell | Help | Seller guide | yes (PR 55) | 7 | – | HAVE |
| How to buy | Help | Buyer guide | no | 6 | S | **BUILD** (PR 68) |
| Contact support (form) | Help | Ask for help | email only | 6 | M | **BUILD** (PR 68: a contact form; staff answer by email; no IP stored) |
| About, Terms, Privacy, Cookies, Copyright | Footer | Legal pages | yes | 8 | – | HAVE |
| Billing policy | Footer | Payments rules | no | 4 | S | LATER (needed once boosts take real money; LATER.md) |
| Footer: columns, art, social links | Footer | Navigation | yes (PR 53) | 7 | – | HAVE |
| App badges | Footer | Get the app | hidden (no app) | 5 | – | LATER (with the app) |
| Other countries | Footer | Jiji in other countries | – | 1 | – | SKIP (iibsom is Somalia only) |

## 13. SEO

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Page titles per page | All | Google | yes | 9 | – | HAVE |
| Category × city pages | Lists | "Cars in Mogadishu" | yes | 8 | – | HAVE |
| Brand pages | Lists | "Toyota cars in Mogadishu" | yes | 6 | – | HAVE |
| Breadcrumbs (also for Google) | Lists/ads | Rich results | yes | 6 | – | HAVE |
| Structured data | Ad / list pages | Rich results | yes | 6 | – | HAVE |
| Sitemap | /sitemap.xml | Google | yes | 8 | – | HAVE |
| Clean addresses | All | Readable links | yes | 7 | – | HAVE |

## 14. Phone web

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Bottom navigation | Phone | Main places | yes | 9 | – | HAVE |
| SELL always reachable | Phone | Post fast | yes (bottom bar) | 9 | – | HAVE |
| Filters as a sheet | Phone | Filters | yes (PR 52) | 9 | – | HAVE |
| Back closes the sheet | Phone | Feels like an app | yes (sheet is a #filters address) | 6 | – | HAVE |
| Offline page / install as app | Phone | Works badly online | yes (PWA) | 7 | – | HAVE |
| Speed tricks (cache, small images) | All | Fast on 3G | yes | 10 | – | HAVE |

## 15. Other ideas noticed

| Feature | Where | What it does | iibsom? | Value | Effort | Decision |
|---|---|---|---|---|---|---|
| Jobs: apply with a CV | Jobs | Job seekers | no | 4 | L | SKIP for now (CVs are personal data; chat/call covers it) |
| "Request help" call from staff | Profile | Phone support for sellers | no | 4 | M | SKIP (needs a call team; the contact form covers it) |
| Seller's personal manager | Premium | Account manager | no | 2 | – | SKIP (company process, not software) |

## Counts

- Features found: **137**
- iibsom already had: **88**
- **Build this round: 27** (PRs 63–68)
- LATER (money, legal, or a paid outside service): **14** (written in LATER.md)
- SKIP: **8**, each with its reason in the table (last seen, email + password, pay-per-click, offline store visits, other countries, job CVs, staff call-backs, personal managers)
