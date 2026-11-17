# Iibsom API v1

For the future mobile app (or a partner). Base address: `https://iibsom.com/api/v1`.

- Answers are JSON. Errors look like `{"message": "..."}`; form errors (422) also have `"errors": {"field": ["..."]}`.
- Language: add `?lang=so` (Somali) or `?lang=en` (English). Without it: the site's main language.
- Speed limit: 60 requests a minute per address (write part: 30 a minute, posting: 10 a minute). Over the limit = `429`.
- Money is whole US dollars (`"currency": "USD"`).
- Two switches (Admin → Features): **Public API** (`api`, ON) and **Write API** (`api.write`, OFF). When a switch is OFF its addresses answer `404`.

## 1. Read part (no sign-in)

No cookies and no personal data. Phone numbers stay on the website, behind "Show number".

| Method | Address | What it gives |
|---|---|---|
| GET | `/categories` | Live categories, each with `children` (sub categories) and `ads_count` |
| GET | `/categories/{slug}/fields` | The extra fields to send when posting in that sub category, and `has_condition` |
| GET | `/cities` | Live cities |
| GET | `/ads` | Live ads. Filters: `q`, `category`, `city`, `min`, `max`, `condition` (`new`/`used`), `sort` (`newest`/`cheapest`/`expensive`), `page` (max 500), `per_page` (1–50) |
| GET | `/ads/{id}` | One live ad with description, all photos, seller name |

Example:

```
curl "https://iibsom.com/api/v1/ads?category=cars&city=mogadishu&sort=cheapest&lang=so"
```

## 2. Write part (your own ads)

### Get a key

1. Sign in on the website → **Account → App access (keys)**.
2. Type a name (e.g. "My phone"). Tick **Allow this key to post…** if the app must change ads.
3. Copy the key. It looks like `iib_12_…` and is shown **only once**. Up to 5 keys; each ends after 1 year.

Send it in every request:

```
Authorization: Bearer iib_12_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

- No key, a wrong key or an ended key: `401`.
- A read-only key on a write address: `403`.
- Banned user: `403`. Writing before the profile is finished (name, city, phone) on the website: `403`.
- A website sign-in cookie does **not** work here; only keys.

### Addresses

| Method | Address | Key | What it does |
|---|---|---|---|
| GET | `/me` | read | Your id, name, city, verified, member since (no phone, no email) |
| GET | `/me/ads?page=1` | read | All your ads (any status), 20 a page, with `status`, `rejection_reason`, `photo_ids` |
| POST | `/photos` | write | Upload one photo (multipart field `photo`, JPG/PNG/WebP, max 12 MB, at least 200×200). Answer: `{"id": 55, "status": "processing"}` |
| GET | `/photos/{id}` | read | Is your photo ready? `status` is `processing`, `ready` or `failed` |
| POST | `/ads` | write | Post a new ad (see below). `201` with the ad |
| PUT | `/ads/{id}` | write | Change your ad (same fields, no `category`). A sold ad: `409` |
| POST | `/ads/{id}/sold` | write | Mark your live ad as sold. Not live: `409` |
| DELETE | `/ads/{id}` | write | Delete your ad |

Someone else's ad always answers `404`, the same as an ad that does not exist.

### Posting an ad

The **same rules as the website**: required fields, the spam check, "same ad twice", the daily limit, and approval (with "Admin approval" ON the new ad is `pending`; edits go back to `pending` too).

```
POST /api/v1/ads
Content-Type: application/json

{
  "category": "cars",
  "title": "Toyota Vitz 2012 automatic",
  "description": "Very clean car, new tyres, AC works well.",
  "price": 6500,
  "price_negotiable": false,
  "condition": "used",
  "city_id": 1,
  "area_id": 4,
  "photos": [55, 56],
  "fields": {"brand": "toyota", "model": "Vitz", "year": 2012}
}
```

- `category`: a sub category slug from `/categories`.
- `price`: needed unless `price_negotiable` is `true`.
- `condition`: only when the category `has_condition` (see `/categories/{slug}/fields`).
- `photos`: ids from `POST /photos`, in the order to show (first = cover). Only your own photos are used.
- `fields`: the keys from `/categories/{slug}/fields`; `select` fields take one of the `options` values.

## 3. Safety notes

- Only a hash of each key is stored; the key itself cannot be read back.
- Lost phone? Delete the key at **Account → App access**; it stops at once.
- Every key creation and deletion is written to the security log.
- No address in this API stores the visitor's IP.
