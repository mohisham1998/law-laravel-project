#!/bin/sh
# Clear compiled Blade views on startup so UI changes are picked up immediately (e.g. when using volume mount).
cd /var/www/html && php artisan view:clear 2>/dev/null || true
exec "$@"
