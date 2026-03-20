#!/bin/bash

echo "[*] Preparing the runtime environment for the docker application..."

mkdir -p ./config && \
  mkdir -p ./files && chmod 750 ./files && \
  mkdir -p ./errors && chmod 750 ./errors && \
  echo -n '{"success":false,"error":"API_NOT_FOUND"}' > ./errors/404.json

if [[ "$APP_ENV" == "local" ]]; then
  # add our local user to apache run group so we can live edit files
  TARGET_USER_NAME=$(ls -l ./config/base_config.php | awk '{ print $3 }')
  usermod -aG $APACHE_RUN_GROUP $TARGET_USER_NAME
  chown -R $APACHE_RUN_USER:$APACHE_RUN_GROUP ./errors
  chown -R $APACHE_RUN_USER:$APACHE_RUN_GROUP ./files
else
  # set up directory structure in the container
  chown -R $APACHE_RUN_USER:$APACHE_RUN_GROUP ./
fi

if [[ "$SUPERKEY" == "CHANGE_ME" ]]; then
  echo "[ERROR] You seem to be using the default encryption key." >&2
  echo "[ERROR] Please update the environment variables (probably in the docker-compose.yml file)."
  exit 1;
fi

# instead of default umask 0022, use 0002, so we can have g+w permissions on uploaded files
umask 0002

env >> /etc/environment
service cron start

echo "[✓] Runtime environment prepared. Starting apache2 server."
# Start the apache server in foreground (so Docker doesn't exit)
exec /usr/sbin/apache2ctl -D FOREGROUND
