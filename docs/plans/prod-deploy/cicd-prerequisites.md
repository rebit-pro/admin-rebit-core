# CI/CD: что собрать для продолжения (пререквизиты)

> Статус: репозиторная часть готова (workflow, compose-production, deploy/, Makefile).
> Всё ниже — внешние доступы/ключи/решения. Порядок внизу.

## A. GitHub → repo `rebit-pro/admin-rebit-core` → Settings → Secrets and variables → Actions

### Secrets

| Секрет | Значение | Откуда взять |
|---|---|---|
| `HOST` | `37.143.8.221` | известен |
| `PORT` | `22` | ✅ подтверждён (SSH работает, host keys сняты 2026-07-12) |
| `DEPLOY_USER` | `deploy` | известен (пользователь существует на сервере) |
| `SSH_PRIVATE_KEY` | приватный ключ | ✅ **сгенерирован** (2026-07-12): `cat ~/.ssh/admin-rebit-core-ci` → в секрет. Публичный ключ (для сервера, блок C1): `~/.ssh/admin-rebit-core-ci.pub` |
| `SSH_KNOWN_HOSTS` | пин host key | ✅ **снят** (2026-07-12): `cat ~/.ssh/admin-rebit-core-known_hosts` → в секрет (ed25519 SHA256:bwtFdiGszmYuG3bLDNhh3uM9WdEFZb2gbIAuP3J9wqU) |
| `REGISTRY` | `ghcr.io/rebit-pro` | константа (§14.3) |
| `GHCR_PULL_USER` | логин под PAT | владелец PAT (лучше отдельный machine-аккаунт org) |
| `GHCR_PULL_TOKEN` | PAT **только `read:packages`** | GitHub → Developer settings → Personal access tokens; никакого write — он оседает на shared-сервере (§14.3) |
| ~~`BACKUP_AWS_ACCESS_KEY_ID`~~ | — | **отложено** (бэкапы — решение заказчика 2026-07-12, §10) |

### Variables

| Переменная | Значение | Откуда взять |
|---|---|---|
| `VITE_API_URL` | `https://api2.rebit-pro.ru` | константа (§2.4) |
| `VITE_SMARTCAPTCHA_CLIENT_KEY` | клиентский ключ капчи | Yandex Cloud, см. блок B1 |
| ~~`BACKUP_S3_BUCKET`~~ | — | **отложено** (бэкапы, §10) |

### Настройки репозитория

- **Environment `production`** (Settings → Environments): создать + required reviewers на первые релизы (§6).
- **Branch protection** на `master`: require PR + прохождение checks-джоб.

## B. Yandex Cloud (console.yandex.cloud)

### B1. SmartCaptcha (§16.3)

Создать капчу (домен `admin.rebit-pro.ru`) →
- **клиентский ключ** → GitHub Variable `VITE_SMARTCAPTCHA_CLIENT_KEY`;
- **серверный ключ** → на сервер: `/srv/admin-rebit-core/swarm/secrets/admin_smartcaptcha_server_key` (0600).

### B2. Object Storage — бэкапы: **ОТЛОЖЕНО** (решение заказчика 2026-07-12, §10)

Не пререквизит. При вводе: бакет + lifecycle (7д/30д) + сервисный аккаунт `storage.uploader` → ключи; вернуть сервис `api-postgres-backup` в compose, сборку образа в CI, `BACKUP_*` в secrets/vars.

## C. Сервер 37.143.8.221 (SSH работает: `ssh rebit-pro`, root — факт 2026-07-12)

1. ~~CI-ключ~~ — ✅ **дописан 2026-07-12** (`restrict … ci@admin-rebit-core`, ключ tarasov не тронут); проверено: вход по ключу, `docker info` (deploy в группе docker), scp под `restrict` — всё работает. Судьбу чужого ключа `tarasov.ae@nikamed-it.ru` решить вместе с tenancy (C4).
2. **Первичная закладка** (от `deploy`, инструкция — `deploy/README.md`):
   `/srv/admin-rebit-core/swarm/{backend.env,secrets/*}` — каталоги 0700, файлы 0600;
   секреты: `admin_db_password`, `admin_smartcaptcha_server_key` (бэкап-ключ — при вводе бэкапов).
3. ~~Инвентаризация~~ — ✅ **выполнена 2026-07-12** (итоги — §16.7 в `04-devops.md`): labels шлюза подтверждены, ёмкость 1 CPU/2 ГБ → replicas 1 в compose, `swarm-cronjob` отсутствует → **развернуть `deploy/swarm-cronjob-stack.yml` до первого деплоя** (разовая команда — в шапке файла).
4. **Tenancy-решение заказчика письменно** (§16.5): общий `deploy`-юзер с соседними проектами или изоляция.

## D. Порядок продолжения

1. Блок A (секреты/vars/environment; значения SSH_* уже готовы — пути в таблице) + C1 (ключ на сервер).
2. Push ветки → PR → **первый прогон checks-джоб** (заодно соберутся php-образы — открытая задача).
3. `ssh-canary` (Actions → workflow_dispatch) — подтвердить канал именно с GitHub-раннера.
4. B1 (капча) + C2 (закладка секретов) + swarm-cronjob-стек (C3).
5. Merge в `master` → полный прогон: build → security-scan → deploy остановится на environment-approve.
6. Bootstrap-деплой (`deploy/README.md`) → smoke → учение `make rollback` (restore-учение — при вводе бэкапов).
