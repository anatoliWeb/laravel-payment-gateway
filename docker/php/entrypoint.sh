#!/bin/sh
set -e

cd /var/www

if [ ! -f ".env" ]; then
  cp .env.example .env
fi

sed -i "s|DB_HOST=.*|DB_HOST=${DB_HOST}|g" .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE}|g" .env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME}|g" .env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|g" .env

sed -i "s|REDIS_HOST=.*|REDIS_HOST=${REDIS_HOST}|g" .env
sed -i "s|REDIS_PASSWORD=.*|REDIS_PASSWORD=${REDIS_PASSWORD}|g" .env

# WHY: Laravel, queue worker and Reverb must be able to write runtime logs/cache.
# Bind mounts or artisan commands executed as root can create files that block app writes.
mkdir -p /var/www/storage/logs
touch /var/www/storage/logs/laravel.log
mkdir -p /var/www/bootstrap/cache

if [ "$(id -u)" = "0" ]; then
  chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
fi

chmod -R ug+rwX /var/www/storage /var/www/bootstrap/cache

exec sh /var/www/docker/entrypoint.sh