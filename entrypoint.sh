#!/bin/sh

# Remove prior storage links that exist
rm -rf public/storage

# Clear the old boostrap/cache
php artisan clear-compiled

echo "🔑 Checking app key for encryption (sessions, JWT, app-level encryption)..."
if ! grep -q '^APP_KEY=' .env || grep -q '^APP_KEY=$' .env; then
  echo "🔑 No existing key found. Generating new app key..."
  php artisan key:generate --force
  echo "✅ App key generated."
else
  echo "✅ App key existing, reusing it. Remember to rotate the key occasionally."
fi

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
