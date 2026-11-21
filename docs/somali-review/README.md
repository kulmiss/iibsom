# Somali review sheet

For a native Somali speaker. Every Somali text on the site is in **somali-texts.csv**:
screen texts, error messages, the help articles, the 4 pages (About, Safety, Terms,
Privacy) and the names of categories, fields and places.

## How to review

1. Open `somali-texts.csv` in Excel or Google Sheets (Somali letters show correctly).
2. Columns:
   - **key**: the text's name in the code. Do not change it.
   - **english**: the English text.
   - **translation**: the Somali text now on the site. Write the better Somali here.
   - **status**: `needs native review`, `reviewed` or `missing`.
   - **where**: the file that shows the text (or a short note, like "Error messages under form fields").
   - **screenshot**: a phone picture of that page, in the `screens` folder.
3. Words like `:name`, `:count`, `:when` or `:max_photos` are filled in by the site (a name, a number, a time, a link).
   Keep them as they are, in the right place in the sentence.
4. Send the sheet back. The developer imports it (PR 112):
   ```bash
   php artisan iibsom:translations:import reviewed.csv --dry-run   # shows every change; writes nothing
   php artisan iibsom:translations:import reviewed.csv             # writes them
   ```
   Every changed row is checked (`:words`, plural parts, length, no HTML). If any row has a problem, nothing is
   written. Screen texts go into `lang/so/*.php` (comments stay); names into `database/seeders/data/somali.php`
   and the database (only where the admin did not change them); checked keys into `lang/so/_reviewed.php`.
   Help articles and the 4 pages are listed to change by hand (Admin → Pages, `resources/help/so/`).

**For the reviewer:** send them [reviewer-guide.md](reviewer-guide.md) and the review-layout sheet:
`php artisan iibsom:translations:export so --review --path=somali-review.csv` (columns: key, screen, english,
somali, max_length, notes).

Category, place and page texts on a live site can also be changed in the admin
(Categories, Locations, Pages). The sheet shows the starter text.

## Rebuild (developers)

```bash
# 1. Pictures (site running with demo data, Somali ON; IIBSOM_COOKIE = a signed-in cookie "name=value")
python3 tools/somali-screenshots http://localhost:8095 docs/somali-review/screens
# 2. Sheet
php artisan iibsom:translations:export so --screens=docs/somali-review/screens --path=docs/somali-review/somali-texts.csv
```

A test fails when a text changes and the sheet was not rebuilt, and when an English
text is not used anywhere (dead text).
