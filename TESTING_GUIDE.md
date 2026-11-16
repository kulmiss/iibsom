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
