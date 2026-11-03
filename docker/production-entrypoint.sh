#!/bin/sh
set -eu
cd /var/www/html
umask 027
if [ -f /etc/secrets/.env ]; then cp /etc/secrets/.env .env; chmod 600 .env; fi
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
php artisan config:clear --no-interaction
php artisan release:check --boot --no-interaction
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then php artisan migrate --force --no-interaction; fi
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
chown -R www-data:www-data storage bootstrap/cache
if [ -n "${PORT:-}" ]; then
    case "$PORT" in *[!0-9]*|'') echo 'PORT must be numeric' >&2; exit 1;; esac
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/:80>/:$PORT>/" /etc/apache2/sites-available/000-default.conf
fi
exec "$@"
