# Деплой стека `admin` (docs/04-devops.md §7–§8)

## Первичная закладка на сервере (однократно, от `deploy`)

```bash
mkdir -p /srv/admin-rebit-core/swarm/secrets
chmod 0700 /srv/admin-rebit-core/swarm /srv/admin-rebit-core/swarm/secrets

# backend.env — из deploy/backend.env.example (без секретов)
vi /srv/admin-rebit-core/swarm/backend.env

# секреты — по одному файлу на секрет, строго 0600 (publish-скрипт падает иначе)
vi /srv/admin-rebit-core/swarm/secrets/admin_db_password
vi /srv/admin-rebit-core/swarm/secrets/admin_smartcaptcha_server_key
chmod 0600 /srv/admin-rebit-core/swarm/secrets/*
# admin_backup_aws_secret_access_key — при вводе бэкапов (отложены решением заказчика 2026-07-12)
```

## Разовый пререквизит кластера: swarm-cronjob

На живом swarm его нет (инвентаризация 2026-07-12), а cron-задачи стека (purge-tokens)
исполняет именно он — развернуть до первого деплоя: см. шапку `deploy/swarm-cronjob-stack.yml`.

## Каждый релиз

1. CI (или вручную) публикует versioned-объекты: `VERSION=<BUILD_NUMBER> ./deploy/swarm-publish-runtime.sh`
   → печатает `BACKEND_ENV_CONFIG_NAME` и `*_SECRET_NAME` (уходят в `.env` релиза).
2. `make deploy` — scp compose в релиз `/srv/admin-rebit-core/site_<BUILD>`, `.env` с versioned-именами,
   `docker pull`, **migrate-gate** (`docker run … php bin/app.php migrate` — до переключения),
   symlink `site → site_<BUILD>`, `docker stack deploy … admin`.

## Bootstrap первого деплоя

Сети `admin_default` ещё нет → migrate-gate пропускается один раз:

```bash
make deploy SKIP_MIGRATE=1   # поднимет стек (api будет 503 до миграций — это ожидаемо)
make api-migrate-prod        # прогонит миграции; healthcheck'и сами выздоровеют
```

## Откат

`make rollback ROLLBACK_BUILD_NUMBER=<N>` — symlink на релиз N (его `.env` хранит
versioned-имена секретов той сборки) + `docker stack deploy` из него.
