#!/bin/sh
set -e

echo "Starting Laravel scheduler..."

cd /var/www

if [ ! -f ".env" ]; then
  cp .env.example .env
fi

if [ ! -f "vendor/autoload.php" ]; then
  echo "Installing composer dependencies..."
  composer install --no-interaction --prefer-dist
fi

echo "Waiting for Redis..."
until nc -z redis 6379; do
  sleep 1
done

echo "Redis is ready"

echo "Waiting for MySQL..."
until nc -z mysql 3306; do
  sleep 1
done

echo "MySQL is ready"

php artisan config:clear
php artisan cache:clear

exec php artisan schedule:work
