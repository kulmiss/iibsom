# Small choices taken by Claude (Watchdog, PR 94–96)

The owner said: "take your recommended option for small choices, log them here". Each can be changed later.

| Date | PR | Choice | Why |
|---|---|---|---|
| 2026-11-20 | 94 | Started from `main` (there is no `develop`; #80 was merged on 2026-11-18). The Watchdog stack sits on PR 93. | Avoids conflicts in DECISIONS / handover. |
| 2026-11-20 | 94 | 🟠 Warnings are sent **once** (no reminder); only 🔴 urgent problems get a reminder (every 6 hours by default). | Your rule "Warning: send now, once". Warnings still show in the daily digest until fixed. |
| 2026-11-20 | 94 | During quiet hours a warning waits and is sent when quiet hours end (only if it is still open). A warning fixed during the night is never sent. | No messages at night for things that fixed themselves. |
| 2026-11-20 | 94 | "Mark as fixed" closes the problem. If the check still finds it, it opens again as a new problem and is sent again. | You always learn when "fixed" was not really fixed. Use "Mute" to only stop reminders. |
| 2026-11-20 | 94 | A problem that gets worse (warning → urgent) is sent again. | Disk 85% → 95% must reach you. |
| 2026-11-20 | 94 | Page speed: warning only after **two** slow checks in a row (default limit 3,000 ms); not opening = urgent at once. | One slow moment is normal; two in a row is a real problem. |
| 2026-11-20 | 94 | The speed check opens the real pages over the internet (APP_URL), the way a visitor does. | Measures what visitors get (web server, PHP, cache). It does not add ad views (views are counted by a separate beacon). |
| 2026-11-20 | 94 | Errors are grouped by kind + place (class, file, line). The error message is never kept or sent. | Messages can hold personal data (emails, phone numbers, SQL values). |
| 2026-11-20 | 94 | The Watchdog's memory (last run, digest sent, backup test) is a small file `storage/app/watchdog/state.json`, not the database. | It must work while the database is down, and a settings change empties the page cache. |
| 2026-11-20 | 94 | While the database is down: one 🔴 alert (from the last saved channel copy, encrypted), a reminder every 6 hours, "✅ Fixed" when back. The heartbeat URL gets `/fail`. | Nothing can be stored in the database then. healthchecks.io understands `/fail`. |
| 2026-11-20 | 94 | Backups: the backup folder is set in `.env` (`WATCHDOG_BACKUP_PATH`), not in the admin. | An admin screen that can read any folder on the server is a risk. |
| 2026-11-20 | 94 | The weekly backup test reads the start of the newest file (gzip) and looks for a MySQL / MariaDB dump header. A failed test is tried again every day. | "Opens" without a restore on the live site. |
| 2026-11-20 | 94 | Daily mail test: only with a real mailer (not `log`), the email channel ON and an address. | No test mails on your computer. |
| 2026-11-20 | 94 | "Live site" = any address that is not localhost, an IP, `.test`/`.local`, or starts with staging/dev/test/qa/preview. APP_DEBUG / APP_ENV and SSL are checked only there. | Your computer and a staging site may use debug. |
| 2026-11-20 | 94 | The Watchdog switch is ON by default. It sends nothing until you add an email address or a Telegram bot. | Safe default. |
