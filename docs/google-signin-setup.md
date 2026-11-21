# Google sign-in: set up the keys for the live site

About 15 minutes. You need the Google account that will own iibsom, and the real domain (`https://iibsom.com`).
Until the keys are in `.env`, the **Continue with Google** button hides itself and **email + password** sign-in still works.

> Email sign-in (sign-up confirmation and "Forgot password") also needs real email (SMTP) on the live site.
> Without it, nobody gets the links and nobody can finish signing up by email. See `docs/deploy-shared.md` → mail.

## 1. Make a Google Cloud project

1. Open <https://console.cloud.google.com/> and sign in.
2. At the top, click the project list → **New project**. Name: `iibsom` → **Create**. Make sure `iibsom` is selected at the top.

## 2. The consent screen (what people see at Google)

Google has renamed these menus over time. If a name below is different, look for the same words in the left menu (**Google Auth Platform** or **APIs & Services → OAuth consent screen**).

1. Left menu → **Google Auth Platform** → **Branding** (or **OAuth consent screen**) → **Get started**.
2. **App name:** `iibsom`. **User support email:** your email.
3. **Audience:** **External**.
4. **Contact email:** your email. Accept the policy → **Create**.
5. **Branding** page:
   - **App home page:** `https://iibsom.com`
   - **Privacy policy:** `https://iibsom.com/page/privacy`
   - **Terms of service:** `https://iibsom.com/page/terms`
   - **Authorised domains:** `iibsom.com`
   - The logo is optional. A logo makes Google check the app first, which can take days. You can add it later.
6. **Data access** (scopes): keep the defaults only (`openid`, `.../auth/userinfo.email`, `.../auth/userinfo.profile`). Don't add more: more scopes need a Google review.
7. **Audience** → **Publish app** → confirm. While the app is in "Testing", only the test users you add can sign in.

## 3. The keys (OAuth client)

1. Left menu → **Clients** (or **Credentials** → **Create credentials** → **OAuth client ID**).
2. **Application type:** **Web application**. **Name:** `iibsom web`.
3. **Authorised JavaScript origins:** leave this empty (it's not needed).
4. **Authorised redirect URIs** → add each one exactly, with no space and no slash at the end:
   - `https://iibsom.com/auth/google/callback` (the live site)
   - `https://www.iibsom.com/auth/google/callback` (only if people can open the site with `www.`)
   - `http://127.0.0.1:8095/auth/google/callback` (your own computer, for testing)
5. **Create**. Copy the **Client ID** and the **Client secret**. Keep the secret private: never put it in GitHub, a chat or a screenshot.

## 4. Put the keys on the server

In the live site's `.env` (cPanel → File Manager, or SSH):

```dotenv
APP_URL=https://iibsom.com
GOOGLE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxxxxxxx
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

Then run `php artisan config:clear` (or `php artisan config:cache` on a VPS).
`APP_URL` must be the exact address people use (https, with or without `www`), or Google answers **redirect_uri_mismatch**.

## 5. Test it (5 minutes)

1. Open `https://iibsom.com/login` in a private window. You should see **Continue with Google**. If you don't, the keys are missing or the switch is OFF (Admin → Feature switches → **Continue with Google**).
2. Click it, choose an account → you come back to **Almost done** (new account) or the home page.
3. Admin → Users: the new person is there.
4. Sign out, then sign in again: it should go straight back in.
5. Try the Somali page (`/so/login`): after Google you come back to Somali pages.

## If something goes wrong

| What you see | Why | Fix |
|---|---|---|
| `Error 400: redirect_uri_mismatch` | The address in step 3.4 isn't exactly the same as `APP_URL` + `/auth/google/callback` | Copy the address Google shows in the error into step 3.4 and save; wait 5 minutes |
| `Access blocked: app has not completed verification` | The app is still in "Testing" | Step 2.7: **Publish app** |
| "Sign-in with Google is not set up yet" | Keys missing in `.env`, or the config cache is old | Step 4, then `php artisan config:clear` |
| No Google button at all | Keys missing, or the switch is OFF | Step 4, or Admin → Feature switches |
| "Your Google email is not verified" | That Google account has no confirmed email | The person confirms it at Google first |

## Built-in safety

- State check (stops fake sign-ins); only Google accounts with a confirmed email.
- Blocked people can't sign in; a new session id after each sign-in.
- If someone had started an email sign-up with that address and never confirmed it, that password is removed when the real owner signs in with Google.
- Every sign-in is written to `storage/logs/security.log` (user number only, no email).
