#!/bin/bash

echo "[*] Preparing the runtime environment for the docker application..."

mkdir -p ./config && mkdir -p ./files && \
  mkdir -p ./errors && chmod 755 ./errors && \
  echo -n '{"success":false,"error":"API_NOT_FOUND"}' > ./errors/404.json

if [[ "$APP_ENV" == "local" ]]; then
  # add our local user to apache run group so we can live edit files
  TARGET_USER_NAME=$(ls -l ./config/base_config.php | awk '{ print $3 }')
  usermod -aG $APACHE_RUN_GROUP $TARGET_USER_NAME
  chown -R $APACHE_RUN_GROUP:$APACHE_RUN_USER ./errors
else
  # set up directory structure in the container
  chown -R $APACHE_RUN_GROUP:$APACHE_RUN_USER ./
fi

if [[ "$SUPERKEY" == "CHANGE_ME" ]]; then
  echo "[ERROR] You seem to be using the default encryption key." >&2
  echo "[ERROR] Please update the environment variables (probably in the docker-compose.yml file)."
  exit 1;
fi

echo "[✓] Runtime environment prepared. Starting apache2 server."
# Start the apache server in foreground (so Docker doesn't exit)
exec /usr/sbin/apache2ctl -D FOREGROUND
