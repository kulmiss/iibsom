# Languages and translations

- Texts live in `lang/{code}/site.php`. English (`en`) is the source.
- **Somali is written** for every screen (`lang/so/*.php`), plus Somali names for categories, fields, places and the 4 pages. A test fails if a new English text has no Somali text.
- **Before turning Somali ON:** a native speaker checks the sheet (below). Mark checked keys in `lang/so/_reviewed.php`.
- **Catalogue names:** `database/seeders/data/somali.php`. Seeders only add a missing Somali name; names edited in the admin are never overwritten.
- **Turn a language ON:** Admin → Settings → General → Languages ON.
- **URLs:** English has no prefix (`/cars`), Somali is `/so/cars`. A language that is OFF answers 404.
- **Switching:** the page swaps in place (no reload) and the choice is remembered in the `lang` cookie.
- **Native review:** `php artisan iibsom:translations:export so` → `storage/app/translations-so.csv` (key, english, translation, status). Status: `missing`, `needs native review`, or `reviewed` (keys listed in `lang/so/_reviewed.php`).
- **New language (e.g. Arabic):** add it to `config/iibsom.php` → `locales` (with `dir` = `rtl`), create `lang/ar/site.php`, turn it on.
