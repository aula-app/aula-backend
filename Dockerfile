FROM ubuntu:24.04

#
#   install php, mysql, and apache:
#

# Environment variables
ENV PROJECT_PATH=/var/www/html 
ENV DEBIAN_FRONTEND=noninteractive 
ENV APACHE_RUN_USER=www-data 
ENV APACHE_RUN_GROUP=www-data 
ENV APACHE_LOG_DIR=/var/log/apache2 
ENV APACHE_LOCK_DIR=/var/lock/apache2 
ENV PHP_INI=/etc/php/8.3/apache2/php.ini 
ENV TERM=xterm

# Update, upgrade and cURL installation
RUN apt update -q && apt upgrade -yqq && apt install -yqq software-properties-common curl locales gnupg

# Yarn package managerc
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb http://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list

# Utilities, Apache, PHP, and supplementary programs which the application requires
RUN apt update -q 
RUN apt install -yqq --force-yes vim
RUN apt install -yqq --force-yes git npm wget yarn zip 
RUN apt install -yqq --force-yes apache2 libapache2-mod-php 
RUN apt install -yqq --force-yes memcached libmemcached-tools php-cli php-memcached
RUN apt install -yqq --force-yes php8.3
RUN apt install -yqq --force-yes php8.3-memcached
RUN apt install -yqq --force-yes php-mysql
RUN apt install -yqq --force-yes php8.3-bcmath 
RUN apt install -yqq --force-yes php8.3-curl 
RUN apt install -yqq --force-yes php8.3-dom php8.3-mbstring php8.3-intl
RUN apt install -yqq --force-yes sendmail
RUN apt install -yqq --force-yes libphp-phpmailer
RUN apt install -yqq --force-yes php-mail
RUN apt install -yqq --force-yes default-mysql-client

# Apache mods
RUN a2enmod rewrite expires headers

# Apache2 conf
RUN echo "ServerName localhost" | tee /etc/apache2/conf-available/fqdn.conf
RUN a2enconf fqdn

# Cleanup
RUN apt purge -yq \
  patch \
  software-properties-common \
  wget && \
  apt autoremove -yqq

# Port to expose
EXPOSE 80 443

COPY ./ $PROJECT_PATH/api
COPY ./docker-entrypoint.sh $PROJECT_PATH

# Workdir
WORKDIR $PROJECT_PATH


# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 
#   aula specific config
#

# Start apache
CMD ["./docker-entrypoint.sh"]