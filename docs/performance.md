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
