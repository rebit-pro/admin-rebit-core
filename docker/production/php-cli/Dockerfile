# Прод-образ API php-cli: консоль, migrate-gate, cron-задачи. Build-context — корень монорепо.
FROM php:8.5-cli-alpine AS builder

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY api/composer.json api/composer.lock ./

RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts \
    && composer clear-cache

FROM php:8.5-cli-alpine

RUN set -eux; \
    apk add --no-cache --virtual .build-deps postgresql-dev; \
    docker-php-ext-install pdo_pgsql opcache; \
    apk del .build-deps; \
    apk add --no-cache libpq

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && echo "expose_php = Off" > "$PHP_INI_DIR/conf.d/zz-app.ini"

RUN addgroup -g 1000 app && adduser -u 1000 -G app -D app

COPY docker/production/php-cli/wait-for /usr/local/bin/wait-for
RUN chmod +x /usr/local/bin/wait-for

WORKDIR /app

COPY --from=builder /app/vendor ./vendor
COPY api/ ./

RUN mkdir -p var && chown -R app:app var

USER app
