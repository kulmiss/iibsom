# Settings

## General

- **Ad rules:** days before ads end, ads per seller per day, photos per ad.
- **Languages ON:** English is always ON. Turn Somali ON when the translation is checked.
- **Bad words:** ads with these words are refused. Add Somali words too.
- **Drug words:** new and edited ads with these words are refused ("Drugs are not allowed on iibsom"). Whole words only, so "chat" and "Qatar" are fine. Do not add normal Somali words such as "qaad" (take) or "jaad" (kind). After a change, run `php artisan iibsom:drugs:check` to send matching live ads back for checking.
- **Weekly summary email:** last week's numbers every Monday morning. **Send me a test now** checks that email works.
- **Help email for customers:** shown on the Help page and as **Contact us** in the footer.
- **Footer:** tick the links to show (About, Terms, Privacy, Cookie info, Copyright, Contact us, Safety tips, Help centre, Popular categories and cities) and paste the links of iibsom's social pages (Facebook, Instagram, TikTok, YouTube, Telegram, WhatsApp). Empty = hidden. Only https links on the network's own site are accepted. The switch **Big footer** (Switches → Site) turns the whole footer on or off.
- **Run the first-time setup again:** the same questions in 5 short steps.

## Appearance

**Website colour:** the colour of the website's top bar, buttons and links. Pick one colour (or a ready one); the lighter and darker shades and the dark mode are made from it. The preview shows how it looks. A colour that is too light is refused, because white text on it would be hard to read; you get a darker one to use instead. "Back to iibsom blue" undoes it. Visitors see the new colour at once. Switch: **Website colour**. Only the Super Admin (Settings) can change it.

**Admin colour** and light or dark mode: only for this admin.

## Security (2FA)

Shows that 2FA is on for you. Lost your phone? Use one of your recovery codes, or ask the Super Admin to reset your 2FA (Staff page).

## Prices for buyers abroad

Only while the **Help for buyers abroad** switch is ON. Type how much **1 US dollar** is in each money (for example 0.92 for the euro). Buyers whose phone is set to that part of the world see "≈ €5,980" under the price, with the date you last changed the rates. 0 or empty = that money is not shown. Update the rates now and then (once a month is enough).

## Demo data

**Show demo data** shows or hides the demo ads. **Delete all demo data** removes them for good (type DELETE). Do this before going live.
