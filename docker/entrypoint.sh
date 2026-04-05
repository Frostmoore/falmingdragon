#!/bin/bash
set -e

# ── Wait for MariaDB ─────────────────────────────────────────────────────────
echo "[entrypoint] Waiting for database..."
until php -r "new PDO('mysql:host=${DB_HOST:-db};port=${DB_PORT:-3306};dbname=${DB_DATABASE:-flamingdragon}', '${DB_USERNAME:-flamingdragon}', '${DB_PASSWORD:-flamingdragon_secret}');" 2>/dev/null; do
    sleep 2
done
echo "[entrypoint] Database ready."

# ── Generate app key if missing ──────────────────────────────────────────────
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:CHANGE_ME" ]; then
    echo "[entrypoint] Generating APP_KEY..."
    php artisan key:generate --force
fi

# ── Laravel bootstrap ────────────────────────────────────────────────────────
echo "[entrypoint] Clearing config cache..."
php artisan config:clear

echo "[entrypoint] Running migrations..."
php artisan migrate --force

echo "[entrypoint] Running seeders (updateOrCreate — safe to re-run)..."
php artisan db:seed --class=DefaultProvidersSeeder --force
php artisan db:seed --class=DefaultToolsSeeder     --force
php artisan db:seed --class=DefaultCommandsSeeder  --force

echo "[entrypoint] Caching config and routes..."
php artisan config:cache
php artisan route:cache

# ── Storage symlink ──────────────────────────────────────────────────────────
php artisan storage:link --force 2>/dev/null || true

echo "[entrypoint] Starting PHP-FPM..."
exec php-fpm
