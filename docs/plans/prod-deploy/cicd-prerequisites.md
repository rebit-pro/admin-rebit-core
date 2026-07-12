# CI/CD: что собрать для продолжения (пререквизиты)

> Статус: репозиторная часть готова (workflow, compose-production, deploy/, Makefile).
> Всё ниже — внешние доступы/ключи/решения. Порядок внизу.

## A. GitHub → repo `rebit-pro/admin-rebit-core` → Settings → Secrets and variables → Actions

### Secrets

| Секрет | Значение | Откуда взять |
|---|---|---|
| `HOST` | `37.143.8.221` | известен |
| `PORT` | `22` | известен (подтвердить при инвентаризации — из среды аудита SSH-баннер на 22 не отвечал) |
| `DEPLOY_USER` | `deploy` | известен (пользователь уже существует на сервере) |
| `SSH_PRIVATE_KEY` | приватный ключ | **сгенерировать новый** CI-ключ: `ssh-keygen -t ed25519 -C "ci@admin-rebit-core" -f admin-ci -N ""` → содержимое `admin-ci` в секрет; файл после этого удалить |
| `SSH_KNOWN_HOSTS` | пин host key | с машины с рабочим SSH: `ssh-keyscan -p 22 37.143.8.221 2>/dev/null` → сверить fingerprint (`ssh-keygen -lf`) с тем, что показывает сервер |
| `REGISTRY` | `ghcr.io/rebit-pro` | константа (§14.3) |
| `GHCR_PULL_USER` | логин под PAT | владелец PAT (лучше отдельный machine-аккаунт org) |
| `GHCR_PULL_TOKEN` | PAT **только `read:packages`** | GitHub → Developer settings → Personal access tokens; никакого write — он оседает на shared-сервере (§14.3) |
| `BACKUP_AWS_ACCESS_KEY_ID` | key id статического ключа | Yandex Cloud, см. блок B2 |

### Variables

| Переменная | Значение | Откуда взять |
|---|---|---|
| `VITE_API_URL` | `https://api2.rebit-pro.ru` | константа (§2.4) |
| `VITE_SMARTCAPTCHA_CLIENT_KEY` | клиентский ключ капчи | Yandex Cloud, см. блок B1 |
| `BACKUP_S3_BUCKET` | имя бакета | Yandex Cloud, см. блок B2 |

### Настройки репозитория

- **Environment `production`** (Settings → Environments): создать + required reviewers на первые релизы (§6).
- **Branch protection** на `master`: require PR + прохождение checks-джоб.

## B. Yandex Cloud (console.yandex.cloud)

### B1. SmartCaptcha (§16.3)

Создать капчу (домен `admin.rebit-pro.ru`) →
- **клиентский ключ** → GitHub Variable `VITE_SMARTCAPTCHA_CLIENT_KEY`;
- **серверный ключ** → на сервер: `/srv/admin-rebit-core/swarm/secrets/admin_smartcaptcha_server_key` (0600).

### B2. Object Storage — бэкапы (§10, §14.5)

1. Бакет (напр. `rebit-admin-backups`), приватный.
2. Lifecycle: ежечасные дампы 7 дней + ежедневные 30 дней.
3. Сервисный аккаунт с ролью **storage.uploader** (PutObject-only) → статический ключ доступа:
   - key id → GitHub Secret `BACKUP_AWS_ACCESS_KEY_ID`;
   - secret → на сервер: `/srv/admin-rebit-core/swarm/secrets/admin_backup_aws_secret_access_key` (0600).
4. Имя бакета → GitHub Variable `BACKUP_S3_BUCKET`.

## C. Сервер 37.143.8.221 (нужен рабочий SSH — из среды разработки баннер не приходит)

1. **CI-ключ** в `/home/deploy/.ssh/authorized_keys` со строкой ограничений:
   `restrict <содержимое admin-ci.pub> ci@admin-rebit-core`
2. **Первичная закладка** (от `deploy`, инструкция — `deploy/README.md`):
   `/srv/admin-rebit-core/swarm/{backend.env,secrets/*}` — каталоги 0700, файлы 0600;
   `backend.env` — из `deploy/backend.env.example`; секреты: `admin_db_password`, `admin_smartcaptcha_server_key`, `admin_backup_aws_secret_access_key`.
3. **Инвентаризация (§4 шаг 0)** — снять и принести факты:
   - `docker node ls` + labels ноды; `docker stack ls`; `docker network ls` (есть ли `traefik-public`);
   - `docker service inspect <traefik-сервис>` → фактические имена **entryPoints / certResolver / middleware** (`letsEncrypt`? `secure-headers`? `redirect-to-https`?) — сверить с labels в `docker-compose-production.yml` и поправить при расхождении;
   - есть ли **swarm-cronjob** (если нет — ставим отдельным стеком до первого деплоя);
   - ёмкость: `nproc`, `free -m`, `df -h` → финализировать replicas/limits/reservations;
   - ревизия `authorized_keys` у `deploy` и `root`; `cat /etc/docker/daemon.json`;
   - причина молчания SSH из WSL-среды (fail2ban? фильтр?) — влияет на канарейку.
4. **Tenancy-решение заказчика письменно** (§16.5): общий `deploy`-юзер с соседними проектами или изоляция.

## D. Порядок продолжения

1. Блок A (секреты/vars/environment) + C1 (ключ на сервер).
2. Push ветки → PR → **первый прогон checks-джоб** (заодно соберутся php-образы — открытая задача).
3. `ssh-canary` (Actions → workflow_dispatch) — если провал: развилка self-hosted runner.
4. C3 инвентаризация → финализация labels/limits в compose-production.
5. B1/B2 (капча, бакет) + C2 (закладка секретов).
6. Merge в `master` → полный прогон: build → security-scan → deploy остановится на environment-approve.
7. Bootstrap-деплой (`deploy/README.md`) → smoke → учения `make rollback` и restore из дампа (§13.10).
