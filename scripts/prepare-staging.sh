#!/usr/bin/env bash
set -euo pipefail
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

# Keep cache files owned by the account that actually serves this checkout.
# This also supports containers where that account is mapped to nobody.
RUNTIME_USER="${APP_RUNTIME_USER:-$(stat -c '%U' storage/framework)}"
RUNTIME_GROUP="${APP_RUNTIME_GROUP:-$(stat -c '%G' storage/framework)}"

if [[ "$RUNTIME_USER" == "root" ]]; then
    RUNTIME_USER="www-data"
    RUNTIME_GROUP="www-data"
fi

artisan() {
    if [[ "$(id -u)" -eq 0 ]]; then
        (umask 0002; runuser -u "$RUNTIME_USER" -- php artisan "$@")
    else
        (umask 0002; php artisan "$@")
    fi
}

normalize_runtime_permissions() {
    if [[ "$(id -u)" -eq 0 ]]; then
        runuser -u "$RUNTIME_USER" -- find storage/framework storage/logs bootstrap/cache -type d ! -perm 775 -exec chmod 775 {} +
        runuser -u "$RUNTIME_USER" -- find storage/framework storage/logs bootstrap/cache -type f ! -perm 664 -exec chmod 664 {} +
    else
        find storage/framework storage/logs bootstrap/cache -type d ! -perm 775 -exec chmod 775 {} +
        find storage/framework storage/logs bootstrap/cache -type f ! -perm 664 -exec chmod 664 {} +
    fi
}

find app resources routes config -type d ! -perm 755 -exec chmod 755 {} +
find app resources routes config -type f ! -perm 644 -exec chmod 644 {} +
chown -R "$RUNTIME_USER:$RUNTIME_GROUP" storage bootstrap/cache
normalize_runtime_permissions
artisan optimize:clear
artisan view:cache
chown -R "$RUNTIME_USER:$RUNTIME_GROUP" storage bootstrap/cache
normalize_runtime_permissions
echo "Laravel staging permissions and caches prepared."
