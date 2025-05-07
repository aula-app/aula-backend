#!/bin/sh

echo "Starting the docker application!"

# write the super keys from environment variables, where they
#  should be kept.
echo $JWT_KEY > api/jwt_key.ini
echo $SUPERKEY > api/superkey.ini

# maye you will need this information at startup
# php -i

# run apache as expected
/usr/sbin/apache2ctl -D FOREGROUND & 

# make sure to show php errors in the docker log
#  while it is running
tail -f /var/log/apache2/error.log

