#!/bin/bash
set -e

# Forward environment variables to cron (cron runs in a clean env)
printenv | grep -E '^(APP_|DB_|ESP_|LOG_|CACHE_|SESSION_|QUEUE_)' > /etc/environment

echo "$(date) Starting cron..."
cron

echo "$(date) Waiting for MySQL at ${DB_HOST}..."
until php -r "new PDO('mysql:host='.getenv('DB_HOST'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    sleep 2
done

echo "$(date) MySQL ready. Running migrations..."
cd /var/www/html
php artisan migrate --force

echo "$(date) Caching config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "$(date) Starting Apache..."
exec apache2-foreground
