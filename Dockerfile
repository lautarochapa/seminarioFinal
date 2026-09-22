FROM php:7.4-cli AS vendor

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/debian-bullseye.list /etc/apt/sources.list
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-autoloader \
    --no-scripts \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --ignore-platform-req=ext-gd

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && php artisan package:discover --ansi

FROM php:7.4-apache

ARG APP_DIR=/var/www/html

ENV APACHE_DOCUMENT_ROOT=${APP_DIR}/public
ENV PORT=10000

WORKDIR ${APP_DIR}

COPY docker/debian-bullseye.list /etc/apt/sources.list
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        exif \
        gd \
        mbstring \
        pdo_pgsql \
        pgsql \
        zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/mpm_prefork.conf /etc/apache2/mods-available/mpm_prefork.conf
COPY docker/apache/runtime.conf /etc/apache2/conf-available/runtime.conf
COPY docker/php/runtime.ini /usr/local/etc/php/conf.d/zz-runtime.ini
COPY docker/entrypoint.sh /usr/local/bin/render-entrypoint
COPY docker/memory-log.sh /usr/local/bin/render-memory-log
RUN a2enconf runtime \
    && chmod +x /usr/local/bin/render-entrypoint /usr/local/bin/render-memory-log

# Exercise Apache and PHP in the build image, without an application database.
COPY public/.htaccess /var/www/html/public/.htaccess
COPY docker/tests/apache-smoke.sh /tmp/apache-smoke.sh
RUN bash /tmp/apache-smoke.sh && rm /tmp/apache-smoke.sh

COPY --from=vendor /app ${APP_DIR}

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 10000

ENTRYPOINT ["render-entrypoint"]
CMD ["apache2-foreground"]
