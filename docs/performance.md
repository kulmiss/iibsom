# Speed

Measured 2026-11-16 on the sandbox (PHP 8.3 built-in server, MySQL 8.4, **database cache = shared-hosting mode**),
with the full demo data (324 ads). 20 requests each, median.

| Page | Page cache ON (guest) | Page cache OFF | HTML size (before gzip) |
|------|----------------------:|---------------:|-----:|
| Home | 17 ms | 53 ms | 63 KB |
| Category (/cars) | 18 ms | 67 ms | 31 KB |
| Ad page | 20 ms | 66 ms | 28 KB |
| Search (?q=toyota, never server-cached) | 51 ms | 51 ms | 26 KB |
| API /api/v1/ads | 55 ms | 55 ms | 17 KB |

Files sent to phones (gzip): CSS 19 KB, main JS 1.3 KB, ad page JS 0.6 KB, post-ad JS 2.4 KB. No web fonts.
Photos: WebP/AVIF in 3 sizes, lazy loading, 1-year cache.

## What makes it fast
- **Guest pages are cookie-free** and say `Cache-Control: public, max-age=60, s-maxage=120`, so Cloudflare and browsers can keep them.
- **Page cache** (switch "Page cache"): guest pages are kept 2 minutes on the server; any change (ad approved, switch, setting, category, page, banner) clears it at once. A fresh CSP nonce is put in every cached copy.
- Lists of categories/cities, switches, settings and banners come from the cache.
- Query budgets are tested (`tests/Feature/PerformanceTest.php`): home ≤ 6 queries, category/search ≤ 10, ad page ≤ 14, cached home 0.
- Search uses MySQL FULLTEXT; Meilisearch can replace it later (`AdSearch` interface).

## Check it yourself
```bash
curl -s -o /dev/null -w '%{time_total}\n' http://localhost:8095/
curl -sD - -o /dev/null http://localhost:8095/ | grep -i 'x-page-cache\|cache-control'
```
On the live site also run PageSpeed Insights (https://pagespeed.web.dev) on the home page and an ad page.

## At 100,000 ads
See [load-test.md](load-test.md): pages answer in about 25–45 ms without the page cache, and about 130 pages a second with it, on 2 cores.


## Jiji round speed check (2026-11-18, PR 71)

100,000 ads (`iibsom_load`), shared-hosting mode (database cache/sessions), 2 cores. Before = `main` (PR 55); after = top of the stack (PR 71). Each page 15 times.

| page (page cache ON, as guests see it) | before median / p95 ms | after median / p95 ms |
|---|---|---|
| home | 32 / 36 | 35 / 39 |
| category (cars) | 42 / 48 | 39 / 44 |
| category (phones) | 38 / 42 | 41 / 51 |
| main category | 39 / 41 | 41 / 46 |
| search "toyota" | 46 / 59 | 46 / 58 |
| search cheapest | 44 / 49 | 38 / 48 |
| price filter | 39 / 44 | 42 / 48 |
| brand page | 74 / 83 | 68 / 75 |
| filters: 7 days+photos | 28 / 30 | 30 / 34 |
| filters: popular | 61 / 68 | 60 / 88 |
| filters: many | 38 / 42 | 38 / 46 |
| filter count (JSON) | 27 / 29 | 30 / 37 |
| ad page | 45 / 50 | 41 / 43 |
| page 50 of a list | 43 / 46 | 45 / 60 |
| API list | 27 / 29 | 31 / 37 |
| API search | 43 / 47 | 39 / 46 |
| API ad | 25 / 30 | 30 / 33 |
| sitemap | 49 / 57 | 47 / 55 |

**Cold** (page cache and data cache emptied before every request — the worst case, once every few minutes on a live site), 5 times each:

| page | before median / max ms | after median / max ms |
|---|---|---|
| home | 63 / 262 | 80 / 382 |
| category + filters | 54 / 70 | 56 / 71 |
| search | 88 / 117 | 96 / 111 |
| ad page | 60 / 67 | 60 / 78 |
| seller page | new / - | 48 / 50 |
| suggestions | new / - | 39 / 45 |

Found and fixed: "Popular now" read 49,000 rows and sorted them (64 ms cold); a new `ads(status, views)` index makes it 15 ms. The home page is still ~17 ms slower when fully cold (8 more ad cards); with the page cache it is the same. New pages (seller page, search suggestions) answer in about 40–50 ms cold.

Lighthouse (phone, on the owner-like local setup): home 90 / 100 / 100, ad page 95 / 100 / 100, category 96 / 100 / 100 (performance / accessibility / best practices). SEO shows 69 only because the local site tells Google "do not index" on purpose (it is not live yet).
