FROM php:8.5-fpm-alpine

# pdo_pgsql — целевая СУБД PostgreSQL 17.
# (opcache — в прод-Dockerfile многостадийной сборки, задача DevOps.)
RUN set -eux; \
    apk add --no-cache --virtual .build-deps postgresql-dev; \
    docker-php-ext-install pdo_pgsql; \
    apk del .build-deps; \
    apk add --no-cache libpq

WORKDIR /app
