# Somali review: a 1-page guide for the reviewer

Thank you for helping. You'll check the Somali texts of **iibsom**, a free website to buy and sell in Somalia.

## What you get
- **somali-review-2026-11-20.csv**: open it in **Google Sheets** or **Excel**. Somali letters show correctly.
- The **screens** folder (optional): phone pictures of the pages, so you can see where a text is used.

## The columns
| Column | What it is | Do you change it? |
|---|---|---|
| **key** | The text's name in the code | **No, never** |
| **screen** | The picture (or the place) where the text shows | No |
| **english** | The English text | No |
| **somali** | The Somali text on the site now | **Yes: write the better Somali here** |
| **max_length** | The most letters that fit on a phone | No (stay under it) |
| **notes** | Tips for this row | No |

## The rules
1. **Only change the "somali" column.** If a text is already good, leave it as it is: it then counts as checked.
2. **Keep the words that start with ":"** exactly as they are, for example `:name`, `:count`, `:city`. The site puts a name, a number or a place there. You can move them inside the sentence.
3. **Plural texts** look like `{0} No ads|{1} 1 ad|[2,*] :count ads`. Keep every `|`, `{0}`, `{1}` and `[2,*]`; only translate the words between them.
4. **Stay under max_length.** Short and clear is best: most people read on small phones.
5. **No HTML** (nothing between `<` and `>`). In long texts, keep the marks `##` (heading), `-` (list) and `**bold**`.
6. **Names** (categories, cities, districts like Hodan or Gacan Libah): check the spelling people really use.
7. If you are not sure about a row, **leave the cell empty** and write your question in a separate note. An empty cell keeps today's text.
8. Use simple, everyday Somali that people in Mogadishu, Hargeisa and abroad all understand. Be polite and short (like a good shop sign).

## When you are done
- Save it as **CSV (UTF-8)** (Google Sheets: File → Download → CSV; Excel: Save As → "CSV UTF-8").
- Send the file back. Keep the same columns and do not delete rows.

The developer then runs one command that checks every row (the `:words`, the plural parts, the length) before anything changes on the site. If a row has a problem, nothing is changed, and you get the list of rows to fix.

*Mahadsanid!*
