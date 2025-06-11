#!/bin/bash

echo "Starting the docker application!"

# add our local user to apache run group so we can live edit files
if [[ "$APP_ENV" == "local" ]]; then
  TARGET_USER_NAME=$(ls -l ./config/base_config.php | awk '{ print $3 }')
  usermod -aG $APACHE_RUN_GROUP $TARGET_USER_NAME
  mkdir -p ./config && mkdir -p ./files && \
    mkdir -p ./empty && chmod 600 ./empty
else
  # set up directory structure in the container
  mkdir -p ./config && mkdir -p ./files && \
    chown -R $APACHE_RUN_GROUP:$APACHE_RUN_USER ./ && \
    mkdir -p ./empty && chmod 600 ./empty
fi

if [[ "$JWT_KEY" == "CHANGE_ME" || "$SUPERKEY" == "CHANGE_ME" ]]; then
  echo "[ERROR] You seem to be using the default encryption keys." >&2
  echo "[ERROR] Please update the environment variables (probably in the docker-compose.yml file)."
  exit 1;
fi

# write the super keys from environment variables, where they should be kept.
echo $JWT_KEY > config/jwt_key.ini && chown $APACHE_RUN_USER:$APACHE_RUN_GROUP config/jwt_key.ini && chmod 600 config/jwt_key.ini
echo $SUPERKEY > config/superkey.ini && chown $APACHE_RUN_USER:$APACHE_RUN_GROUP config/superkey.ini && chmod 600 config/superkey.ini

# Start the apache server in foreground (so Docker doesn't exit)
exec /usr/sbin/apache2ctl -D FOREGROUND
