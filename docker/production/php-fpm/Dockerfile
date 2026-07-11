# Прод-образ API php-fpm. Build-context — корень монорепо: docker build -f docker/production/php-fpm/Dockerfile .
FROM php:8.5-cli-alpine AS builder

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY api/composer.json api/composer.lock ./

RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts \
    && composer clear-cache

FROM php:8.5-fpm-alpine

RUN set -eux; \
    apk add --no-cache --virtual .build-deps postgresql-dev; \
    docker-php-ext-install pdo_pgsql opcache; \
    apk del .build-deps; \
    apk add --no-cache libpq fcgi

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/production/php-fpm/conf.d/ "$PHP_INI_DIR/conf.d/"
COPY docker/production/php-fpm/php-fpm.d/ /usr/local/etc/php-fpm.d/

WORKDIR /app

COPY --from=builder /app/vendor ./vendor
COPY api/ ./

RUN mkdir -p var && chown -R www-data:www-data var

USER www-data
