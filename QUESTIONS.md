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
| 2026-11-20 | 95 | Staff "new browser": each browser gets a random id in a cookie; only its hash is stored, with "Chrome on Windows". No IP. For the Super Admin it is a 🟠 warning; for other staff 🟢 info (digest). | Your rule: no IP storage; the label is enough to recognise your own phone or computer. |
| 2026-11-20 | 95 | Staff changes (new account, role, 2-step reset) are read from the existing activity log every 5 minutes, not sent from inside the staff member's click. | The admin stays fast; one place for the rules. |
| 2026-11-20 | 95 | Failed Google sign-ins of users: warning at 3× the staff limit (30 in 15 min by default). | Users fail more often than staff (cancel, slow network). |
| 2026-11-20 | 95 | Code files: a change is told **once** and becomes the new normal. After each update run `php artisan iibsom:watchdog:baseline`. Folders: app, bootstrap/app.php, config, routes, resources/views, public/index.php, public/.htaccess, composer.lock (not vendor: too big for shared hosting). | One message per change, no daily repeats. |
| 2026-11-20 | 95 | PHP in uploads: checked every hour (not only daily), urgent. Also .phtml, .phar, .htaccess and similar. | It is the most dangerous finding; an hour is cheap. |
| 2026-11-20 | 95 | Same photo = the same 64-bit photo fingerprint (saved on every new photo from now on; older photos have none). Same text = exactly the same description (30+ letters). | Exact match: no false alarms. A smaller copy of a photo usually matches too, not always. |
| 2026-11-20 | 95 | Scam prices: under half of the usual low price (the same rule as the ad page, PR 83), ads of the last 2 days, max 200 per hour. "Mark as fixed" = looked at, never asked again for that ad. | Matches what buyers already see. |
| 2026-11-20 | 95 | Drug check: only live ads changed since the last look (every 5 minutes); found ads go back to Waiting with the Drugs mark (the same as `iibsom:drugs:check`), one message per batch. | Cheap; posting already refuses drug words. |
| 2026-11-20 | 95 | The daily digest has yesterday's numbers (from the weekly summary code). On Mondays it also has "Last 7 days" — one message, not a second weekly one. The weekly summary email (PR 41) stays as it is. | No double messages. |
| 2026-11-20 | 95 | The digest email is the daily mail test when it went out; otherwise a short test mail is sent at night. | One email a day, not two. |
| 2026-11-20 | 96 | The Watchdog page has 3 tabs: Overview, History, Settings. The big status counts only urgent and warning problems; 🟢 notes (like "Backups are not watched yet") show on the card as "All good · 1 note". | One clear answer at the top. |
| 2026-11-20 | 96 | Mute choices: 1 h, 4 h, 12 h, 1 day, 3 days, 7 days. | Simple list for a phone. |
| 2026-11-20 | 96 | Limits that would alert all the time are raised when saved: disk warning stays under 90% (90% is always urgent), page speed at least 500 ms, others at least 1. | A typo must not flood your phone. |
| 2026-11-20 | 96 | The Telegram token is never sent back to the browser; the page only says "a token is saved". Leave the box empty to keep it. | Your rule: no secrets anywhere they can leak. |
| 2026-11-20 | 96 | No "Check now" button. | The speed check opens the site itself; on a small server (or `php artisan serve`) a page that calls itself can hang. The 5-minute cron is soon enough. |
| 2026-11-20 | 96 | The whole-Watchdog switch stays in Feature switches ("Watchdog"); while it is OFF the menu item and page are hidden. | Same place as every other switch. |
| 2026-11-20 | 96 | **Home side panels: no icon next to each sub category** (only name + count, like Jiji). | PR 93's 141 sub categories made the home page ~100 KB heavier (also on phones, where the panels are hidden) and the phone speed check (Lighthouse) fell to 84 (limit 85). Without the icons the page is back near its old size. Easy to undo if you prefer the icons. |
| 2026-11-20 | 98 | Owner chose "icons only, like the categories" (asked in chat), so no photo upload was built. | Owner's answer. |
| 2026-11-20 | 98 | The sub-category icon is drawn by CSS (a mask of the icon file from /build, in the text colour) and only asked for when a panel opens. Not drawn into the page as before. | 141 inline icons made the page ~100 KB heavier and broke the phone speed check. This way the page is even lighter than before (585 KiB vs 611 KiB) and dark mode still works. |
| 2026-11-20 | 98 | Switch "Home: icons next to sub categories" (`home.sub_icons`, ON) on Admin → Home page. OFF = names and counts only. | Owner asked for a switch. |
| 2026-11-20 | 98 | Icon file missing → the first letter of the sub category in the same box. | Never an empty or broken box. |
| 2026-11-20 | 98 | Fixed on the way: the admin icon picker listed our own social logos, which it cannot draw (console error). They are no longer offered. | Found by the browser check. |
| 2026-11-20 | 99 | Feature switches: sections by what a switch changes (from its name), Phase 2/3 at the end; one column (open/closed cards of different heights look messy in two); closed sections remembered in this browser; while searching every match is shown open. | Easy to find; nothing hidden by mistake. |
| 2026-11-20 | 100 | The intro under the title is turned OFF (switch `seo.intro`), not deleted. | It helped Google a little; you can switch it back ON. |
| 2026-11-20 | 100 | "No scrollbars" covers the customer website only (not the admin), and also text boxes (they still scroll). | Your words: all except the page's own. Tell me if you want them back in text boxes or in the admin. |
| 2026-11-21 | 105 | Passwords: at least 8 characters with letters and numbers; no check against leaked-password lists. | That check calls an outside website, which shared hosting may block. |
| 2026-11-21 | 105 | The email link only confirms the address; the person then signs in with their password (it does not sign them in). | A link made by somebody else (who typed your email) can never sign anybody in. |
| 2026-11-21 | 105 | Signing up again with an unconfirmed email replaces the name and password, and older links stop working. | People who mistype their password can simply sign up again. |
| 2026-11-21 | 105 | Confirm links work 24 hours; reset links 60 minutes, once. | Slow internet: people read email later. Reset links are more dangerous, so shorter. |
| 2026-11-21 | 105 | At most 3 sign-up emails an hour to one address; 5 wrong passwords per visitor in 5 minutes and 20 per email in 15 minutes. | Stops inbox flooding and password guessing. |
| 2026-11-21 | 105 | Staff can't use the email sign-in or the reset link on the customer site. | Staff sign in at /admin with a 2-step code. |
| 2026-11-21 | 105 | Google button hides when its keys are missing. | Your request; no broken button on a new site. |
| 2026-11-21 | 105 | The test sign-in box also needs a local address (127.0.0.1, localhost, *.test). | Your request: it stays closed even if APP_ENV is set wrong on the live server. |
| 2026-11-21 | 106 | Launch setup also turns OFF demo data, boosts, shops, banners, price guide, trusted sellers, write API, app, chat, ratings and phone login; it never touches any other switch. | The list is shown first and only those switches change. |
| 2026-11-21 | 106 | Launch setup changes exactly the list it showed; if switches changed in the meantime it refuses and asks you to open it again. | Nothing changes that you did not see. |
| 2026-11-21 | 106 | "Needs … (OFF)" is shown only for switches that need the other one completely (not for ones that work partly without it). | No false warnings. |
| 2026-11-21 | 106 | Fixed on the way: with My ads OFF and Ad statistics ON, the account page showed Views / Contacts tiles linking to a page that does not open. Those tiles now follow My ads. | Found because Ad statistics became ON by default. |
