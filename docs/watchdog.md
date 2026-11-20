# Watchdog: set-up guide

The Watchdog (Admin → Watchdog) checks the site every 5 minutes, every hour and every night, and tells you by email and/or Telegram. It runs only from the normal cron line, on shared hosting and on a VPS. It never runs while a visitor opens a page.

Do these steps once, in this order.

## 1. The cron line (you probably have it already)

Everything runs from the one Laravel cron job, every minute:

```
* * * * * cd /path/to/iibsom && php artisan schedule:run >> /dev/null 2>&1
```

- Shared hosting: cPanel → Cron Jobs → "Once per minute".
- Check it: Admin → Health → "Scheduler (cron)" says "Last run … seconds ago".

## 2. Telegram (recommended: alerts arrive in seconds)

1. In Telegram, open **@BotFather** → `/newbot` → give it a name (e.g. "iibsom alerts") and a username ending in `bot`.
2. BotFather answers with a **token** like `123456789:AA…`. Keep it secret.
3. Open your new bot and press **Start** (a bot can only write to you after you do this).
4. Find your **chat ID**: open `https://api.telegram.org/bot<TOKEN>/getUpdates` in your browser (put your token in), send any message to the bot, refresh, and look for `"chat":{"id":123456789`. That number is the chat ID. For a group: add the bot to the group, send a message there; the group's ID starts with `-`.
5. Admin → Watchdog → **Settings** → Telegram ON, paste the token and the chat ID → **Save settings** → **Send test Telegram**.

The token is stored encrypted and is never shown again or put in any message or log.

## 3. Email

1. Mail must work on the server (`MAIL_MAILER`, `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` in `.env`; see docs/deploy-shared.md or docs/deploy-vps.md).
2. Admin → Watchdog → Settings → Email ON, your address → **Save settings** → **Send test email**.
3. The daily digest email is also the daily mail test: if it fails, Telegram tells you.

## 4. Outside uptime monitor on /up (free)

The site cannot tell you that it is completely down. An outside service can. `https://your-site/up` answers **200** when the site and its database work, and **500** when the database is down. It shows no details and sets no cookies.

**UptimeRobot (free plan)** — checked on 2026-11-20 on uptimerobot.com: the free plan has 50 monitors with a 5-minute check interval. Telegram and webhooks are only on paid plans, so on the free plan you get the alert by email.

1. Sign up at uptimerobot.com (no card needed).
2. **New monitor** → type **HTTP(s)** → URL `https://iibsom.com/up` → interval 5 minutes.
3. Alert contact: your email.

## 5. Heartbeat to healthchecks.io (free, tells you on Telegram)

healthchecks.io waits for a "ping" from your server. If the pings stop (server down, cron stopped), it alerts you. Checked on 2026-11-20 on healthchecks.io/pricing: the free **Hobbyist** plan monitors 20 jobs.

1. Sign up at healthchecks.io → **Add Check** → Period **5 minutes**, Grace **10 minutes**.
2. Copy its ping URL (`https://hc-ping.com/…`).
3. Admin → Watchdog → Settings → **Heartbeat URL** → paste → Save.
4. In healthchecks.io → **Integrations** → add **Email** and/or **Telegram** (follow its steps).

While the database is down, the site pings `…/fail`, so healthchecks.io marks the check "down" at once.

## 6. Backups

The Watchdog checks backups but does not make them. Make a nightly backup (docs/deploy-vps.md → Backups), then put its folder in `.env`:

```
WATCHDOG_BACKUP_PATH=/var/backups
WATCHDOG_BACKUP_PATTERN="iibsom-*.sql.gz"
```

It checks every night that the newest backup is less than 26 hours old and not much smaller than usual, and once a week that the file opens as a database dump. On shared hosting without file access to backups, leave it empty (the Backups card then says "not watched yet").

## 7. After every update of the code

The Watchdog fingerprints the code files every night and tells you when they change. After **your own** update, run:

```
php artisan iibsom:watchdog:baseline
```

so your update is not reported as a change.

## Commands

| Command | What it does |
|---|---|
| `php artisan iibsom:watchdog` | The 5-minute checks (the cron runs it) |
| `php artisan iibsom:watchdog hourly` / `daily` / `digest` | The other groups |
| `php artisan iibsom:watchdog:baseline` | Accept the current code files as normal |
| `php artisan iibsom:drugs:check --dry-run` | List live ads with drug words (the Watchdog runs this on changed ads) |

## Privacy

- No IP address of visitors is stored. Counters (failed sign-ins, errors) live in the cache for at most 24 hours and count only "how many".
- Errors are grouped by kind and place in the code; the error message (which can hold personal data) is never kept or sent.
- Staff "new browser" uses a random id in a cookie; only its hash is kept, with a label like "Chrome on Windows".
