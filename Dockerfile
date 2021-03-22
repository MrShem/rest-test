# the different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/compose/compose-file/#target

ARG PHP_VERSION=7.4
ARG NGINX_VERSION=1.16

FROM php:${PHP_VERSION}-fpm AS symfony_php

ARG APCU_VERSION=5.1.19
ARG XDEBUG_VERSION=2.9.8
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PATH="${PATH}:/root/.composer/vendor/bin"

WORKDIR /srv

COPY --from=composer:1.10 /usr/bin/composer /usr/bin/composer
COPY .docker/php/php.ini /usr/local/etc/php/php.ini
COPY .docker/php/php-cli.ini /usr/local/etc/php/php-cli.ini
COPY .docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY .docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

RUN apt-get -qq update > /dev/null && apt-get -yqq upgrade > /dev/null && apt-get -yqq autoremove > /dev/null \
    && apt-get -yqq install acl libfreetype6-dev libicu-dev libjpeg62-turbo-dev \
    libpng-dev libtool libwebp-dev zlib1g-dev libzip-dev > /dev/null; \
    docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype --with-libdir=/usr/include > /dev/null; \
    docker-php-ext-configure zip --with-zip > /dev/null;  \
    docker-php-ext-install -j$(nproc) exif gd intl pdo_mysql zip > /dev/null \
    && pecl install apcu-${APCU_VERSION} xdebug-${XDEBUG_VERSION}> /dev/null; \
    pecl clear-cache > /dev/null; docker-php-ext-enable apcu opcache xdebug> /dev/null \
    && chmod +x /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

FROM nginx:${NGINX_VERSION}-alpine AS symfony_nginx

COPY .docker/nginx/conf.d/default.conf /etc/nginx/conf.d/
WORKDIR /srv
