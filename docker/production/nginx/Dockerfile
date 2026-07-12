# Прод-образ API nginx: статика public/ вкопирована, PHP — по fastcgi в api-php-fpm. Build-context — корень монорепо.
FROM nginx:1.29-alpine

ENV PHP_FPM_HOST=api-php-fpm \
    CORS_ALLOWED_ORIGIN=https://admin.rebit-pro.ru

RUN rm /etc/nginx/conf.d/default.conf

COPY docker/production/nginx/templates/ /etc/nginx/templates/
COPY api/public /app/public
