#!/bin/sh
set -eu

cd /app

# Ensure runtime writable dirs exist (mounted volumes may be empty).
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Warm production caches unless explicitly skipped.
if [ "${APP_ENV:-production}" = "production" ] && [ "${SKIP_OPTIMIZE:-false}" != "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# Optional one-shot migrations (keep false by default; use a deploy job instead).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
