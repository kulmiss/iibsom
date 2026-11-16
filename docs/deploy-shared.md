# Put iibsom live on shared hosting (cPanel / DirectAdmin / Plesk)

About 1 hour the first time. Read [hosting-modes.md](hosting-modes.md) for what changes in shared mode.

## What the host must have
- PHP **8.3 or newer** with: `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd` (or `imagick`), `intl`, `zip`, `bcmath`
- MySQL 8 (or MariaDB 10.6+) database
- **Cron jobs** that can run every minute
- SSH is nice to have (not required)

Check in the panel: *Select PHP version* / *MultiPHP Manager*. Ask the host if something is missing.
(Open questions H1–H3 in DECISIONS.md are about this.)

## 1. Make the upload file (on your computer)
```bash
git pull
bash tools/make-release
```
You get `iibsom-release-YYYYMMDD-HHMM.zip` (about 40 MB). It already has `vendor/`, the built website files and the admin panel. No Node or Composer is needed on the server.

## 2. Database
Panel → **MySQL Databases**: create a database (e.g. `user_iibsom`), a user with a strong password, and give the user **ALL PRIVILEGES** on the database.

## 3. Upload
1. Panel → **File Manager** → go to your home folder (NOT `public_html`) → make a folder `iibsom`.
2. Upload the zip into `iibsom` and **Extract** it.
3. Make the website show the `public` folder:
   - **Best:** Panel → *Domains* → iibsom.com → **Document root** = `iibsom/public`.
   - **If the panel can't change it:** move everything inside `iibsom/public/` into `public_html/`, then edit `public_html/index.php`
     and change the two `__DIR__.'/../` paths to `__DIR__.'/../iibsom/`.

## 4. `.env` (settings)
Copy `.env.example` to `.env` (File Manager → Copy), then edit it:
```dotenv
APP_NAME=iibsom
APP_ENV=production
APP_DEBUG=false
APP_URL=https://iibsom.com
HOSTING_MODE=shared

DB_HOST=localhost
DB_DATABASE=user_iibsom
DB_USERNAME=user_iibsom
DB_PASSWORD=the-password

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

PHOTOS_DISK=r2          # see cloudflare-r2.md
TRUSTED_PROXIES=*       # when the site is behind Cloudflare
GOOGLE_CLIENT_ID=...    # see google-login.md
GOOGLE_CLIENT_SECRET=...
MAIL_HOST=...           # your host's email (SMTP) settings
```

## 5. First run
**With SSH** (Terminal in the panel):
```bash
cd ~/iibsom
php artisan key:generate --force
php artisan migrate --seed --force      # tables + Somalia places + categories + pages (no demo data)
php artisan storage:link
php artisan optimize                    # caches config, routes and views = faster
php artisan iibsom:staff you@iibsom.com # your Super Admin account (prints a one-time password)
php artisan iibsom:health               # everything should be OK
```
**Without SSH:** Panel → Cron Jobs → add each command once as a one-time job (`cd ~/iibsom && php artisan ...`), wait a minute, then remove it. Ask the host to run them if that's easier.

## 6. Cron job (required)
Panel → **Cron Jobs** → every minute (`* * * * *`):
```
cd ~/iibsom && php artisan schedule:run >> /dev/null 2>&1
```
This expires old ads, resizes photos (queue) and shows the heartbeat on the admin Health page.

## 7. Check
- https://iibsom.com opens; https://iibsom.com/admin → sign in → set up 2FA on your phone.
- Admin → **System health**: all green.
- Post a test ad with a photo → approve it → it shows on the home page.

## Updating later
1. `bash tools/make-release` on your computer.
2. Upload and extract over `iibsom/` (your `.env` and `storage/` are not in the zip, so they stay).
3. Run `php artisan migrate --force && php artisan optimize` (SSH or one-time cron).

## Problems
| You see | Fix |
|---------|-----|
| 500 error | `storage/logs/laravel.log`; often wrong DB details or `APP_KEY` missing |
| Photos stay "Uploading…" | Cron job missing → step 6 |
| Page without styles | Document root is not `public` → step 3 |
| "Not secure" / redirects loop | Cloudflare SSL must be **Full (strict)** and `TRUSTED_PROXIES=*` |
