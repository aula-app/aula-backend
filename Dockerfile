FROM ubuntu:24.04

# Environment variables
ENV PROJECT_PATH=/var/www/html \
  DEBIAN_FRONTEND=noninteractive \
  APACHE_RUN_USER=www-data \
  APACHE_RUN_GROUP=www-data \
  APACHE_LOCK_DIR=/var/lock/apache2 \
  PHP_INI=/etc/php/8.3/apache2/php.ini \
  TERM=xterm
EXPOSE 80 443
WORKDIR $PROJECT_PATH

# Utilities, Apache, PHP, and supplementary programs which the application requires
RUN set -eux; apt update -q && \
  apt install -yqq curl gosu \
    apache2 libapache2-mod-php php8.3 \
    memcached libmemcached-tools php-cli php-memcached php8.3-memcached \
    php-mysql default-mysql-client \
    php8.3-bcmath php8.3-curl php8.3-dom php8.3-mbstring php8.3-intl \
    sendmail libphp-phpmailer php-mail && \
  apt purge -yq patch software-properties-common && \
  apt autoremove -yqq && \
  apt clean && \
  rm -rf /var/cache/apt/* && \
	rm -rf /var/lib/apt/lists/*

# Apache mods & conf
RUN a2enmod rewrite expires headers && \
  echo 'ServerName ${APACHE_SERVER_NAME}' | tee /etc/apache2/conf-available/fqdn.conf && \
  a2enconf fqdn
# This envvar should be injected at runtime, localhost is the fallback value
ENV APACHE_SERVER_NAME="localhost"

COPY ./docker-entrypoint.sh ./
COPY ./src ./api

# Logs should be forwarded to stdout & stderr
RUN rm -f /var/log/apache2/access.log /var/log/apache2/error.log && \
  ln -sf /dev/stdout /var/log/apache2/access.log && \
  ln -sf /dev/stderr /var/log/apache2/error.log && \
  rm -f /etc/logrotate.d/apache2

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Grab encryption keys from envvars and start apache2
USER root
CMD ["./docker-entrypoint.sh"]
