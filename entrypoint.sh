#!/bin/sh

# Remove prior storage links that exist
rm -rf public/storage

# Clear the old boostrap/cache
php artisan clear-compiled

echo "🔑 Generating app key..."
php artisan key:generate --force
echo "✅ App key generated."

echo "⏳ Waiting for database connection..."
until php artisan db:show > /dev/null 2>&1; do
  sleep 2
done
echo "✅ Database is ready."

php artisan migrate --force
php artisan optimize:clear
php artisan storage:link

echo "🔑 Ensuring Passport keys are present and with correct permissions..."
php artisan passport:keys -q
chown www-data:www-data ./storage/oauth-*.key
chmod 660 ./storage/oauth-public.key
chmod 600 ./storage/oauth-private.key
echo "✅ Passport keys"

exec /usr/bin/supervisord -c /etc/supervisord.conf
