# DevOps-архитектура — ReBit Admin Core

> Усиленная DevOps-архитектура: контейнеризация, три окружения, CI/CD, Docker Swarm-кластер, Traefik-шлюз с автоматическим TLS, секреты, cron, бэкапы, наблюдаемость и безопасность.
> **Эталон** — DevOps-связка Д. Елисеева: приложение (`/home/user/slim`) + кластер (`/home/user/cluster`, Ansible→Swarm) + шлюз (`/home/user/traefik`).
> **Отличие проекта:** фронтенд — **Vue 3 + Vuetify + Berry** (админ-шаблон), а не React. Бэкенд — Slim 4 / PHP 8.5 / PostgreSQL 17 (PDO, самописный `Migrator`).
> **CI/CD — GitHub Actions** (пайплайн по образцу Rabbit P2P `.github/workflows/makefile.yml`), **не** Jenkins. **Структура — монорепо Елисеева** (`/home/user/slim`): корень (`Makefile`, `compose*.yml`, `.github/`) + `api/` (весь бэкенд) + `frontend/` (см. §1a).
> **Конкретика:** репозиторий `git@github.com:rebit-pro/admin-rebit-core.git`; админка (фронт) — `admin.rebit-pro.ru`; API — `api2.rebit-pro.ru`; реестр образов — GitHub Container Registry (`ghcr.io/rebit-pro`).
> Опирается на [`01-scenarios.md`](01-scenarios.md) §5–6 (cron, безопасность) и [`03-database.md`](03-database.md) §10 (миграции). Уровень **L** по [[rebit-backend]] (раздел «Деплой»).

## 0. Принципы

1. **Три окружения — три compose-файла:** локально собираем образы, в CI тянем их из registry, в проде раскатываем как Swarm-стек. Один и тот же образ проходит весь путь (build once, run anywhere).
2. **Multi-stage образы:** dev (Xdebug + код через volume) ≠ prod (`composer --no-dev -o`, opcache, код вкопирован, non-root).
3. **Инфраструктура как код:** серверы — Ansible; кластер — Docker Swarm; шлюз — Traefik-стек; пайплайн — **GitHub Actions** (`.github/workflows/makefile.yml`, поверх целей Makefile); команды — Makefile.
4. **Секреты — вне образов и репозитория:** versioned docker secrets/configs (`*_FILE`), **GitHub Secrets** (чувствительное) и **GitHub Variables** (публичные `VITE_*`).
5. **Всё запинено:** явные версии образов (`postgres:17-alpine`, `php:8.5-fpm-alpine`, `nginx:1.29-alpine`, `node:24-alpine`, `traefik:3.x`), теги приложения по номеру сборки.
6. **Усиление (сверх эталона):** firewall/ufw, харднинг SSH, fail2ban, сканирование образов, resource limits, защита дашборда Traefik, TLS-опции, агрегация логов — то, чего в референсе нет, вводим осознанно (см. §11).

---

## 1. Топология

Классическая триада Елисеева, три отдельных репозитория/стека:

```
                    ┌───────────────────────────────────────────────┐
   Разработчик ──►  │  push → GitHub → GitHub Actions (CI/CD)        │
                    └───────────────┬───────────────────────────────┘
                                    │ docker build+push → Registry
                                    │ docker stack deploy (ssh://deploy@manager)
                                    ▼
   Internet ─► :80/:443 ─►  ┌──────────────── Docker Swarm ──────────────────┐
                            │  ┌─────────┐  сеть overlay: traefik-public       │
                            │  │ Traefik │◄─ шлюз (ACME/Let's Encrypt, TLS)    │
                            │  └────┬────┘  (стек `traefik`, на manager-ноде)  │
                            │       │ по deploy.labels сервисов                │
                            │  ┌────▼─────────── стек `rebit-admin` ─────────┐ │
                            │  │ frontend (nginx+Berry SPA) ×2                │ │
                            │  │ api (nginx) ×2 ─► api-php-fpm ×2             │ │
                            │  │ api-migration (one-shot при деплое)         │ │
                            │  │ cron (swarm-cronjob) ─► задачи replicas:0   │ │
                            │  │ api-postgres  (node.labels.db==db)          │ │
                            │  │ api-postgres-backup (pg_dump→S3, ежечасно)  │ │
                            │  └─────────────────────────────────────────────┘ │
                            └──────────────────────────────────────────────────┘
   Провижининг: Ansible (repo `cluster`) ─► swap · docker · swarm-init · node-labels · deploy-user
```

**Три репозитория:**
| Репо | Роль | Технология |
|---|---|---|
| `admin-rebit-core` (этот, монорепо) | приложение: `api/` + `frontend/` | Docker, compose ×3, Makefile, `.github/workflows` |
| `cluster` (эталон `/home/user/cluster`) | провижининг серверов и Swarm | Ansible |
| `traefik` (эталон `/home/user/traefik`) | шлюз/reverse-proxy для всего кластера | Docker Swarm stack |

---

## 1a. Структура репозитория (монорепо, как у Елисеева)

Бэкенд **перенесён из корня в `api/`** (раскладка из `/home/user/slim`; у P2P — та же схема). **По требованию заказчика папка `docker/` и прочие технические/инфраструктурные файлы (`.dockerignore`) — в корне монорепо, НЕ в `api/`:** `api/` остаётся чистым приложением.

```
admin-rebit-core/                  # монорепо (git: rebit-pro/admin-rebit-core)
├── Makefile                       # единая точка команд
├── docker-compose.yml             # dev
├── docker-compose-production.yml  # prod (Swarm stack)
├── .dockerignore
├── docker/                        # ← инфраструктура (Dockerfile'ы, nginx-конфиг) — ВНЕ api/
│   ├── php/Dockerfile  nginx/default.conf               # dev (сейчас)
│   └── {development,production,testing}/{nginx,php-fpm,php-cli}   # целевое
├── .github/workflows/makefile.yml # CI/CD (GitHub Actions, образец — P2P)
├── deploy/                        # swarm-publish-runtime.sh, secrets/*.example, logrotate
├── api/                           # ← ТОЛЬКО приложение
│   ├── bin/ config/ public/ src/ tests/ migrations/
│   ├── composer.json composer.lock phpunit.xml
│   └── .env .env.example var/ vendor/
├── frontend/                      # Berry SPA
└── docs/
```

**В `api/` — только приложение:** `bin/ config/ public/ src/ tests/ migrations/` + `composer.json`/`phpunit.xml`/`.env` (обязаны лежать рядом с кодом — composer/phpunit исполняются в `api/`). **`docker/` и `.dockerignore` — в корне.**

**Build-context:**
- **dev** (сделано): `context: ./docker, dockerfile: php/Dockerfile` — образ без COPY, код через volume `./api:/app`; контекст крошечный.
- **prod** (далее): `context: ., dockerfile: docker/production/<svc>/Dockerfile`, внутри `COPY api/ ./`; корневой `.dockerignore` исключает `api/vendor`, `node_modules`, `frontend/dist`.

> **Статус:** реструктуризация **выполнена и проверена** — `make api-migrate/api-fixtures/api-test`, `/health`, вход через фронт-прокси — всё зелёное. Остаток #13: prod-Dockerfile'ы, `docker-compose-production.yml`, GitHub Actions, `deploy/`, uuid-перекладка.

---

## 2. Контейнеры и Dockerfile'ы

Текущее состояние (факт): `docker/php/Dockerfile` тривиален (`FROM php:8.5-fpm-alpine; WORKDIR /app`) — **нет `pdo_pgsql`, opcache, стадий сборки**. Именно поэтому проект пока живёт на SQLite. Ниже — целевая раскладка `docker/{common,development,production,testing}/…`, как у эталона.

### 2.1. API php-fpm
- **Dev** (`docker/development/php-fpm/Dockerfile`): `php:8.5-fpm-alpine` + `pdo_pgsql`, **Xdebug** (из исходников), `php.ini-development`, проброс `UID/GID` (ARG) для `www-data`, `HEALTHCHECK` через `cgi-fcgi` на `/ping`. Код — через volume.
- **Prod** (`docker/production/php-fpm/Dockerfile`, 2 стадии):
  - `builder` (`php:8.5-cli`): `COPY composer.json composer.lock` → `composer install --no-dev --prefer-dist --optimize-autoloader`; чистка кэша.
  - runtime (`php:8.5-fpm`): `pdo_pgsql` + **opcache** (`opcache.enable=1`, `validate_timestamps=0`), `php.ini-production`, `COPY --from=builder /app/vendor ./vendor`, `COPY ./ ./`, `chown www-data var -R`; **без Xdebug**, `expose_php=Off`.

> Введение `pdo_pgsql` здесь **закрывает открытый вопрос** из [`03-database.md`](03-database.md): после этого разработка и прод идут на Postgres, SQLite остаётся только для быстрых юнит-тестов, и двойная ветка миграций больше не нужна.

### 2.2. API php-cli (`docker/{development,production,testing}/php-cli/Dockerfile`)
Один образ для **консоли, миграций и cron**. Prod — 2 стадии, `composer install --no-dev -o`, `USER app` (non-root), `wait-for-it` в комплекте. Testing-вариант — с dev-зависимостями (для фикстур против прод-подобных образов). Используется тремя сервисами: `api-php-cli` (ручные команды), `api-migration` (one-shot), cron-задачи.

### 2.3. API nginx (`docker/{common,development,production}/nginx`)
- Общий `conf.d/default.conf` (развитие текущего `docker/nginx/default.conf`): `root /app/public`, `try_files $uri /index.php$is_args$args`, fastcgi на `api-php-fpm:9000`, `/health` → факт `GET /health` приложения, `/health/nginx` → `200` (факт), CORS-заголовки по **белому списку** (не `*`), preflight `OPTIONS → 204`.
- **Prod** дополнительно `COPY ./public ./public` (index.php внутрь образа), security-заголовки.

### 2.4. Frontend — Vue + Vuetify + Berry
Факт сборки (`frontend/package.json`): `build = vue-tsc --noEmit && vite build`; стек Vite 7 / Vuetify 3.10 / Pinia / vue-router / vue-tabler-icons.
- **Dev** (`frontend/docker/development/node`): `node:24-alpine`, `npm run dev -- --host` (Vite HMR), проброс `UID/GID`. Отдельный nginx-роутер `frontend` проксирует `/` на `frontend-node:5173` и `/api` на `api` (сейчас dev-фронт поднимается напрямую сервисом `frontend-node`, порт `5174:5173` — факт).
- **Prod** (`frontend/docker/production/nginx`, 2 стадии):
  - `builder` (`node:24`): `npm ci && npm run build` → артефакт `dist/`.
  - runtime (`nginx:1.29`): `COPY --from=builder /app/dist ./public`; **SPA-fallback** `try_files $uri $uri/ /index.html`; кэш статики (`js/css expires 1y`); `X-Frame-Options SAMEORIGIN`, security-заголовки.
  - **Окружение-независимый образ:** значения `VITE_API_URL`, `VITE_GEETEST_CAPTCHA_ID` собираются как плейсхолдеры и **подставляются на старте контейнера** (entrypoint `sed` по `*.js`) — один образ Berry работает в любом окружении.
  - В проде `VITE_API_MOCKS_ENABLED=false` (в отличие от факта dev, где `=true` — моки в localStorage).

### 2.5. Данные и вспомогательные
- **`api-postgres`** — `postgres:17-alpine` (факт), volume `api-postgres`, healthcheck `pg_isready` (факт).
- **`api-postgres-backup`** — `alpine` + `postgresql-client` + `aws-cli`: `pg_dump | gzip -9` → `aws s3 cp`. Локально — `adobe/s3mock`, в проде — реальный S3.
- **`mailer`** — `axllent/mailpit` (dev-SMTP + UI) для писем подтверждения (смена email/сброс пароля, [`01-scenarios.md`](01-scenarios.md) §1.6).
- **(опц.) `redis`** — для rate-limit/локов/кэша, если решим вынести из Postgres (см. открытый вопрос в [`03-database.md`](03-database.md)).

---

## 3. Три окружения (Docker Compose)

### 3.1. Локальная разработка — `docker-compose.yml`
Факт: уже есть `nginx / app(php-fpm) / frontend-node / db(postgres17)`; **нет** traefik, mailer, backup, php-cli, secrets. Целевой набор: `traefik(dev) · frontend · frontend-node · api(nginx) · api-php-fpm · api-php-cli · api-postgres · api-postgres-backup · mailer`. Образы **собираются** (`build:`), код — через volume, `APP_ENV=local`, `APP_DEBUG=1`, Xdebug. Секреты — файлы `docker/development/secrets/*` (dev-значения в репозитории: пароль БД, ключи и т.п. — как в эталоне).

### 3.2. Тесты / CI — `docker-compose-testing.yml`
Все сервисы — **готовые образы из registry** (`image: ${REGISTRY}/rebit-admin-*:${IMAGE_TAG}`), `APP_ENV=prod`, `APP_DEBUG=0`. Отдельный `testing-api-php-cli` (с dev-зависимостями) — для миграций и фикстур. Traefik без публикации портов. Прогон smoke + e2e (Playwright/Cucumber — факт: фронт уже содержит `test:e2e` на Cucumber+Playwright).

### 3.3. Прод — `docker-compose-production.yml` (Swarm stack)
`version: "3.9"`. Ключевые отличия от локалки:
- образы **из registry**; **нет** сервиса traefik (шлюз развёрнут отдельным стеком), сеть `traefik-public` — `external: true`;
- **реплики + rolling update** у `frontend/api/api-php-fpm`:
  ```yaml
  deploy:
    mode: replicated
    replicas: 2
    update_config: { parallelism: 1, delay: 10s }
    restart_policy: { condition: on-failure }
    resources: { limits: { cpus: "1.0", memory: 512M } }   # усиление: лимиты ресурсов
  ```
- **маршрутизация в `deploy.labels`** (TLS + Let's Encrypt + secure-headers):
  ```yaml
  # frontend (админка) → admin.rebit-pro.ru
  deploy:
    labels:
      - traefik.enable=true
      - traefik.docker.network=traefik-public
      - traefik.http.routers.admin-frontend.rule=Host(`admin.rebit-pro.ru`)
      - traefik.http.routers.admin-frontend.entryPoints=https
      - traefik.http.routers.admin-frontend.tls=true
      - traefik.http.routers.admin-frontend.tls.certResolver=letsEncrypt
      - traefik.http.routers.admin-frontend.middlewares=secure-headers
      - traefik.http.services.admin-frontend.loadBalancer.server.port=80
  # api (nginx) → api2.rebit-pro.ru: аналогичные labels с Host(`api2.rebit-pro.ru`);
  # домены раздельные → CORS-whitelist на https://admin.rebit-pro.ru (см. §14.2, §15).
  ```
- **`api-migration`** — one-shot при каждом деплое:
  ```yaml
  api-migration:
    image: ${REGISTRY}/rebit-admin-api-php-cli:${IMAGE_TAG}
    command: ["sh","-c","wait-for-it api-postgres:5432 -t 60 && exec php bin/app.php migrate"]
    deploy:
      restart_policy: { condition: on-failure, delay: 5s, max_attempts: 5, window: 120s }
  ```
  (`migrate` — факт: команда уже есть в `bin/app.php`.)
- **cron** — сервис `crazymax/swarm-cronjob` на manager-ноде (см. §7);
- **Postgres на выделенной ноде**: `placement.constraints: [ node.labels.db == db ]`, `endpoint_mode: dnsrr` — лейбл `db=db` навешивает Ansible-роль `swarm-labels`;
- **секреты/config** — `external: true`, versioned (`admin_backend_env_<BUILD>`, `<secret>_<BUILD>`); имена подставляет CI (GitHub Actions) через `deploy/swarm-publish-runtime.sh` (§8).

**Сводка local → prod:** build→pull · labels→deploy.labels · +replicas/rolling · +TLS/ACME/secure-headers · +api-migration · +swarm-cronjob · +placement БД · secrets `${*_FILE}` · сеть `traefik-public` external.

---

## 4. Кластер — Ansible + Docker Swarm (репо `cluster`)

Из голых серверов разворачивается защищённый Swarm-кластер (эталон `/home/user/cluster`).

**Инвентарь** `hosts.yml` (из `.dist`): группы `manager` и `workers`; на manager — переменная `db_hostname: worker-db` (какая нода несёт БД).

**Плейбуки** (запуск через `Makefile` → `ansible-playbook -i hosts.yml <playbook>`):
| Плейбук | Что делает |
|---|---|
| `site.yml` | manager: роли `swap → docker → swarm-manager`, затем `swarm-labels` (вешает `db=db` на ноду `worker-db`) |
| `upgrade.yml` | `apt upgrade dist` по всем хостам |
| `authorize.yml` / `authorize-deploy.yml` / `add-ssh-key.yml` | раздача SSH-ключей пользователю `deploy` |
| `docker-login.yml` | `docker login` в registry от имени `deploy` |

**Роли:**
- **`swap`** — swap-файл (при RAM ≤1 ГБ размер 2×RAM, иначе 2 ГБ), `vm.swappiness=1`.
- **`docker`** — установка `docker-ce` через GPG-signed apt-репозиторий; `daemon.json` с registry-mirror; **cron `docker system prune -af --filter until=48h`** (ежедневно); python-docker SDK для модулей Ansible.
- **`swarm-manager`** — создаёт пользователя **`deploy`** (группа `docker`, пароль заблокирован `'!'`, вход только по SSH-ключу), `docker swarm init` (advertise `:2377`), сохраняет worker-join-token.
- **`swarm-worker`** — join воркера по токену менеджера (в эталоне вызывается отдельно — при масштабировании подключить в `site.yml`).
- **`swarm-labels`** — навешивает node-labels (`db=db`) под `placement.constraints`.

> **Усиление кластера (добавляем к эталону, см. §11):** роль `firewall` (ufw: разрешить 22/80/443 + Swarm-порты 2377/7946/4789 только между нодами), харднинг `sshd` (без пароля/root-login по паролю), `fail2ban`, автоматические security-обновления.

**Мультименеджер/масштаб:** эталон — single-manager. Для отказоустойчивости — 3 менеджера (raft-кворум) + N воркеров; роль `swarm-worker` включается в оркестрацию.

---

## 5. Шлюз — Traefik (репо `traefik`)

Единый reverse-proxy для всего кластера (эталон `/home/user/traefik`). Приложения подключаются к **внешней overlay-сети `traefik-public`** и объявляют маршруты своими `deploy.labels` (§3.3).

**Прод-стек** (`docker-compose-production.yml`, Swarm):
- провайдер `--providers.docker.swarmMode=true`, `--providers.docker.exposedByDefault=false` (сервис не публикуется без явного `traefik.enable=true`);
- entrypoints `http :80` / `https :443`, порты в режиме `mode: host`;
- **ACME/Let's Encrypt** (`httpChallenge`), хранилище `acme.json` в volume `traefik-public-certs`, e-mail оператора (в эталоне студии — `vidmon83@vk.com`);
- глобальный **redirect http→https** (`redirect-to-https`, permanent);
- middleware **`secure-headers`**: `stsSeconds=31536000` (HSTS), `contentSecurityPolicy`, `sslRedirect` — приложения цепляют его в своих роутерах;
- `placement.constraints: node.role == manager` (нужен доступ к `docker.sock`).

**Dev-вариант** (`docker-compose.traefik.yml`): провайдер `file` (`traefik_dynamic.yml`), self-signed сертификаты из `certs/` (для `*.loc` доменов), дашборд на `:8080`.

**Деплой шлюза** (`Makefile` эталона): создаётся сеть `traefik-public` (`docker network create --driver=overlay --attachable`), релиз через **symlink-стратегию** (`traefik_<BUILD>/` ← `ln -s`), `docker stack deploy --with-registry-auth --prune traefik`; оркестрируется GitHub Actions по ветке `main`.

> **Усиление шлюза (добавляем):** `tls.options` (minVersion `VersionTLS12`, безопасные cipherSuites); **basic-auth на дашборд** (в эталоне дашборд в проде просто не публикуется — если включаем, закрываем middleware `dashboard-auth`); rate-limit middleware на уровне шлюза как первый рубеж перед app-level лимитами ([`01-scenarios.md`](01-scenarios.md) §6.1).

---

## 6. CI/CD — GitHub Actions (`.github/workflows/makefile.yml`)

Один workflow, по образцу Rabbit P2P (`/home/user/rebit-p2p/.github/workflows/makefile.yml`). **Триггеры:** `push`/`pull_request` в `main`. На PR — только проверки; **build/push/deploy — только `push` в `main`** (`if: github.event_name == 'push' && github.ref == 'refs/heads/main'`). Джобы опираются на цели `Makefile`.

**Jobs (граф зависимостей):**
1. **`api-checks`** — `actions/checkout@v5` → `shivammathur/setup-php@v2` (PHP **8.5**, ext `intl,mbstring,curl,json,openssl,pdo_pgsql`, `composer:v2`) → `actions/cache@v4` (`api/vendor` по `api/composer.lock`) → в `working-directory: api`: `composer install` → `lint` → `cs-check` (php-cs-fixer) → `psalm` → `test` (PHPUnit).
2. **`frontend-checks`** — `setup-node@v4` (node **24**, cache по `frontend/package-lock.json`) → `npm ci` → `lint` → `typecheck` → `build` (env `VITE_API_URL=${{ vars.VITE_API_URL }}`=`https://api2.rebit-pro.ru`, `VITE_APP_VERSION=${{ github.run_number }}`, `VITE_API_MOCKS_ENABLED=false`).
3. **`build-api`** (`needs: api-checks`, `environment: production`, только main) — `docker/setup-buildx` → `docker/login-action@v3` (ghcr: `REGISTRY_HOST`/`REGISTRY_USER`/`TOKEN_GIT_HUB`) → `docker/build-push-action@v6` три образа (`admin-rebit-core-api` nginx, `-api-php-fpm`, `-api-php-cli`), `context: api`, тег `${{ github.sha }}`, cache `type=gha`.
4. **`build-frontend`** (`needs: frontend-checks`, только main) — образ `admin-rebit-core-frontend` (Berry build → nginx).
5. **`deploy`** (`needs: [build-api, build-frontend]`, `environment: production`, только main) — SSH (`SSH_PRIVATE_KEY` + `ssh-keyscan`) → публикация **versioned Swarm config/secrets** (`deploy/swarm-publish-runtime.sh`) → `make deploy` (см. §7).

**GitHub Secrets:** `REGISTRY_HOST`=`ghcr.io`, `REGISTRY_USER`, `TOKEN_GIT_HUB` (PAT для ghcr и SSH-login на сервере), `REGISTRY`=`ghcr.io/rebit-pro`, `HOST`, `PORT`, `SSH_PRIVATE_KEY` (ed25519 deploy-ключ), `DEPLOY_USER`=`deploy`. **GitHub Variables:** `VITE_API_URL`=`https://api2.rebit-pro.ru`, `VITE_GEETEST_CAPTCHA_ID` (→ id Yandex SmartCaptcha). Тег образов — `github.sha`; `BUILD_NUMBER`=`github.run_number` (версия + имена versioned config/secrets).

> **Усиление пайплайна (сверх P2P):** e2e (Playwright/Cucumber) **включены** в CI (в P2P — `if: false`); **security-scan** — Trivy/Grype (fail при HIGH/CRITICAL) + `composer audit` + `npm audit` + gitleaks; проверка `APP_DEBUG=0` в прод-образе; **миграции-gate** до переключения трафика (в P2P `api-migrate-deploy` закомментирован — мы включаем, см. §7/§15).

---

## 7. Деплой, миграции, cron

**Деплой** (`Makefile` эталона):
```makefile
deploy:
	envsubst < docker-compose-production.yml > "$$TEMP/compose.yml"
	DOCKER_HOST=ssh://deploy@$$HOST:$$PORT \
	  docker stack deploy --compose-file "$$TEMP/compose.yml" rebit-admin --with-registry-auth --prune
```
Вызывается джобой `deploy` (GitHub Actions). Механика (образец P2P): `scp` `docker-compose-production.yml` на сервер в релиз `site_${BUILD_NUMBER}/` (atomic symlink `site → site_N`, `KEEP_RELEASES=2`); рядом — `.env` с именами **versioned** Swarm config/secrets; `docker login ghcr` → `docker pull` образов → `docker stack deploy --with-registry-auth --prune --resolve-image=never -c docker-compose.yml admin`. Стек — `admin`.

**Миграции** — one-shot сервис `api-migration` (§3.3) при каждом деплое: `wait-for-it db` → `php bin/app.php migrate` (факт). Идемпотентность обеспечивает `schema_migrations` (факт — [`03-database.md`](03-database.md) §10).

**Cron** — `crazymax/swarm-cronjob` на manager-ноде читает labels у сервисов с `replicas: 0`:
```yaml
api-schedule-run:
  image: ${REGISTRY}/rebit-admin-api-php-cli:${IMAGE_TAG}
  command: ["sh","-c","exec php bin/app.php schedule:run"]
  deploy:
    replicas: 0
    labels:
      - swarm.cronjob.enable=true
      - swarm.cronjob.schedule=* * * * *
      - swarm.cronjob.skip-running=true
    restart_policy: { condition: none }
```
Так на инфраструктуру ложатся задачи из [`01-scenarios.md`](01-scenarios.md) §5 / [`03-database.md`](03-database.md) §7: `schedule:run` (диспетчер, ежеминутно), либо каждая задача отдельным cron-сервисом — `events:dispatch-outbox` (мин), `auth:purge-expired-tokens` (`0 * * * *`), `api-postgres-backup` (`0 * * * *`). Двойной запуск исключён (`skip-running` + локи `cron_locks`/`symfony/lock`).

> **Требуется:** перевести CLI с `match($argv)` (факт) на **`symfony/console`** — нужно для аргументов, кодов возврата и `schedule:run` (зафиксировано как открытый вопрос в [`01-scenarios.md`](01-scenarios.md) §8).

**Откат:** отдельной rollback-стадии нет — Swarm rolling update (`parallelism: 1`) не заменяет старые реплики, пока новые не прошли healthcheck; ручной откат — `docker stack deploy` с предыдущим `IMAGE_TAG` (образы тегированы и лежат в registry) либо `docker service rollback rebit-admin_api`.

---

## 8. Секреты и конфигурация

- **Прод (3 уровня, как в P2P):** несекретные env — Swarm **config** `admin_backend_env_<BUILD>` (монтируется как `.env`); секреты — **versioned Swarm secrets** `<name>_<BUILD>` (immutable → корректный rollback), публикуются `deploy/swarm-publish-runtime.sh` из `/srv/admin-rebit-core/swarm/secrets/*` (кладутся на сервер вручную из `deploy/secrets/*.example`). В контейнерах — `*_FILE` env; приложение читает файл (`DB_PASSWORD_FILE`). CI-доступы — **GitHub Secrets**, публичные `VITE_*` — **GitHub Variables**.
- **Факт проекта:** сейчас конфиг читается из `.env` через `phpdotenv safeLoad()`. Для прода — заменить `.env` на docker secrets + `*_FILE`, оставив `.env` только для локалки.
- **Dev-секреты** — файлы `docker/development/secrets/*` в репозитории (заведомо небоевые значения), как в эталоне.
- **Правила:** секретов нет в образах и гите; `APP_DEBUG=false` на проде (факт `.env`: `APP_DEBUG=1` — только локально); переменные окружения — через compose `environment:`.

---

## 9. Наблюдаемость

- **Health-checks:** `GET /health` (факт — `HealthAction`, `{status: ok}`), `GET /health/nginx` (факт), php-fpm `/ping`. HEALTHCHECK во всех образах (`interval=5s`) — Swarm/Traefik используют их для rolling-update и маршрутизации.
- **Логи:** Monolog → `php://stderr` → `docker service logs` (пакет `monolog/monolog` уже подключён — факт: осталось зарегистрировать в контейнере). Формат — JSON для машинной обработки.
- **Ошибки:** интеграция **Sentry** (`sentry/sdk`) через `SENTRY_DSN_FILE` — как в эталоне (decorator над обработчиком ошибок).
- **Аудит:** доменные события пишутся в `audit_log` ([`03-database.md`](03-database.md) §8), `SecurityAlert` при аномалиях.
> **Усиление:** централизованная агрегация логов (Loki/ELK) + метрики (cAdvisor/node-exporter → Prometheus → Grafana) + алерты (падение реплик, место на диске, ошибки ACME).

---

## 10. Бэкапы и восстановление

- **БД:** `api-postgres-backup` — `pg_dump | gzip -9` → S3, ежечасно (swarm-cronjob `0 * * * *`). Retention/lifecycle — на стороне бакета.
- **acme.json** (сертификаты Traefik) и docker secrets — в резерв отдельно.
- **Restore-процедура** (задокументировать и **регулярно проверять**): поднять `api-postgres`, `gunzip | psql`, затем `api-migration` доводит схему. RPO ≤ 1 ч (частота дампа), RTO — по регламенту.
- Данные БД — на выделенной ноде (`node.labels.db==db`), volume `api-postgres` вне контейнера.

---

## 11. Безопасность (усиление сверх эталона)

Сводит инфраструктурный слой с прикладным ([`01-scenarios.md`](01-scenarios.md) §6, OWASP). **Жирным — то, чего в эталоне нет и что добавляем** (это и делает архитектуру «усиленной»).

**ОС / сеть (репо `cluster`):**
- пользователь `deploy` без пароля, вход только по SSH-ключу (факт эталона);
- GPG-signed apt Docker, registry-mirror, авто-prune (факт эталона);
- **ufw/firewall:** наружу только 22/80/443; Swarm-порты `2377/tcp`, `7946/tcp+udp`, `4789/udp` — только между нодами;
- **харднинг sshd** (`PasswordAuthentication no`, `PermitRootLogin prohibit-password`), **fail2ban**, **unattended-upgrades**.

**Docker / образы:**
- multi-stage, non-root (`www-data`/`app`/`node`), `expose_php=Off`, `--no-dev -o`, opcache;
- **`resources.limits`** на сервисы (анти-DoS, стабильность);
- **сканирование образов** (Trivy/Grype) в CI, `read_only`-контейнеры где возможно, `no-new-privileges`.

**Сеть / TLS (репо `traefik`):**
- `exposedByDefault=false`, изоляция в overlay `traefik-public`;
- Let's Encrypt, HSTS, `secure-headers` (факт эталона);
- **`tls.options` (minVersion TLS1.2, cipherSuites)**, **basic-auth дашборда**, **rate-limit middleware** на шлюзе.

**Приложение** (детали — [`01-scenarios.md`](01-scenarios.md) §6): аутентификация middleware, RBAC/IDOR, серверная валидация, prepared statements (факт — PDO), argon2, CORS-whitelist, security-заголовки, `APP_DEBUG=false`.

**Секреты:** versioned docker secrets/configs `*_FILE`, GitHub Secrets/Variables, ничего в образах/гите; **ротация ключей** (versioned по `BUILD_NUMBER`); `composer audit` / `npm audit` в CI.

---

## 12. Makefile — единая точка команд

Сейчас — только composer-скрипты (`migrate`, `fixtures`, `test`, `lint` — факт). Целевой `Makefile` (по эталону), пробрасывает `UID/GID` в build-args:

| Группа | Цели |
|---|---|
| Жизненный цикл | `init`, `up`, `down`, `restart`, `docker-build`, `docker-down-clear` |
| API | `api-init`, `api-migrations`, `api-fixtures`, `api-check`, `api-lint(-fix)`, `api-analyze`, `api-test`, `api-backup` |
| Frontend | `frontend-init`, `frontend-check` (lint+typecheck), `frontend-build` |
| E2E | `test-smoke`, `test-e2e` (Playwright/Cucumber) |
| Образы | `build`, `try-build`, `push` |
| Testing | `testing-build`, `testing-init`, `testing-smoke`, `testing-e2e` |
| Деплой | `deploy` (`envsubst` + `docker stack deploy` по SSH), `deploy-clean` |

---

## 13. План внедрения (порядок, пересобран по консилиуму)

Порядок исправлен: провижининг и сеть — до прод-стека; console — до cron; бэкапы/алертинг — до ввода в прод.
0. **Реструктуризация в монорепо (§1a):** перенести бэкенд из корня в `api/` (`src/ config/ public/ bin/ migrations/ tests/ composer* docker/` → `api/`), перенастроить `docker-compose.yml` (build-context `api`), `public/index.php`, PSR-4 autoload; прогнать стек заново. Инициализировать git-репозиторий `git@github.com:rebit-pro/admin-rebit-core.git`.
1. **Dockerfile'ы:** довести php-образ (`pdo_pgsql` + opcache + стадии, единая база dev/prod — не alpine-vs-debian) → перевести проект на Postgres, **отказаться от SQLite полностью** (тесты — на Postgres в CI).
2. `symfony/console` (нужен для cron-команд/`schedule:run`, которые фигурируют дальше).
3. `docker-compose.yml` до полного dev-набора (+ php-cli, mailer, secrets, traefik-dev); `Makefile`.
4. Провижининг: `cluster` (Ansible) — Swarm, `deploy`-юзер, лейбл `db=db`, **+ firewall(`DOCKER-USER`)/sshd/fail2ban/chrony**, overlay `--opt encrypted`.
5. Шлюз: `traefik`-стек (ACME, secure-headers, **+ tls.options/basic-auth/rate-limit**) + внешняя сеть `traefik-public`.
6. Prod-образы (Berry-фронт multi-stage + env-подстановка, API) → push в registry.
7. `docker-compose-production.yml` (Swarm: реплики, deploy.labels, cron через swarm-cronjob, placement БД, secrets `*_FILE`) + **миграции как gate-шаг до переключения трафика** (см. §15).
8. Бэкапы (S3+SSE) + алертинг + ротация docker-логов + Sentry (PII-scrubbing) — **до** первого прод-деплоя.
9. **GitHub Actions** (`.github/workflows/makefile.yml`: api-checks/frontend-checks → build-api/build-frontend → deploy) + `deploy/swarm-publish-runtime.sh` + security-scan (Trivy, `composer/npm audit`, gitleaks).
10. Первый прод-деплой; далее — observability-стек (Loki/Prometheus) и HA-апгрейды (§15).

## 15. Prod must-fix по итогам консилиума

Не блокеры `05-modules.md`, но обязательны до прод-релиза (сверх §11):
- **Миграции при деплое (CRITICAL):** `api-migration` как отдельный **gate-шаг**, завершающийся ДО переключения трафика (в Swarm `depends_on` не работает — не полагаться на него); `pg_advisory_lock` на весь прогон; **expand/contract**-дисциплина (обратносовместимые миграции, две фазы), down-эквиваленты критичных шагов, health-gate.
- **HA/DR данных (CRITICAL):** для MVP явно зафиксировать **деградированный SLA** — single-node Postgres + ежечасный `pg_dump`, **RPO ≤ 1 ч, целевой RTO ≤ 2 ч**; WAL-архив/PITR + streaming-реплика (patroni/managed-PG) — первый апгрейд после MVP; **алертинг + ротация docker-логов (`json-file max-size/max-file`) — в CORE, не опция**.
- **Домены раздельные → CORS обязателен:** админка `admin.rebit-pro.ru`, API `api2.rebit-pro.ru`. api-nginx отдаёт **CORS-whitelist на `https://admin.rebit-pro.ru`** (не `*`), preflight `OPTIONS→204`, заголовки `Authorization/Content-Type`. Фронт — `VITE_API_URL=https://api2.rebit-pro.ru` (env-подстановка в прод-образ, §2.4).
- **Сетевой харднинг:** правила `DOCKER-USER` (ufw не применяется к опубликованным Docker-портам иначе), `--opt encrypted` на overlay-сетях, `chrony`/NTP.
- **Ресурсы:** к `limits` добавить `reservations` (иначе Swarm переподпишет ноду → OOM).
- **Rollback-контур:** трекинг `latest-stable` тега, связка «образ ↔ схема» (не откатывать образ отдельно от совместимой схемы), IMAGE_TAG с git-sha.
- **Секреты:** runbook ротации (docker secrets иммутабельны → версионирование `*_v2` + update сервиса); SSE/шифрование дампов; gitleaks в CI.
- **ACME:** при переходе на мультименеджер Traefik остаётся single-replica либо распределённое хранилище сертов; бэкап `acme.json`.

## 16. Решения по оставленным вопросам (зафиксировано)

1. **SLA данных (RTO/RPO).** MVP: single-node Postgres + ежечасный `pg_dump` → **RPO ≤ 1 ч, RTO ≤ 2 ч** (restore + `migrate`), деградированный SLA — согласовать с заказчиком письменно. **Апгрейд №1 после MVP:** WAL-архивация/PITR (цель **RPO ≤ 5 мин**) + streaming-реплика (patroni / managed-PG); тогда RTO ≤ 30 мин. Проверка restore — ежемесячно, автоматизированная.
2. **Observability — минимум в CORE, полный стек позже.** MVP (обязательно): health-checks (факт), Monolog→stderr в JSON + `docker service logs` + ротация `json-file` (`max-size`/`max-file`), Sentry с PII-scrubbing, **минимальные алерты** — email/Sentry на `severity=critical` из `audit_log`/`security.*`, падение реплик (по healthcheck), диск > 85%, ошибки ACME. **После MVP:** агрегация логов (Loki/ELK) + метрики (cAdvisor/node-exporter → Prometheus → Grafana) + Alertmanager. Логика: без базовых алертов single-node отказ не заметят; полный стек для мини-админки на старте — оверинжиниринг.
3. **Капча.** Yandex SmartCaptcha вместо GeeTest — см. [`01-scenarios.md`](01-scenarios.md) §6.9.

---

## 14. Принятые решения

1. **Оркестратор — Docker Swarm** (эталон студии, минимум операционной сложности). Kubernetes — только если появятся требования по масштабу/экосистеме, которых сейчас нет (YAGNI).
2. **Домены — раздельные (по требованию заказчика):** админка — `admin.rebit-pro.ru`, API — `api2.rebit-pro.ru` (кросс-origin, как `app`/`api` в P2P). У каждого — свой Traefik-роутер + ACME-сертификат. Фронт бьёт на `VITE_API_URL=https://api2.rebit-pro.ru`; api-nginx отдаёт **CORS-whitelist на `https://admin.rebit-pro.ru`** (не `*`). *(Ранее рассмотренный same-origin `/api` — отклонён.)*
3. **Registry — GitHub Container Registry (`ghcr.io/rebit-pro`)** — единый с CI (GitHub Actions), как в P2P. Логин `docker/login-action` по `REGISTRY_HOST=ghcr.io` / `REGISTRY_USER` / `TOKEN_GIT_HUB` (GitHub Secrets); на сервере — `docker login ghcr.io` тем же PAT (`--with-registry-auth`). Образы — private-пакеты GitHub. Образы не содержат ПДн → ghcr вне 152-ФЗ-ограничений; `dockerhub.timeweb.cloud` в `daemon.json` остаётся только зеркалом базовых образов.
4. **CI/CD — GitHub Actions** (по образцу Rabbit P2P `.github/workflows/makefile.yml`), поверх целей `Makefile`; деплой по `push` в `main` через SSH + `docker stack deploy`. Jenkins эталона Елисеева **не используем**.
5. **Бэкапы — S3-совместимое RU-хранилище** (Yandex Object Storage / Timeweb S3), 152-ФЗ-совместимо. Retention: ежечасные дампы 7 дней + ежедневные 30 дней (lifecycle бакета). Restore проверяется ежемесячно.
6. **Без Redis на старте** — всё в Postgres (см. [`03-database.md`](03-database.md) §12). Вводится при росте нагрузки.
7. **Swarm — single-manager на старте**, `db=db` на выделенной ноде + 1–2 воркера. Переход на 3 менеджера (raft-кворум для HA) — когда SLA потребует отказоустойчивости control-plane; роль `swarm-worker`/join уже готова в `cluster`.
8. **Структура — монорепо Елисеева** (§1a): бэкенд в `api/`, фронт в `frontend/`, оркестрация (`Makefile`, `compose*`, `.github/`, `deploy/`) в корне. Репозиторий — `git@github.com:rebit-pro/admin-rebit-core.git`.
