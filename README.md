# iibsom

Classifieds website for Somalia (iibsom.com): buy and sell cars, houses, land, phones, livestock, services and jobs.
Every ad is checked by an admin. English now, Somali ready.

- **Stack:** Laravel 13 + MySQL 8.4 (+ Redis on a VPS), Blade + Tailwind public site, React admin (shadcn-admin) at `/admin`.
- **Hosting:** the same code runs on shared hosting or a VPS (`HOSTING_MODE` in `.env`), Cloudflare in front, photos on Cloudflare R2.

## Run it on your computer (port 8095)

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed            # tables + Somalia places + categories + pages
npm install && npm run build          # website files
cd admin && pnpm install && pnpm build && cd ..   # admin panel
php artisan iib:demo-reset            # optional: 300+ demo ads with real photos
php artisan iibsom:staff you@example.com          # your Super Admin (one-time password)
php artisan serve --port=8095
```

Open <http://localhost:8095> and <http://localhost:8095/admin>. Docker and Windows (Laragon) steps: [docs/local-setup.md](docs/local-setup.md).

Tests: `php artisan test` (MySQL database `iibsom_testing`).

## Guides

| Guide | For |
|-------|-----|
| [local-setup.md](docs/local-setup.md) | Running on your PC (Laragon or Docker) |
| [deploy-shared.md](docs/deploy-shared.md) | Going live on shared hosting (`bash tools/make-release`) |
| [deploy-vps.md](docs/deploy-vps.md) | Going live on a VPS |
| [cloudflare-r2.md](docs/cloudflare-r2.md) | Cloudflare, photos on R2, cache rules |
| [google-login.md](docs/google-login.md) | Google sign-in keys |
| [hosting-modes.md](docs/hosting-modes.md) · [scaling.md](docs/scaling.md) | Shared vs VPS, growing |
| [demo-data.md](docs/demo-data.md) | Demo data and photos |
| [translations.md](docs/translations.md) · [somali-review](docs/somali-review/README.md) | Somali texts and the review sheet |
| [Admin guide (PDF)](resources/help/admin-guide.pdf) | How every admin page works (also inside the admin: **?** buttons). Remake: `tools/make-guide-pdf` |
| [security.md](docs/security.md) · [performance.md](docs/performance.md) | Security check, speed numbers |

## Project memory

| File | What it holds |
|------|---------------|
| [DECISIONS.md](DECISIONS.md) | Every question + owner's answer + date, small choices |
| [ROADMAP.md](ROADMAP.md) | Phase 1 / 2 / 3 + Ideas |
| [LATER.md](LATER.md) | The owner's to-do list before launch |
| [handover.md](handover.md) | Current state, how to run, what's next |
| [TESTING_GUIDE.md](TESTING_GUIDE.md) | Manual tests for every PR |
| [CREDITS.md](CREDITS.md) · [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) | Licences |
