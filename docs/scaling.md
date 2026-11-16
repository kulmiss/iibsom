# Growing: when and what to change

iibsom is built so each step is a settings change, not a rewrite.

| Stage | Signs | Do this |
|-------|-------|---------|
| **Start** (shared hosting) | < ~2,000 visitors a day, < 50k ads | Cloudflare in front, page cache ON (default), photos on R2 |
| **1. VPS** | Host warns about CPU; pages often > 1 s; photos wait for cron | Move to a VPS ([deploy-vps.md](deploy-vps.md)): `HOSTING_MODE=vps`, Redis for cache/sessions/queue, 2 queue workers |
| **2. Bigger VPS** | CPU often > 70% | More CPU/RAM (resize in the provider panel, minutes); more PHP-FPM children; 4 queue workers |
| **3. Search server** | > ~100k ads or search > 300 ms | Meilisearch on the VPS, a new `AdSearch` class (the interface is ready), set in `.env` |
| **4. Separate database** | DB is the busy part | Managed MySQL (or a 2nd VPS) + read replica; set `DB_HOST` |
| **5. More app servers** | One VPS is not enough | 2+ app servers behind a load balancer; sessions/cache already in Redis; photos already on R2 — nothing on disk to share |

## Moving from shared hosting to a VPS
1. Set up the VPS ([deploy-vps.md](deploy-vps.md)) with the same `.env` (change the `HOSTING_MODE`, Redis and DB lines).
2. Shared host: put the site in maintenance (`php artisan down`), export the database (phpMyAdmin → Export).
3. VPS: import it (`mysql iibsom < dump.sql`), `php artisan migrate --force && php artisan optimize`.
4. Cloudflare DNS → point `iibsom.com` to the VPS IP. Photos don't move (R2).
Downtime: about 10–20 minutes.

## Watch these numbers (Admin → System health, Cloudflare analytics)
- Pages slower than 1 second
- Queue jobs waiting more than 1 minute
- Database size and slow queries
- Cloudflare cache hit ratio (aim > 60% for guest pages)
