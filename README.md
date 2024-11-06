# Aula API

## How to install

1. Copy aula-api content to a path that will be acessible thourgh your Apache2 server.

2. Create a database for aula:

  >  mysql -u admin_user -p
  > 
  > CREATE DATABASE aula_database 

  where admin_user must be an user with database creation permission.

3. Create an user to access the database:

  > mysql -u admin_user -p
  > 
  > CREATE USER aula_database;
  > 
  > GRANT ALL PRIVILEGES ON aula_database.* TO aula_user@'localhost' identified by "PASSWORD";

4. Create the initial structure for aula with:

  > mysql -u admin_user -p aula_database < aula-api/init/aula_db_structure.sql 

5. Copy on your aula-api folder the file db_config.ini-example to db_config.ini

6. Edit the db_config.ini file, the first 4 lines 'host', 'user', 'pass' and 'dbname' with the db user and dbname created on steps 2 and 3.

7. Copy on your aula-api folder the file base_config.ini-example to base_config.ini.

8. Edit your base_config.ini file with:

   a. $baseHelperDir, $baseClassDir, $baseClassModelDir, $baseDir, $baseConfigDir: pointing to where your aula-api folder is located.
   
   b. $cryptFile and $jwtKeyFile: must be random strings.
   
   c. $filesDir: where the uploaded files as avatar pictures will be located. Must be a place where the user running apache2 has writting permission.
   
   d. All variables starting with $email_ must be set to the smtp email server (you can use an external service as mailgun).
   
   e. Everything related to Auth0 configuration must be created on Auth0 panel and filled on $AUTH0_ variables

10. Put a build of https://github.com/aula-app/aula-frontend in a public path for your Apache server.

11. Configure Apache:

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

   c. Add the following Header configuration that will be sent my aula-api:

       Header set Access-Control-Allow-Origin "*"
       Header set Access-Control-Allow-Credentials "true"
       Header set Access-Control-Allow-Headers "DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range,Referer,Authorization,User-Agent"
       Header set Access-Control-Expose-Headers "Authorization,Content-Length,Content-Range,Content-Type,Referer,User-Agent"
       Header set Access-Control-Allow-Methods "GET,HEAD,OPTIONS,POST,PUT,PATCH,DELETE"

   d. Install and configure PHP and Memcached.

   e. Disable PHP errors reporting, in particular the Warnings setting `display_errors = Off` on your php.ini file.

   f. Login to your aula system with  user: admin, password: change-this-password

   e. If you are going to use OAuth install inside your aula-api folder auth0-php with:

     $ composer require auth0/auth0-php 
     $ composer require guzzlehttp/guzzle guzzlehttp/psr7 http-interop/http-factory-guzzle

     The last package are necessary for auth0-php to work.
