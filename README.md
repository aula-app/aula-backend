# Aula API

## Running with Docker Compose

```bash
# Copy examples (the defaults should be enough for a local development test, but not for production)
cp ./docker-compose.override.yml.example ./docker-compose.override.yml
cp ./config/base_config.php-example ./config/base_config.php
cp ./config/db_config.ini-example ./config/db_config.ini
# Please select another secure encryption key
sed -i s/CHANGE_ME/CHANGE_ME/g docker-compose.yml
# Build image and run the service(s)
docker compose build && docker compose up -d
```

## How to install to webserver manually

1. Copy this repo's content to a path that will be accessible through your Apache2 server.

2. Create a database and user for aula:

```mysql
mysql -u admin_user -p

CREATE DATABASE aula_database;
CREATE USER aula_database;

GRANT ALL PRIVILEGES ON aula_database.\* TO aula_user@'localhost' identified by "PASSWORD";
```

3. Create the initial structure for aula with:

```bash
mysql -u admin_user -p aula_database < ./init/aula_db_structure.sql
```

4. Rename `./config/db_config.ini-example` to `./config/db_config.ini`.

5. Edit the `db_config.ini` file, the first 4 lines 'host', 'user', 'pass' and 'dbname' with the db user and dbname created in step 2.

6. Rename `./config/base_config.ini-example` to `base_config.ini`.

7. Edit your `base_config.ini` file with:

   a. $baseHelperDir, $baseClassDir, $baseClassModelDir, $baseDir, $baseConfigDir: pointing to where your aula-backend folder is located, and related paths.

   b. $cryptFile and $jwtKeyFile: must be random strings.

   c. $filesDir: where the uploaded files as avatar pictures will be located. Must be a place where the user running apache2 (usually www-data) has write permission.

   d. All variables starting with $email\_ must be set to the smtp email server (you can use an external service like mailgun).

   e. Everything related to Auth0 configuration must be created on Auth0 panel and filled on $AUTH0\_ variables

8. Put a build of https://github.com/aula-app/aula-frontend in a public path for your Apache server.

9. Configure Apache:

a. IMPORTANT: Add the configuration parameter:

    <FilesMatch "\.(ini)$">
      deny from all
    </FilesMatch>

    to your apache otherwise your files with sensitive information will be public.

b. Configure the folder where the frontend from step 9 is located:

    <Directory "/var/www/">
        Options Includes FollowSymLinks
        AddOutputFilter Includes html
        AllowOverride All
        Order allow,deny
        Allow from all
        RewriteEngine On

        RewriteBase /
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-l
        RewriteRule ^(.*)$ /index.html?path=$1 [L,QSA]
    </Directory>

c. Add the following Header configuration that will be sent by aula-backend:

       Header set Access-Control-Allow-Origin "https://www.YOUR_WEBSITE.com"
       Header set Access-Control-Allow-Credentials "true"
       Header set Access-Control-Allow-Headers "DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Referer,Authorization,User-Agent"
       Header set Access-Control-Expose-Headers "Authorization,Content-Length,Content-Range,Content-Type,Referer,User-Agent"
       Header set Access-Control-Allow-Methods "GET,HEAD,OPTIONS,POST,PUT,PATCH,DELETE"

d. Install and configure PHP and Memcached.

e. Disable PHP errors reporting, in particular the Warnings setting `display_errors = Off` in your php.ini file.

f. Login to your aula system with user: admin, password: change-this-password

e. If you are going to use OAuth install inside your aula-backend folder auth0-php with:

```bash
composer require auth0/auth0-php
composer require guzzlehttp/guzzle guzzlehttp/psr7 http-interop/http-factory-guzzle
```

The last packages are necessary for auth0-php to work.
