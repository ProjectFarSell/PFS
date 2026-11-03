#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
  bootstrap/cache \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs

chown -R www-data:www-data storage bootstrap/cache || true

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
  composer clearcache
  composer install --no-interaction --prefer-dist
fi

if [ -z "${APP_KEY:-}" ] && ! grep -q '^APP_KEY=.' .env; then
  php artisan key:generate --force --ansi
fi
php artisan storage:link --force --ansi
php artisan migrate --force --ansi
if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then php artisan db:seed --force --ansi; fi

exec "$@"
