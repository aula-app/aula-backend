#!/bin/bash
# Grant the application user privileges to create and manage tenant databases.
# The default MARIADB_USER only gets access to MARIADB_DATABASE;
# stancl/tenancy needs to CREATE/DROP databases dynamically.
mariadb -u root -p"${MARIADB_ROOT_PASSWORD}" <<-EOSQL
    GRANT CREATE, DROP ON *.* TO '${MARIADB_USER}'@'%' WITH GRANT OPTION;
    FLUSH PRIVILEGES;
EOSQL
