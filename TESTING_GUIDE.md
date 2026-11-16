# Testing guide

Most important tests first. "Expected" is what you should see.

## Automated (run before every merge)
| # | Command | Expected |
|---|---------|----------|
| 1 | `docker compose exec app php artisan test` | All tests green |
| 2 | `docker compose exec app vendor/bin/pint --test` | "PASS" (code style OK) |

## Manual (PR 1: foundation)
| # | Do this | Expected |
|---|---------|----------|
| 1 | Open http://localhost:8095 | "Iibsom": "Buy and sell anything in Somalia. Coming soon." |
| 2 | Open http://localhost:8095/up | Green "Application up" page |
| 3 | Open http://localhost:8096 | Mailpit inbox opens (empty) |
| 4 | Open a pull request on GitHub (not draft) | CI runs 3 jobs: tests, secret-scan, docker-build. All green |
| 5 | Mark the PR as draft and push again | CI does **not** run |

## Manual (PR 2: switches, settings, hosting modes, health, security)
| # | Do this | Expected |
|---|---------|----------|
| 1 | `php artisan migrate` | Creates `features` and `settings` tables |
| 2 | `php artisan iibsom:health` | List of OK / WARN / FAIL lines. Scheduler = FAIL until cron runs |
| 3 | Run `php artisan schedule:work` in a 2nd window, wait 1 minute, run `php artisan iibsom:health` again | Scheduler = OK |
| 4 | Set `HOSTING_MODE=shared`, run `php artisan schedule:list` | You see `shared-queue-worker` |
| 5 | Open http://localhost:8095, press F12 → Network → click the page → Headers | You see `Content-Security-Policy`, `X-Frame-Options: DENY` |
| 6 | `php artisan tinker` → `feature('chat')` | `false` (Phase 2 features are OFF) |
| 7 | GitHub PR checks | `tests (vps)` and `tests (shared)` are both green |

## Manual (PR 3: languages + layout)
| # | Do this | Expected |
|---|---------|----------|
| 1 | `npm install && npm run build`, open http://localhost:8095 on your phone size (F12 → phone icon) | Blue header box, search box, "Post an ad" button, footer links |
| 2 | Open http://localhost:8095/so | "Page not found" (Somali is OFF) |
| 3 | `php artisan tinker --execute="app(App\\Support\\Settings\\SettingsManager::class)->set('languages.enabled',['en','so']);"` then reload / | "English · Soomaali" switch appears in the header |
| 4 | Click "Soomaali" | Address changes to /so **without the page reloading** (texts stay English until Somali is written) |
| 5 | Open http://localhost:8095/ again | It switches to /so by itself (choice remembered) |
| 6 | `php artisan iibsom:translations:export so` | Creates `storage/app/translations-so.csv`, open in Excel |
| 7 | Turn Somali OFF again: same as step 3 with `['en']` | Switch disappears |

## Manual (PR 4: locations, categories, Jiji-style home)
| # | Do this | Expected |
|---|---------|----------|
| 1 | `php artisan migrate` then `php artisan db:seed` | 15 main categories, Somalia cities, Mogadishu districts |
| 2 | `npm run build`, open http://localhost:8095 (desktop) | Blue search box with "Mogadishu" picker, category list on the left with icons + "No ads yet", popular category tiles, "Trending ads" box |
| 3 | Phone size (F12 → phone icon) | Categories as a 3-column icon grid |
| 4 | `php artisan tinker --execute="App\\Models\\City::where('slug','kismayo')->first()->update(['enabled'=>true]);"` and reload | Picker shows "All Somalia", Mogadishu, Kismayo. Turn it back off with `false` |
| 5 | Same for a category: `App\\Models\\Category::where('slug','jobs')->first()->update(['enabled'=>false]);` | "Jobs" disappears from home |
| 6 | Run `php artisan db:seed` again | Nothing breaks, switches you changed stay as they are |

## Manual (PR 7: Google sign-in + profile)
First add your Google keys: see docs/google-signin.md.

| # | Do this | Expected |
|---|---------|----------|
| 1 | Open http://localhost:8095/login | "Continue with Google" button |
| 2 | Click it, choose your Google account | You come back to "Almost done" (welcome form) |
| 3 | Leave Terms unticked, press Save | Red message under Terms |
| 4 | Type phone `0612345` (too short) | "Please enter a Somali mobile number…" |
| 5 | Fill name, phone `61 5123456`, tick Terms, Save | Home page with "Welcome! Your account is ready." Header shows your first letter |
| 6 | Open http://localhost:8095/account | Your name, Mogadishu, +252 61 512 3456, Log out button |
| 7 | Edit profile, change phone, Save | "Your profile is saved." |
| 8 | Log out | Back to home, header shows the person icon again |
| 9 | Look in `storage/logs/security-*.log` | `login.success` line with your user id |

## Manual (PR 8: post an ad)
| # | Do this | Expected |
|---|---------|----------|
| 1 | Signed out, tap **Sell** | Sign-in page |
| 2 | Signed in (profile done), tap **Sell** | "What are you selling?" with categories |
| 3 | Vehicles → Cars | Form with photos, title, Brand/Model/Year/Kilometres/Fuel/Gear, New/Used, description, price, city, area |
| 4 | Add 3 photos from your phone | Each shows "Uploading…" then the picture; first one says "Cover" |
| 5 | Use ‹ › on a photo | Order changes, "Cover" moves |
| 6 | Type a title, close the tab, open the form again | "We brought back what you typed last time." Your text is back |
| 7 | Press **Post my ad** with an empty description | Red message under Description |
| 8 | Fill everything, press **Post my ad** | "Thank you! Your ad was sent … checks every ad" |
| 9 | Post the same ad again | "You already posted this ad." |
| 10 | `php artisan queue:work --stop-when-empty` then look in `storage/app/public/ads/` | 3 sizes per photo (`thumb`, `medium`, `large` `.webp`) |

## Manual (PR 9: Super Admin foundation)
| # | Do this | Expected |
|---|---------|----------|
| 1 | `cd admin && pnpm install && pnpm build`, then `php artisan iibsom:staff you@example.com` | "Staff account ready" |
| 2 | Open http://localhost:8095/admin | "Staff sign in" |
| 3 | Wrong password 6 times | "Too many tries. Wait … seconds." |
| 4 | Right password | "Set up two-factor sign-in (required)" with a QR code |
| 5 | Scan with your authenticator app, type the code | "2FA is on. Save your recovery codes" (8 codes) |
| 6 | Continue | Dashboard: 4 cards, chart, top categories + cities |
| 7 | Sun/moon icon (top right) | Light / dark theme |
| 8 | Gear icon | Layout options (sidebar style, compact) |
| 9 | Ctrl+K (⌘K on Mac) | Search box to jump to any page |
| 10 | Avatar → Appearance → pick a colour → Save brand colour | Buttons and charts change colour for all staff |
| 11 | Activity log | Your sign-in and the colour change are listed |
| 12 | Sign out, sign in again with a recovery code | Works once; the same code fails the second time |
| 13 | As a customer (Google) open /admin | Staff sign-in page, no access |

## Manual (PR 10: moderation)
| # | Do this | Expected |
|---|---------|----------|
| 1 | Post an ad on the site, then admin → **Waiting for approval** | The ad is listed with its cover photo |
| 2 | Click the ad | Side panel: photos, price, fields (Brand, Year…), description, seller, history |
| 3 | **Approve** | "Ad approved, it is live now." The ad leaves the queue. Home page count for that category goes up by 1 |
| 4 | Tick 2 ads → **Reject** → pick "Wrong category" → Reject | "2 done." Both show "Rejected" with the reason on **Ads** |
| 5 | **Users** → click a user → **Ban user** (keep both boxes ticked) | User shows "Banned", their ads are taken down, their phone appears under **Blocked phone numbers** |
| 6 | As that customer, try to save the same phone on the profile page | "This phone number cannot be used on iibsom." |
| 7 | **Reports** → **Take ad down** | The ad is rejected and every waiting report on it is closed |
| 8 | Sign in as a **Support** staff member | You can look but there are no Approve / Ban / Take down buttons |

## Manual (PR 11: categories, cities, switches, settings)
| # | Do this | Expected |
|---|---------|----------|
| 1 | Admin → **Categories** → open Vehicles → **Add sub category in Vehicles** → "Boats", pick an icon → Save | "Boats" appears under Vehicles. On the site, **Sell** → Vehicles shows Boats |
| 2 | Boats → **Fields** → **Add a field** → "Engine" → Number, unit "hp" → Save | Posting a Boats ad now asks "Engine (hp)" |
| 3 | Switch **Jobs** OFF | Jobs disappears from the home page |
| 4 | **Cities and areas** → switch **Kismayo** ON | Home page search shows "All Somalia / Mogadishu / Kismayo" |
| 5 | Mogadishu → **areas** → add "KM4" | KM4 is in the area list when posting |
| 6 | **Feature switches** → WhatsApp button | A box explains what changes before you confirm. After OFF it shows "changed" with a reset button |
| 7 | **Settings → General** → Ads end after 30 days, tick Somali → Save | New approved ads end in 30 days. A language switch appears on the site |

## Manual (PR 12: pages, banners, staff, health, demo data)
| # | Do this | Expected |
|---|---------|----------|
| 1 | `php artisan db:seed`, open http://localhost:8095/safety | "Stay safe" page. Footer links About / Safety tips / Terms / Privacy work |
| 2 | Admin → **Pages** → Safety → change text → **Preview** → **Save page** | The site shows the new text. Typing `<script>` shows as text, not code |
| 3 | **Home banners** → **Add banner** → picture 1600×500, link `/post` | "Showing". The banner appears on the home page under the quick links |
| 4 | Banner link `javascript:alert(1)` | Refused |
| 5 | **Staff** → **Add staff** (Moderator) | A one-time password appears once. Signing in with it asks for 2FA setup |
| 6 | Try to change your own role | Not possible ("You") |
| 7 | **System health** | List of checks with fixes, plus "This server" facts (Google keys MISSING until you add them) |
| 8 | **Settings → Demo data** → type DELETE → delete | Only demo records are removed |

## Manual (PR 13: browse, search, ad page)
| # | Do this | Expected |
|---|---------|----------|
| 1 | Approve an ad in Admin, open the home page | It shows in "Latest ads" |
| 2 | Search "toyota" on the home page | Results page with filters (price, condition, sort) |
| 3 | Open **Cars** → filter brand + min year | Only matching cars |
| 4 | Open an ad → **Show number** | The number appears only after the tap |
| 5 | Tap a photo | It opens big; Esc closes it |
| 6 | Signed in → **Report this ad** | "Thanks" message; reporting twice is not possible |
| 7 | Open a pending ad link while signed out | Not found (404) |
| 8 | http://localhost:8095/sitemap.xml and /robots.txt | Live ads listed; robots blocks all when not production |

## Manual (PR 14: My Ads + safety)
| # | Do this | Expected |
|---|---------|----------|
| 1 | Account → **My ads** | Tabs: Live / Waiting / Rejected / Sold-ended with numbers |
| 2 | **Edit** a live ad, change the price, save | "Saved. Our team checks…"; ad moves to Waiting |
| 3 | On edit, remove one photo and add a new one | After save the ad has the new set, in your order |
| 4 | **Mark as sold** | Asks first; ad moves to Sold / ended and is gone from the site |
| 5 | Admin sets an ad to end in 2 days (or wait) → **Renew** | Ends 60 days from today, still live |
| 6 | **Delete** | Asks first; ad gone (staff can still see it) |
| 7 | Post an ad with "send money first" or a web link | Refused with a clear message |
| 8 | Admin → Settings → General → add a word to Bad words | Ads with that word are refused |

## Manual (PR 15: Recommended for you)
| # | Do this | Expected |
|---|---------|----------|
| 1 | Open 2–3 car ads, then go to the home page | "Recommended for you" shows other cars; "Recently viewed" shows the ads you opened |
| 2 | Tap **Clear my history** | Both lists disappear |
| 3 | Admin → Feature switches → Recommended for you OFF | Home shows no lists |
| 4 | Browser dev tools → Application → Local storage | `iibsom-history` is there; nothing is saved on the server |

## Manual (PR 16: demo data)
| # | Do this | Expected |
|---|---------|----------|
| 1 | `php artisan iib:demo-reset` | About 1 minute. Prints 2 demo staff logins with 2FA keys |
| 2 | Open the site | 300+ ads with real photos in every category |
| 3 | Sign in to /admin as demo moderator (add the 2FA key to your phone app) | Dashboard shows charts for the last 3 months, 24 waiting ads, 12 reports |
| 4 | Admin → Settings → Demo data → Show demo data OFF | Demo ads disappear from the site and the counts |
| 5 | Delete all demo data (type DELETE) | All demo ads, users, staff and reports are gone; your own data stays |
| 6 | Set `APP_ENV=production` in .env and run `php artisan iib:demo-reset` | Refused |

## Manual (PR 17: Somali)
| # | Do this | Expected |
|---|---------|----------|
| 1 | `php artisan db:seed` (adds Somali names to existing data) | Nothing you edited changes |
| 2 | Admin → Settings → General → tick Somali → Save | "English / Soomaali" switch appears on the site |
| 3 | Tap **Soomaali** | Page changes to Somali without reloading; URL starts with /so |
| 4 | Look at categories, ad page, post form, My ads, errors | All in Somali |
| 5 | `php artisan iibsom:translations:export so` | CSV for a native speaker to check |
