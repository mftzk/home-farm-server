#!/bin/bash
set -e

# Forward environment variables to cron (cron runs in a clean env)
printenv | grep -E '^(DB_|ESP_)' > /etc/environment

echo "$(date) Starting cron..."
cron

echo "$(date) Waiting for MySQL at ${DB_HOST}..."
until php -r "new PDO('mysql:host='.getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'));" 2>/dev/null; do
    sleep 2
done

echo "$(date) MySQL ready. Running schema migration..."
php -r "
    \$pdo = new PDO('mysql:host='.getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'));
    \$pdo->exec('CREATE DATABASE IF NOT EXISTS '.getenv('DB_NAME').' DEFAULT CHARACTER SET utf8mb4');
    \$pdo->exec('USE '.getenv('DB_NAME'));
    \$pdo->exec('CREATE TABLE IF NOT EXISTS light_readings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        lux FLOAT NOT NULL,
        recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_recorded_at (recorded_at)
    ) ENGINE=InnoDB');
"

echo "$(date) Starting Apache..."
exec apache2-foreground
