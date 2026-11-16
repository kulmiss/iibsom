# Google sign-in: get your keys (10 minutes)

1. Open <https://console.cloud.google.com/> and sign in with the Google account that will own Iibsom.
2. Top bar → **Select a project** → **New project** → name it `iibsom` → **Create**.
3. Left menu → **APIs & Services** → **OAuth consent screen**:
   - User type: **External** → Create
   - App name: `iibsom`, support email: your email, logo (optional)
   - Authorised domain: `iibsom.com`
   - Scopes: keep the defaults (`email`, `profile`, `openid`)
   - **Publish app** (so everyone can sign in, not only test users)
4. Left menu → **Credentials** → **Create credentials** → **OAuth client ID**:
   - Application type: **Web application**, name `iibsom web`
   - **Authorised redirect URIs**, add both:
     - `http://localhost:8095/auth/google/callback` (your PC)
     - `https://iibsom.com/auth/google/callback` (live site)
   - **Create**. Copy the **Client ID** and **Client secret**.
5. Put them in `.env` (never in GitHub):
   ```dotenv
   GOOGLE_CLIENT_ID=xxxx.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=xxxx
   GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
   ```
6. Run `php artisan config:clear`, open http://localhost:8095/login → **Continue with Google**.

**Safety built in:** state check (stops fake logins), only verified Google emails, banned users blocked, new session after sign-in, every sign-in written to `storage/logs/security.log`.
