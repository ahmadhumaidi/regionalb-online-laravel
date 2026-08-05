#!/usr/bin/env bash
set -euo pipefail
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"
find app resources routes config -type d -exec chmod 755 {} +
find app resources routes config -type f -exec chmod 644 {} +
chown -R www-data:www-data storage bootstrap/cache
find storage/framework storage/logs -type d -exec chmod 775 {} +
find storage/framework storage/logs -type f -exec chmod 664 {} +
php artisan optimize:clear
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
find storage/framework -type d -exec chmod 775 {} +
find storage/framework -type f -exec chmod 664 {} +
echo "Laravel staging permissions and caches prepared."
