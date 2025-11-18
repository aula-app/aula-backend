#!/bin/sh

# Remove prior storage links that exist
rm -rf public/storage

# Clear the old boostrap/cache
php artisan clear-compiled

echo "Generating app key..."
php artisan key:generate --force
echo "App key generated."

php artisan migrate --force
php artisan optimize:clear
php artisan storage:link

exec /usr/bin/supervisord -c /etc/supervisord.conf
