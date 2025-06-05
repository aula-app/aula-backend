FROM ubuntu:24.04

# Environment variables
ENV PROJECT_PATH=/var/www/html \
  DEBIAN_FRONTEND=noninteractive \
  APACHE_RUN_USER=www-data \
  APACHE_RUN_GROUP=www-data \
  APACHE_LOG_DIR=/var/log/apache2 \
  APACHE_LOCK_DIR=/var/lock/apache2 \
  PHP_INI=/etc/php/8.3/apache2/php.ini \
  TERM=xterm

# Utilities, Apache, PHP, and supplementary programs which the application requires
RUN apt update -q && apt install -yqq curl gpg && \
  curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - && \
  echo "deb http://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list && \
  apt update -q && \
  apt autoremove -yqq && apt install -yqq vim \
  git npm yarn gpg wget zip \
  apache2 libapache2-mod-php \
  memcached libmemcached-tools php-cli php-memcached \
  php8.3\
  php8.3-memcached \
  php-mysql \
  php8.3-bcmath \
  php8.3-curl \
  php8.3-dom php8.3-mbstring php8.3-intl \
  sendmail \
  libphp-phpmailer \
  php-mail \
  default-mysql-client

# Apache mods & conf
RUN a2enmod rewrite expires headers && \
  echo "ServerName localhost" | tee /etc/apache2/conf-available/fqdn.conf && \
  a2enconf fqdn

# Cleanup
RUN apt purge -yq \
  patch \
  software-properties-common \
  wget && \
  apt autoremove -yqq && \
  apt clean && \
  rm -rf /var/cache/apt/*

# Port to expose
EXPOSE 80 443

COPY ./ $PROJECT_PATH/api
COPY ./docker-entrypoint.sh $PROJECT_PATH
COPY ./config/base_config.php-example $PROJECT_PATH/config/base_config.php
COPY ./config/db_config.ini-example $PROJECT_PATH/config/db_config.ini

RUN mkdir -p $PROJECT_PATH/files && \
  chown -R $APACHE_RUN_GROUP:$APACHE_RUN_USER $PROJECT_PATH/files

# Workdir
WORKDIR $PROJECT_PATH

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 
#   aula specific config
#

# Start apache
CMD ["./docker-entrypoint.sh"]

