# Auto-post new ads to Telegram and Facebook

Switch: **Auto-post to Facebook and Telegram** (`social.autopost`, Phase 3, OFF).

When it is ON, each new ad is shared **once**, the first time it goes live (after approval, or at once when approval is OFF). Edits and renewals are never shared again. Demo ads are never shared. Only the networks whose keys are in `.env` are used: you can use one, both or none.

The post is the title, then "price · city · category", then the link to the ad. Telegram and Facebook show the ad's photo from the link preview.

It runs in the background (the queue), so posting is never slower. If a network says no, it tries 2 more times (after 1 and 5 minutes) and then gives up. The ad and the seller are not affected. **Admin → System health** shows how many were shared or failed in the last 24 hours, with the reason.

Keys are only in `.env` (never in the database, never in git, never shown in the admin).

## Telegram channel (easiest, ~5 minutes)

1. In Telegram, open **@BotFather** → `/newbot` → give a name (e.g. "iibsom ads") → copy the **token** (looks like `123456:ABC...`).
2. Make your channel (e.g. `@iibsom`) or open it → **Administrators** → **Add admin** → choose your bot → allow **Post messages**.
3. In `.env` on the server:

   ```
   TELEGRAM_BOT_TOKEN=123456:ABC...
   TELEGRAM_CHAT_ID=@iibsom
   ```

   (For a private channel use its number, which starts with `-100`.)
4. Run `php artisan config:cache` (if you use it), then turn the switch ON.

## Facebook Page (~20 minutes, needs a Meta developer account)

1. Go to developers.facebook.com → **My Apps** → **Create app** (Meta changes these screens often; pick the option for managing a Page).
2. Open the **Graph API Explorer**, choose your app, and ask for a **Page access token** for the iibsom Page with the permissions `pages_manage_posts` and `pages_read_engagement`.
3. Check it works: in the Explorer, `GET /me` with that token should show the Page name.
4. Make it **long-lived** (Access Token Debugger → Extend). A long-lived Page token does not end as long as the person who made it stays an admin of the Page and keeps the password.
5. Copy the Page ID (Page → About → Page transparency, or from the Explorer).
6. In `.env`:

   ```
   FACEBOOK_PAGE_ID=1234567890
   FACEBOOK_PAGE_TOKEN=EAAB...
   ```

7. The app can stay in **Development** mode while only you (a Page admin) use the token.

## Turning it off

Turn the switch OFF: nothing more is shared; ads waiting in the queue are skipped. Removing the keys from `.env` does the same for one network.

## For developers

- Code: `app/Support/Social` (`SocialNetwork` interface, `TelegramChannel`, `FacebookPage`, `SocialPosts`), job `App\Jobs\PostAdToSocial`, table `social_posts` (one row per ad per network; unique key = never twice).
- Add a network: a class that implements `SocialNetwork`, listed in `SocialPosts::NETWORKS`.
- Error messages are cleaned of keys before they are stored or logged.
