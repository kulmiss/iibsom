# Hosting modes: VPS and shared hosting

The same code runs on both. You choose the mode in `.env`:

```dotenv
IIBSOM_HOSTING=vps      # or: shared
```

## Settings for each mode

| `.env` setting | VPS (recommended for growth) | Shared hosting (cPanel etc.) |
|----------------|------------------------------|------------------------------|
| `IIBSOM_HOSTING` | `vps` | `shared` |
| `CACHE_STORE` | `redis` | `database` |
| `SESSION_DRIVER` | `redis` | `database` |
| `QUEUE_CONNECTION` | `redis` | `database` |
| `FILESYSTEM_DISK` (photos) | `r2` | `r2` |
| Search | database, Meilisearch later | database |

## What runs in the background

| Job | VPS | Shared hosting |
|-----|-----|----------------|
| Scheduler (expire ads, heartbeat) | cron, every minute | cron, every minute |
| Queue (resize photos, send emails) | always-running worker (Supervisor) | cron runs the queue every minute (`--stop-when-empty`) |

**The single cron job (both modes):**
```
* * * * * cd /path/to/iibsom && php artisan schedule:run >> /dev/null 2>&1
```

## Check the server

```bash
php artisan iibsom:health
```
It shows OK / WARN / FAIL for: PHP version, hosting mode, database, cache, scheduler (cron), background jobs, file storage, photo processing (Imagick or GD), and debug mode. Each problem comes with a fix. The admin panel will show the same checks on its Health page.

## Limits on shared hosting
- Photos appear 1–60 seconds after upload, because cron runs the resize job.
- Busy times can be slower, because the host limits CPU. Cloudflare caching hides most of this.
- No Meilisearch. Database search is fine up to about 50k–100k ads.
- Moving to a VPS later is a settings change, plus copying the database and `.env`.
