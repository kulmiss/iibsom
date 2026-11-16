# LATER: things the owner must do or decide

Tick `[x]` with the date when done.

## Before going live
- [ ] Make the GitHub repo **private** again (was public for CI testing on 2026-11-16)
- [ ] Hosting details: panel + host name (H1), PHP 8.3+ available (H2), SSH + cron every minute (H3)
- [ ] What else uses iibsom.com today, e.g. email or an old site (C)
- [ ] Google sign-in keys → `.env` (docs/google-signin.md)
- [ ] Cloudflare account + R2 bucket + keys → `.env` (docs/cloudflare-r2.md, comes with the deploy PR)
- [ ] Point iibsom.com DNS to Cloudflare
- [ ] Native Somali speaker reviews the translation CSV (`php artisan iibsom:translations:export so`)
- [ ] Write/approve Terms, Privacy, About and Safety pages (admin → Pages)
- [ ] Delete all demo data before launch (admin → Settings → Delete all demo data)

- [ ] Create your Super Admin account on the live site: `php artisan iibsom:staff you@iibsom.com` and set up 2FA

## Can wait
- [ ] SMS provider for phone login (S1), only if phone login is wanted
- [ ] Choose staff accounts (Moderator / Support) and give them 2FA apps
