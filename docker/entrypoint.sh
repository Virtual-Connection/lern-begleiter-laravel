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
    php artisan key:generate --force --ansi || true
    php artisan migrate --force --ansi || true
fi

exec "$@"
