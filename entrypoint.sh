#!/bin/sh

# Remove prior storage links that exist
rm -rf public/storage

# Clear the old boostrap/cache
php artisan clear-compiled

echo "🔑 Generating app key..."
php artisan key:generate --force
echo "✅ App key generated."

php artisan migrate --force
php artisan optimize:clear
php artisan storage:link

echo "🔑 Ensuring Passport keys are present and with correct permissions..."
php artisan passport:keys -q
chown www-data:www-data ./storage/oauth-*.key
chmod 660 ./storage/oauth-public.key
chmod 600 ./storage/oauth-private.key
echo "✅ Passport keys"

# @TODO: nikola - this is too permissive, as we want legacy queue to be able to write there
#   after we get rid of legacy interoperability, we should go back to 755 or whatever
chmod -R 777 ./storage/logs/laravel.log

exec /usr/bin/supervisord -c /etc/supervisord.conf
