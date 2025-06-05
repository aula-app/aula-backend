#!/bin/bash

echo "Starting the docker application!"

if [[ "$JWT_KEY" == "CHANGE_ME" || "$SUPERKEY" == "CHANGE_ME" ]]; then
  echo "[ERROR] You seem to be using the default encryption keys." >&2
  echo "[ERROR] Please update the environment variables (probably in the docker-compose.yml file)."
  exit 1;
fi

# write the super keys from environment variables, where they
#  should be kept.
echo $JWT_KEY > config/jwt_key.ini
echo $SUPERKEY > config/superkey.ini

# maye you will need this information at startup
# php -i

# run apache as expected
/usr/sbin/apache2ctl -D FOREGROUND & 

# make sure to show php errors in the docker log
#  while it is running
tail -f /var/log/apache2/error.log

