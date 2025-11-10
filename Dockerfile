FROM php:8.4-fpm-alpine
WORKDIR /opt/laravel
EXPOSE 80

# Install additional packages
RUN apk --no-cache add supervisor nginx mariadb-client \
    && docker-php-ext-install pdo pdo_mysql \
    && docker-php-ext-enable opcache

COPY conf.d/supervisor/supervisord.conf /etc/supervisord.conf
COPY conf.d/php-fpm/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY conf.d/php/php.ini /usr/local/etc/php/conf.d/php.ini
COPY conf.d/nginx/default.conf /etc/nginx/nginx.conf
COPY conf.d/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Fetch composer from its docker image, and run it
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer
COPY composer.json ./
COPY composer.lock ./
RUN composer install --no-interaction --no-scripts --optimize-autoloader --no-dev

# Copy everything (respects .dockerignore)
COPY . .
RUN chown -R www-data:www-data ./ \
    && chmod -R 755 ./storage

# Scheduler setup
# @TODO: nikola - https://dev.to/spiechu/how-to-run-laravel-scheduler-in-docker-container-d1p
RUN touch /var/log/cron.log
RUN echo "* * * * * /usr/local/bin/php /opt/laravel/artisan schedule:run >> /var/log/cron.log 2>&1" | crontab -

# Declare image volumes
VOLUME /opt/laravel/storage

# Define a health check
HEALTHCHECK --interval=30s --timeout=15s --start-period=15s --retries=3 CMD curl -f http://localhost/internal/up || exit 1

# Add the entrypoint
ADD entrypoint.sh /root/entrypoint.sh
RUN chmod +x /root/entrypoint.sh
ENTRYPOINT ["/root/entrypoint.sh"]
