# Feature switches: what each one does and when to turn it on

Admin → **Feature switches**. Every switch takes effect at once; OFF = the feature disappears and its pages answer 404. Nothing is lost when you turn one OFF.

## Phase 1 (ON at launch)

Sign-in with Google, posting, admin approval of every ad, ad end dates, reports, similar ads, "what the seller changed", My Ads, bad words / no links / bot trap, WhatsApp / call / share, safety box, home banners, language switch, installable website, SEO brand pages, weekly email, Recommended for you, Help page, setup wizard, admin guide, welcome tour, page cache, public read API.

OFF on purpose: **Phone + SMS login** (needs an SMS provider), **Trusted sellers skip approval** (decision #9), **Show demo data** (never on the live site).

## Phase 2 and 3 (all built, all OFF)

| Switch | What people see | Needs before ON | Turn on |
|---|---|---|---|
| `favourites` Favourites | "Save" on ad pages, Saved ads page | nothing | **1st wave** |
| `saved_searches` Saved search alerts | "Save this search", daily email | working email (MAIL_ in .env) | **1st wave** |
| `ad_stats` Ad stats | sellers see views and taps | nothing | **1st wave** |
| `verified_badge` Verified badge | sellers send an ID photo, staff check | a Moderator to check ID photos | 2nd wave |
| `chat` In-website chat | "Chat with seller", Messages | staff ready for "Report" questions | 2nd wave |
| `seller_ratings` Seller ratings | stars from the chat | **chat ON** | after chat has run a few weeks |
| `price_suggestions` Price guide | price range while posting | enough real ads (shows only with 5+ similar) | after ~1,000 real ads |
| `diaspora` Help for buyers abroad | safety note + "≈ €5,980" | exchange rates in Settings → General | 2nd wave |
| `shops` Business shop pages | /shop/name pages | a monthly price (LATER.md) | when businesses ask |
| `boosts` Paid boosts | Top / VIP / Diamond with mobile money | real prices + merchant numbers, then test mode OFF | when you want income |
| `banner_ads` Banner ads | paid banners in lists / ad pages | an advertiser | when you sell one |
| `social.autopost` Auto-post | new ads on your Telegram / Facebook | keys in .env (docs/social-autopost.md) | any time |
| `api.write` Write API | "App access" keys for an app | a mobile app that uses it | when the app is ready |
| `app` Mobile app | nothing yet (placeholder) | — | — |

**Recommended first wave (no cost, no risk):** Favourites, Saved search alerts, Ad stats.
