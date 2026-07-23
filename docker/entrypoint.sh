#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ ! -f data/app/database.sqlite ]; then
    mkdir -p data/app
    touch data/app/database.sqlite
fi

if [ -f composer.json ] && [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if [ -f artisan ]; then
    # Nur generieren wenn APP_KEY fehlt/leer – nie --force (sonst Rotation bei jedem Start)
    APP_KEY_VALUE=$(grep -E '^APP_KEY=' .env 2>/dev/null | cut -d '=' -f2- | tr -d '\r' | tr -d '"' || true)
    if [ -z "$APP_KEY_VALUE" ]; then
        php artisan key:generate --ansi || true
    fi
    php artisan migrate --force --ansi || true
fi

exec "$@"
