#!/bin/sh
# Контракт docker secrets для не-PHP контейнера: <NAME>_FILE → <NAME>.
set -o errexit

if [ -n "${POSTGRES_PASSWORD_FILE:-}" ] && [ -f "$POSTGRES_PASSWORD_FILE" ]; then
    export POSTGRES_PASSWORD="$(cat "$POSTGRES_PASSWORD_FILE")"
    unset POSTGRES_PASSWORD_FILE
fi

if [ -n "${AWS_SECRET_ACCESS_KEY_FILE:-}" ] && [ -f "$AWS_SECRET_ACCESS_KEY_FILE" ]; then
    export AWS_SECRET_ACCESS_KEY="$(cat "$AWS_SECRET_ACCESS_KEY_FILE")"
    unset AWS_SECRET_ACCESS_KEY_FILE
fi

exec "$@"
