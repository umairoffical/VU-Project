#!/bin/bash

set -e

echo "🚀 Starting VuProject Laravel API..."

# Wait for database
echo "⏳ Waiting for database..."
while ! nc -z mysql 3306; do
  sleep 1
done
echo "✅ Database is ready!"

# Run migrations
echo "📋 Running database migrations..."
php artisan migrate --force

# Seed database
echo "🌱 Seeding database..."
php artisan db:seed --force

# Clear caches
echo "🧹 Clearing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start supervisor
echo "🔧 Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
