# Demo data

Fake but realistic data for local testing and demos. **Never on the live site.**

```bash
php artisan iib:demo-reset            # delete old demo data, make new (≈1 minute with photos)
php artisan iib:demo-reset --no-photos  # faster, ads without photos
```

You get:
- 500 ads, **all live and all with photos** (owner, 2026-11-21), over the last 110 days; every sub category has at least 3 ads
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
Each demo title in `database/demo/content.php` has "photo words"; an ad only shows photos of the same kind (a Toyota ad never shows a Nissan). Every title has photo words, so every ad gets 1–3 photos (with `--no-photos`: none).
Don't like a photo? Delete it, put its `source_url` in `database/demo/photos-rejected.json`, run `php tools/download-demo-photos`.
To get new ones:

```bash
php tools/download-demo-photos             # fills missing photos
PEXELS_API_KEY=xxx php tools/download-demo-photos --fresh   # use Pexels instead
php tools/download-demo-photos --credits   # after deleting a photo you don't like
```

Without a key the tool uses Openverse, then Wikimedia Commons when Openverse has too few (its free limit is about 200 searches a day). Both: only CC0, Public Domain or CC BY photos (never SA, NC or ND). Kinds used by 2+ titles get 3 photos, others 2.
