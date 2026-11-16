# Iibsom PHP image (used locally with Docker Compose; same extensions as the VPS).
FROM php:8.3-fpm-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
        bash git unzip icu-libs libzip libpng libjpeg-turbo libwebp libavif freetype \
        imagemagick imagemagick-heic imagemagick-webp \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev libavif-dev freetype-dev imagemagick-dev linux-headers \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-avif --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip gd opcache pcntl bcmath exif \
    && pecl install redis imagick \
    && docker-php-ext-enable redis imagick \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-iibsom.ini

# Run as the host user so files created in the container are not owned by root.
RUN addgroup -g ${GID} app && adduser -D -u ${UID} -G app app
USER app

WORKDIR /var/www/html
