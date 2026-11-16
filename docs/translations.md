# Languages and translations

- Texts live in `lang/{code}/site.php`. English (`en`) is the source.
- **Somali is written** for every screen (`lang/so/*.php`), plus Somali names for categories, fields, places and the 4 pages. A test fails if a new English text has no Somali text.
- **Before turning Somali ON:** a native speaker checks the sheet (below). Mark checked keys in `lang/so/_reviewed.php`.
- **Help articles:** `resources/help/{en,so}/{order}-{slug}.md` (first line = title). A test fails if an English article has no Somali file.
- **Catalogue names:** `database/seeders/data/somali.php`. Seeders only add a missing Somali name; names edited in the admin are never overwritten.
- **Turn a language ON:** Admin → Settings → General → Languages ON.
- **URLs:** English has no prefix (`/cars`), Somali is `/so/cars`. A language that is OFF answers 404.
- **Switching:** the page swaps in place (no reload) and the choice is remembered in the `lang` cookie.
- **Native review:** the ready sheet with phone pictures is in `docs/somali-review/` (see its README). `php artisan iibsom:translations:export so` → `storage/app/translations-so.csv` (key, english, translation, status, where, screenshot). Status: `missing`, `needs native review`, or `reviewed` (keys listed in `lang/so/_reviewed.php`). It also lists the 4 pages and the catalogue names.
- **Languages:** the site is Somali + English only (owner, 2026-11-16).
