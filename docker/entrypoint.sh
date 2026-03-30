#!/bin/sh
set -e
cd /var/www/html

# Fix permissions for storage and bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Sync vendor/ into the named volume if empty (first run or after volume prune).
# The named volume overlays the bind mount for fast Linux-native file access.
if [ ! -f /var/www/html/vendor/autoload.php ]; then
  echo "[entrypoint] vendor volume empty — running composer install..."
  composer install --no-interaction --optimize-autoloader 2>&1
fi

# Create .env from .env.example if missing (e.g. first run)
if [ ! -f .env ]; then
  cp .env.example .env 2>/dev/null || true
fi

# Generate app key if missing
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
  php artisan key:generate --force 2>/dev/null || true
fi

# Run migrations (waits for DB via depends_on + healthcheck)
php artisan migrate --force 2>/dev/null || true

# Clear only application cache (not views — view caching is already disabled in config)
php artisan cache:clear 2>/dev/null || true

# Patch mPDF vendor files to support modern Arabic fonts (Cairo) with MarkGlyphSets tables.
# These patches are idempotent — safe to run on every startup.
php storage/app/patch_mpdf.php 2>/dev/null || true

# Start the Laravel scheduler as a background process (runs every minute).
php artisan schedule:work --no-interaction >> /dev/null 2>&1 &

exec "$@"
