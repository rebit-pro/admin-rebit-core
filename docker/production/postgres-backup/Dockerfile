# Образ бэкапа Postgres → S3 (pg_dump | gzip → aws s3 cp). Запускается swarm-cronjob'ом ежечасно.
# Build-context — корень монорепо. Перенос из slim-эталона (api/docker/common/postgres-backup).
FROM alpine:3.23

RUN apk add --no-cache postgresql17-client aws-cli

RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app

COPY docker/production/php-cli/wait-for /usr/local/bin/wait-for
COPY docker/production/postgres-backup/docker-entrypoint-env.sh /usr/local/bin/
COPY docker/production/postgres-backup/backup.sh /usr/local/bin/backup
RUN chmod 555 /usr/local/bin/wait-for /usr/local/bin/backup \
    && chmod 555 /usr/local/bin/docker-entrypoint-env.sh

ENTRYPOINT ["docker-entrypoint-env.sh"]
CMD ["backup"]

WORKDIR /app

RUN chown app:app /app

USER app
