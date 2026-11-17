# LATER: things the owner must do or decide

Tick `[x]` with the date when done.

## Before going live
- [ ] Make the GitHub repo **private** again (was public for CI testing on 2026-11-16)
- [ ] Hosting details: panel + host name (H1), PHP 8.3+ available (H2), SSH + cron every minute (H3)
- [ ] What else uses iibsom.com today, e.g. email or an old site (C)
- [ ] Google sign-in keys → `.env` (docs/google-login.md)
- [ ] Cloudflare account + R2 bucket + keys → `.env` (docs/cloudflare-r2.md, comes with the deploy PR)
- [ ] Point iibsom.com DNS to Cloudflare; SSL "Full (strict)"; set `TRUSTED_PROXIES=*` in `.env`
- [ ] Hosting panel: turn off or shorten web server access logs (they keep visitor IPs)
- [ ] Live `.env`: `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Native Somali speaker reviews `docs/somali-review/somali-texts.csv` (pictures in `docs/somali-review/screens`; how-to in its README)
- [ ] Check and approve the **draft** Terms and Privacy text (admin → Pages); add Somali versions
- [ ] Add Somali bad words to admin → Settings → General → Bad words
- [ ] Set **Help email for customers** (admin → Settings → General). Help articles tell people to write to it (e.g. to delete their account).
- [ ] About page says "use the contact details below" but has none: add them (admin → Pages → About)
- [ ] Delete all demo data before launch (admin → Settings → Delete all demo data)

- [ ] Admin → Settings → General → **Weekly summary email to**: add your email, press "Send me a test now" (needs working MAIL_ settings in `.env`)
- [ ] Create your Super Admin account on the live site: `php artisan iibsom:staff you@iibsom.com` and set up 2FA

## After going live
- [ ] Add iibsom.com to Google Search Console and submit https://iibsom.com/sitemap.xml

## Can wait
- [ ] Choose which Phase 2/3 switches to turn on (recommended first: Favourites, Saved search alerts, Ad stats; see docs/features.md)
- [ ] SMS provider for phone login (S1), only if phone login is wanted
- [ ] Choose staff accounts (Moderator / Support) and give them 2FA apps
- [ ] **Boosts (when you want to sell them):** real prices for Top / VIP / Diamond (edit `config/boosts.php` or tell the developer), and your merchant numbers for EVC Plus, Zaad, Sahal, eDahab (admin → Settings → General → Payments for boosts). Then turn **Test mode** OFF and the **Paid boosts** switch ON.
- [ ] **Auto-post (when you want it):** make the Telegram bot/channel and/or Facebook Page token (docs/social-autopost.md), put the keys in `.env`, turn **Auto-post** ON, post one test ad and check System health.
- [ ] **Buyers abroad (when you want it):** type the exchange rates in admin → Settings → General → Prices for buyers abroad, then turn the switch ON. Update the rates about once a month.
- [ ] **Shop plans:** decide the monthly price for shop pages (turned on by hand in admin → Shops for now).
