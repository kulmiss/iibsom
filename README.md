# Iibsom

Classifieds website for Somalia: buy and sell cars, houses, land, phones, livestock, services and jobs.

- **Stack:** Laravel 13 + MySQL 8.4 + Redis, Blade pages for the public site, React admin (shadcn-admin) later, Docker for local.
- **Hosting target:** small VPS + Cloudflare (+ Cloudflare R2 for photos).

## Quick start

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
npm install && npm run build
```

Open <http://localhost:8095>. Full steps (Windows + WSL): [docs/local-setup.md](docs/local-setup.md).

## Project memory

| File | What it holds |
|------|---------------|
| [DECISIONS.md](DECISIONS.md) | Every question + owner's answer + date |
| [ROADMAP.md](ROADMAP.md) | Phase 1 / 2 / 3 + Ideas |
| [handover.md](handover.md) | Current state, how to run, what's next |
| [TESTING_GUIDE.md](TESTING_GUIDE.md) | Most important manual tests first |
