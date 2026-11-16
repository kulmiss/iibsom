# Photos on Cloudflare R2 (and the site behind Cloudflare)

Ad photos and banners are stored on **R2**, not on the web server: it's cheap (10 GB free, no download fees),
fast (Cloudflare CDN) and the same for shared hosting and VPS.

## 1. Cloudflare account and the domain
1. Sign up at https://dash.cloudflare.com (free plan is fine).
2. **Add a site** → `iibsom.com` → Free → Cloudflare shows 2 nameservers.
3. At the place where you bought iibsom.com, replace the nameservers with Cloudflare's. Wait (minutes to a few hours).
4. Cloudflare → **SSL/TLS** → mode **Full (strict)**. **Always Use HTTPS**: ON.
5. In `.env`: `TRUSTED_PROXIES=*`.

## 2. Bucket
1. Cloudflare → **R2** → *Create bucket* → name `iibsom-photos`, location *Automatic*.
2. Bucket → **Settings** → **Custom domain** → `photos.iibsom.com` → Connect. (Cloudflare makes the DNS record.)

## 3. Keys
R2 → **Manage R2 API Tokens** → *Create API token*:
- Permission: **Object Read & Write**, only bucket `iibsom-photos`
- Copy **Access Key ID**, **Secret Access Key** and the **S3 endpoint** (`https://<account-id>.r2.cloudflarestorage.com`).

## 4. `.env`
```dotenv
PHOTOS_DISK=r2
R2_ACCESS_KEY_ID=xxxx
R2_SECRET_ACCESS_KEY=xxxx
R2_BUCKET=iibsom-photos
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
R2_PUBLIC_URL=https://photos.iibsom.com
```
Then `php artisan optimize` and check Admin → **System health** → *File storage* is OK.
Post a test ad with a photo: the photo address starts with `https://photos.iibsom.com/ads/...`.

## 5. Cache rules (faster pages)
Cloudflare → **Caching** → **Cache Rules** → *Create rule* "Guest pages":
- When: hostname is `iibsom.com` **and** the request has **no cookie** containing `iibsom-session`
  **and** URI path does not start with `/admin`, `/account`, `/post`, `/login`, `/auth`, `/api`
- Then: **Eligible for cache**, Edge TTL: *Use cache-control header* (iibsom sends 2 minutes for guests).

Signed-in people always have the session cookie, so they never get a cached page.
Photos (`photos.iibsom.com`) are cached by Cloudflare automatically (1 year; every photo has a unique name).

## Cost
Free up to 10 GB stored and 10 million reads a month. About 100,000 ads with 4 photos ≈ 15–25 GB → a few dollars a month.
