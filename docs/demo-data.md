# Demo data

Fake but realistic data for local testing and demos. **Never on the live site.**

```bash
php artisan iib:demo-reset            # delete old demo data, make new (≈1 minute with photos)
php artisan iib:demo-reset --no-photos  # faster, ads without photos
```

You get:
- 324 ads in all categories (live, waiting, rejected, sold, expired), over the last 110 days
- 64 customers (2 banned), 18 reports, admin activity history
- 2 demo staff with 2FA ready: `demo-moderator@demo.iibsom.test` and `demo-support@demo.iibsom.test`.
  The command prints their password and 2FA key once. Add the key to an authenticator app
  (Google Authenticator, Authy…) as "enter a setup key".

Rules:
- Everything has `is_demo = true`. Admin → Settings → Demo data → **Delete all demo data** removes exactly that.
- Demo ads only show while the switch **Show demo data** is ON (the command turns it ON).
- The command and `DemoSeeder` refuse to run when `APP_ENV=production`.

## Photos

Real free-licence photos are in `database/demo/photos/` (list: `database/demo/photos.json`, credits: `CREDITS.md`).
To get new ones:

```bash
php tools/download-demo-photos             # fills missing photos
PEXELS_API_KEY=xxx php tools/download-demo-photos --fresh   # use Pexels instead
php tools/download-demo-photos --credits   # after deleting a photo you don't like
```

Without a key the tool uses Openverse and only takes CC0, Public Domain or CC BY photos.
