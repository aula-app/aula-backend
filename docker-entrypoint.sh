#!/bin/bash

echo "[*] Preparing the runtime environment for the docker application..."

# add our local user to apache run group so we can live edit files
if [[ "$APP_ENV" == "local" ]]; then
  TARGET_USER_NAME=$(ls -l ./config/base_config.php | awk '{ print $3 }')
  usermod -aG $APACHE_RUN_GROUP $TARGET_USER_NAME
  mkdir -p ./config && mkdir -p ./files && \
    mkdir -p ./empty && chmod 600 ./empty
else
  echo -n '{"success":false,"error":"API_NOT_FOUND"}' > "./error.json"
  # set up directory structure in the container
  mkdir -p ./config && mkdir -p ./files && \
    chown -R $APACHE_RUN_GROUP:$APACHE_RUN_USER ./ && \
    mkdir -p ./empty && chmod 600 ./empty
fi

if [[ "$SUPERKEY" == "CHANGE_ME" ]]; then
  echo "[ERROR] You seem to be using the default encryption key." >&2
  echo "[ERROR] Please update the environment variables (probably in the docker-compose.yml file)."
  exit 1;
fi


echo "[✓] Runtime environment prepared. Starting apache2 server."
# Start the apache server in foreground (so Docker doesn't exit)
exec /usr/sbin/apache2ctl -D FOREGROUND
