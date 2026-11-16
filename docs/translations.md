# Languages and translations

- Texts live in `lang/{code}/site.php`. English (`en`) is the source.
- **Add Somali texts:** copy keys from `lang/en/site.php` into `lang/so/site.php` and translate. Missing keys show in English.
- **Turn a language ON:** the setting `languages.enabled` (admin Settings page, later). Until then: `php artisan tinker` → `setting` manager → `set('languages.enabled', ['en','so'])`.
- **URLs:** English has no prefix (`/cars`), Somali is `/so/cars`. A language that is OFF answers 404.
- **Switching:** the page swaps in place (no reload) and the choice is remembered in the `lang` cookie.
- **Native review:** `php artisan iibsom:translations:export so` → `storage/app/translations-so.csv` (key, english, translation, status). Status: `missing`, `needs native review`, or `reviewed` (keys listed in `lang/so/_reviewed.php`).
- **New language (e.g. Arabic):** add it to `config/iibsom.php` → `locales` (with `dir` = `rtl`), create `lang/ar/site.php`, turn it on.
