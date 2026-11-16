# Local setup (Windows + WSL 2 + Docker)

The site runs at **http://localhost:8095**. 8080 and 8090 are used by your other projects.

| Service | Address |
|---------|---------|
| Website | http://localhost:8095 |
| Mailpit (see sent emails) | http://localhost:8096 |
| MySQL (for a DB tool like HeidiSQL/TablePlus) | localhost:33095, user `iibsom`, password `secret` |

## 1. One-time install
1. Install **WSL 2** with Ubuntu: open PowerShell as admin, run `wsl --install`, then restart.
2. Install **Docker Desktop**. In Settings → Resources → WSL integration, turn ON your Ubuntu.
3. Open the **Ubuntu** terminal (not PowerShell). Do all the next steps there.
4. Install Node 22: `curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs`

> Keep the code **inside WSL** (for example `~/code/iibsom`), not under `/mnt/c/...`. It is about 10× faster.

## 2. Get the code
```bash
mkdir -p ~/code && cd ~/code
git clone https://github.com/kulmiss/iibsom.git
cd iibsom
```

## 3. Start
```bash
cp .env.example .env
docker compose up -d --build          # first time takes a few minutes
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
npm install
npm run build                          # or: npm run dev (live reload)
```
Open http://localhost:8095. You should see **"Iibsom: Buy and sell anything in Somalia. Coming soon."**

## 4. Run the tests (same as CI)
```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```
Tests use a separate database (`iibsom_testing`), so your local data is safe.

> If you created the MySQL volume **before** this file existed, the test database is missing. Run this once:
> `docker compose exec mysql mysql -uroot -prootsecret -e "CREATE DATABASE IF NOT EXISTS iibsom_testing; GRANT ALL ON iibsom_testing.* TO 'iibsom'@'%';"`

## 5. Every day
| Task | Command |
|------|---------|
| Start | `docker compose up -d` |
| Stop | `docker compose down` |
| Logs | `docker compose logs -f app queue` |
| Artisan | `docker compose exec app php artisan ...` |
| Reset database | `docker compose exec app php artisan migrate:fresh --seed` |

## Problems?
- **Port 8095 is busy:** set `APP_PORT=8097` in `.env`, then run `docker compose up -d`.
- **Permission errors in storage/:** run `docker compose exec app chmod -R ug+rw storage bootstrap/cache`.
- **Slow pages:** check the code is inside WSL, not under `/mnt/c`.
