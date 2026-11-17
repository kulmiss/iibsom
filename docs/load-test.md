# Load test: 100,000 ads and 10,000 users

**Date:** 2026-11-17 (PR 46). **Machine:** a small cloud box: 2 CPU cores, 7 GB memory, MySQL 8.4 with its default 128 MB buffer (like a small VPS or a good shared host), PHP 8.3 with OPcache, PHP's own web server with 4 workers (slower than nginx + PHP-FPM, so real servers do better).

**Data:** 10,000 users and 100,000 ads (85% live, 5% each pending, ended, sold) over the last 6 months, all in Mogadishu (only city ON), with real titles and prices. Made with `php artisan iibsom:load-data` in a separate database.

## Results (after the fixes in this PR)

Each page 15 times, one after another (server time + network on the same machine). **Page cache OFF**, so every request goes to the database:

| Page | Median | 95% under |
|---|---|---|
| Home | 25 ms | 38 ms |
| Category (Cars) | 29 ms | 31 ms |
| Main category (Vehicles) | 31 ms | 38 ms |
| Search "toyota" (full-text) | 35 ms | 39 ms |
| Price filter | 32 ms | 40 ms |
| Brand page (Cars › Mogadishu › Toyota) | 35 ms | 39 ms |
| Ad page | 31 ms | 38 ms |
| Page 50 of a list | 34 ms | 36 ms |
| API list / search / one ad | 25 / 34 / 25 ms | 28 / 42 / 31 ms |
| sitemap.xml | 42 ms | 45 ms |
| Admin dashboard | 119 ms | |
| Admin ads list / search | 47 / 79 ms | |
| Admin users list | 21 ms | |
| My ads (seller with the most ads) | 32 ms | |

**Filters (PR 52, same machine, page cache OFF, 100k ads):**

| Page | Median | 95% under |
|---|---|---|
| Cars, last 7 days + only with photos | 24 ms | 27 ms |
| Vehicles, most viewed | 44 ms | 55 ms |
| Cars, price + condition + 30 days + photos + cheapest | 18 ms | 19 ms |
| "Show N results" count (JSON) | 19 ms | 22 ms |
| Brand page (Cars › Mogadishu › Toyota), re-measured | 63 ms | 82 ms |

All well under the 150 ms goal. The brand page reads the brand from each ad's extra fields (JSON), so it is the slowest list; if it ever matters, a generated column with an index fixes it (ROADMAP).

Many people at once (a mix of home, lists, search, ad pages and the API; one computer, so some answers are "too many requests" from our own rate limits, which is correct):

| Setting | Users at once | Pages per second | Median | 95% under |
|---|---|---|---|---|
| Page cache OFF | 8 | 66 | 112 ms | 211 ms |
| Page cache OFF | 20 | 68 | 294 ms | 368 ms |
| Page cache ON (normal) | 20 | 130 | 140 ms | 260 ms |
| Page cache ON (normal) | 50 | 129 | 382 ms | 557 ms |

130 pages a second is about **11 million page views a day** on 2 cores. With 50 people clicking at the same moment, pages still come back in about half a second. At this size the limit is the CPU, not the database.

## Problems found and fixed

| Problem at 100k ads | Before | After | Fix |
|---|---|---|---|
| Home page "latest ads" | 257 ms | 25 ms | New index `ads (status, published_at)` |
| API list and "all ads" search: counting every live ad for the page numbers | 417 ms | 25 ms | The count is kept in the cache until anything changes (every ad save clears it) |
| Admin dashboard | 860 ms | 119 ms | Live totals per category/city/all from the counters we already keep; index `ads (created_at)` |
| Admin ads list (newest first) | 186 ms | 47 ms | Index `ads (created_at)` |
| Admin users list | 536 ms | 21 ms | Index `users (role, is_demo, created_at)` (it counted every user's ads before sorting) |
| **Approving or deleting 100 ads at once** | ≈ 15 s | ≈ 1.5 s | The live-ad counters are recounted once at the end, not once per ad |

Saving one ad still recounts its category and city (≈ 0.15 s at 100k ads). That is fine for one ad at a time.

## When to change something (future)

- **Above ~500k ads or ~100 pages a second for long:** move search to Meilisearch (the search interface is ready: `App\Search\AdSearch`), put the cache and sessions in Redis (VPS), and give MySQL a bigger buffer (`innodb_buffer_pool_size` = half the memory).
- **Shared hosting:** the numbers above already use the database for cache and sessions, like shared hosting does.

## How to run it again

```bash
# 1. An empty database whose name ends in _load (the command refuses any other name)
#    e.g. in MySQL: CREATE DATABASE iibsom_load; GRANT ALL ON iibsom_load.* TO 'iibsom'@'%';
export DB_DATABASE=iibsom_load APP_DEBUG=false CACHE_STORE=database SESSION_DRIVER=database
php artisan migrate:fresh --seed --force
php artisan iibsom:load-data --ads=100000 --users=10000 --force      # ≈ 20 seconds

# 2. A server for it (another window)
php artisan config:cache && php artisan route:cache
cd public && PHP_CLI_SERVER_WORKERS=4 php -d opcache.enable_cli=1 -S 127.0.0.1:8096 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php

# 3. Measure
tools/load-test pages
tools/load-test mix 20 30

# 4. Afterwards
php artisan config:clear && php artisan route:clear
```
