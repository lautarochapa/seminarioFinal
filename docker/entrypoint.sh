#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/html"
PORT_VALUE="${PORT:-10000}"

sed -i "s/Listen 80/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${PORT_VALUE}>/" /etc/apache2/sites-available/000-default.conf

cd "${APP_DIR}"

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

exec "$@"
