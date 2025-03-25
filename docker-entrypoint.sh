#!/bin/sh

php -i

#systemctl start memcached &

# run apache as expected
/usr/sbin/apache2ctl -D FOREGROUND & 

# make sure to show php errors in the docker log
#  whie it is running
tail -f /var/log/apache2/error.log

