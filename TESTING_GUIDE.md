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
| 4 | Set `IIBSOM_HOSTING=shared`, run `php artisan schedule:list` | You see `shared-queue-worker` |
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
