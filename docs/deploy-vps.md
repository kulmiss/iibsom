# Put iibsom live on a VPS (Ubuntu 24.04)

Recommended when traffic grows (see [scaling.md](scaling.md)). About 1–2 hours. Commands run as a sudo user.

## 1. Server
A VPS with 2 CPU / 4 GB RAM is plenty to start (Hetzner, DigitalOcean, Contabo…). Point a DNS record at it via Cloudflare.

```bash
sudo apt update && sudo apt -y upgrade
sudo apt -y install nginx mysql-server redis-server supervisor unzip git ufw \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-intl php8.3-bcmath php8.3-gd php8.3-imagick
sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
```
(PHP 8.3 not in your Ubuntu? Add `ppa:ondrej/php` first.)

## 2. Database
```bash
sudo mysql -e "CREATE DATABASE iibsom CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'iibsom'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL ON iibsom.* TO 'iibsom'@'localhost'; FLUSH PRIVILEGES;"
```

## 3. Code
Either `git clone` (needs Node on the server to build) or upload the zip from `bash tools/make-release` (easiest):
```bash
sudo mkdir -p /var/www/iibsom && sudo chown $USER:www-data /var/www/iibsom
cd /var/www/iibsom && unzip ~/iibsom-release-*.zip
cp .env.example .env && nano .env
```
`.env` differences from shared hosting:
```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://iibsom.com
HOSTING_MODE=vps
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
PHOTOS_DISK=r2
TRUSTED_PROXIES=*
```
```bash
php artisan key:generate --force
php artisan migrate --seed --force
php artisan storage:link && php artisan optimize
php artisan iibsom:staff you@iibsom.com
sudo chown -R $USER:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache
```

## 4. Nginx
`/etc/nginx/sites-available/iibsom`:
```nginx
server {
    listen 80;
    server_name iibsom.com www.iibsom.com;
    root /var/www/iibsom/public;
    index index.php;
    client_max_body_size 16M;
    access_log off;   # privacy: no visitor IPs in logs
    # Smaller pages on slow phones (HTML is always compressed when gzip is on).
    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml text/plain application/manifest+json;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location = /sw.js { add_header Cache-Control "no-cache"; access_log off; }   # installable website: never cache long
    location = /manifest.webmanifest { default_type application/manifest+json; add_header Cache-Control "no-cache"; }
    location ~* \.(css|js|svg|woff2|webp|avif|png|jpg)$ { expires 1y; access_log off; }
    location ~ /\. { deny all; }
}
```
```bash
sudo ln -s /etc/nginx/sites-available/iibsom /etc/nginx/sites-enabled/ && sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```
HTTPS: Cloudflare SSL **Full (strict)** + a free **Cloudflare Origin Certificate** on Nginx (`listen 443 ssl;` with the cert/key), or `sudo apt install certbot python3-certbot-nginx && sudo certbot --nginx`.

## 5. Queue worker + scheduler
`/etc/supervisor/conf.d/iibsom.conf`:
```ini
[program:iibsom-queue]
command=php /var/www/iibsom/artisan queue:work redis --sleep=1 --tries=3 --max-time=3600
user=www-data
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopwaitsecs=60
```
```bash
sudo supervisorctl reread && sudo supervisorctl update
(sudo crontab -u www-data -l 2>/dev/null; echo "* * * * * cd /var/www/iibsom && php artisan schedule:run >> /dev/null 2>&1") | sudo crontab -u www-data -
```

## 6. Check
`php artisan iibsom:health` → all OK. Admin → System health → green.

## Updating
```bash
cd /var/www/iibsom && unzip -o ~/iibsom-release-NEW.zip
php artisan migrate --force && php artisan optimize && sudo supervisorctl restart iibsom-queue:*
```

## Backups (do this on day 1)
```bash
# /etc/cron.daily/iibsom-backup  (chmod +x)
mysqldump --single-transaction iibsom | gzip > /var/backups/iibsom-$(date +\%F).sql.gz
find /var/backups -name 'iibsom-*.sql.gz' -mtime +14 -delete
```
Copy backups off the server too (e.g. to an R2 bucket). Photos are already on R2.
