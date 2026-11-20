# Watchdog

**Watchdog** (menu: System → Watchdog) watches the site day and night and tells you by **email** and/or **Telegram** when something is wrong. Only the Super Admin can open it.

## The Overview tab

- At the top, one big status: **✅ All good**, **🟠 Warnings** or **🔴 Problems**, and when the last check ran.
- Four cards: **Site health**, **Security**, **Ad safety** and **Backups**, each with its own colour and the time of its last check.
- **Open problems**: each one says what happened, when it was first and last seen, how many times, and what to do first.
  - **Mark as fixed**: you fixed it. If the check still finds it, it opens again and you are told again.
  - **Mute**: no reminders for 1 hour to 7 days. It stays on the list.
- **Please check**: ads that look odd, for example a price under half of the usual price. Nothing was hidden or banned. Press **Open ad**, decide, then **Mark as fixed**; that ad is not asked about again.

## How you are told

- 🔴 **Urgent**: sent at once, and again every few hours (setting) until it is fixed.
- 🟠 **Warning**: sent once. During **quiet hours** it waits until the morning.
- 🟢 **Info**: never sent alone; it is in the **daily digest**.
- **✅ Fixed** comes when a problem that was sent is back to normal.
- The **daily digest** (08:00 by default) has yesterday's numbers (new users, ads, approved / rejected, reports, top categories and places, errors, speed) and the open problems. On Mondays it also has the last 7 days.
- Messages never contain passwords, tokens, email addresses or phone numbers.

## The History tab

Everything from the last 30 days (older is deleted every night). Filter by level and area, or search a title.

## The Settings tab

- **Where alerts go**: your email address, and a Telegram bot token + chat ID. Save, then press **Send test email** / **Send test Telegram**.
- **When**: digest time, how often urgent problems are sent again, quiet hours, and an optional **heartbeat URL** (healthchecks.io).
- **Checks**: every check can be turned off, and most have a number (limit). The line under each check says what it does.

To set up Telegram, the outside uptime monitor and the heartbeat, follow **docs/watchdog.md** (step by step).

## When the whole site is down

The site cannot tell you that it is down. Two free outside services do: an **uptime monitor** that opens `https://your-site/up` every 5 minutes, and **healthchecks.io**, which waits for the heartbeat ping and tells you when it stops. See docs/watchdog.md.

The Watchdog switch is in **Feature switches** ("Watchdog"). OFF = no checks and no alerts.
