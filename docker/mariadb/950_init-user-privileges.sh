#!/bin/bash

# Grant the application_user privileges to create users and pass on privileges (grant option)
# and create and drop tenant databases.
#
# Sadly, MariaDB doesn't support GRANT OPTION per privilege, but per user, so CREATE USER can
# be passed on to children users.
#
# The default $MARIADB_USER only gets access to $MARIADB_DATABASE while
# stancl/tenancy library needs to CREATE/DROP databases and CREATE/DROP users for them dynamically.
#
mariadb -u root -p"${MARIADB_ROOT_PASSWORD}" <<-EOSQL
    GRANT USAGE, ALTER, ALTER ROUTINE, CREATE, CREATE ROUTINE, CREATE TEMPORARY TABLES, CREATE USER, CREATE VIEW, DELETE, DROP, EVENT, EXECUTE, INDEX, INSERT, LOCK TABLES, REFERENCES, SELECT, SHOW VIEW, TRIGGER, UPDATE ON *.* TO '${MARIADB_USER}'@'%' WITH GRANT OPTION;
    FLUSH PRIVILEGES;
EOSQL
