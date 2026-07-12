# DevOps-архитектура — ReBit Admin Core

> Усиленная DevOps-архитектура: контейнеризация, три окружения, CI/CD, Docker Swarm-кластер, Traefik-шлюз с автоматическим TLS, секреты, cron, бэкапы, наблюдаемость и безопасность.
> **Эталон** — DevOps-связка Д. Елисеева: приложение (`/home/user/slim`) + кластер (`/home/user/cluster`, Ansible→Swarm) + шлюз (`/home/user/traefik`).
> **Отличие проекта:** фронтенд — **Vue 3 + Vuetify + Berry** (админ-шаблон), а не React. Бэкенд — Slim 4 / PHP 8.5 / PostgreSQL 17 (PDO, самописный `Migrator`).
> **CI/CD — GitHub Actions** (пайплайн по образцу Rabbit P2P `.github/workflows/makefile.yml`), **не** Jenkins. **Структура — монорепо Елисеева** (`/home/user/slim`): корень (`Makefile`, `compose*.yml`, `.github/`) + `api/` (весь бэкенд) + `frontend/` (см. §1a).
> **Конкретика:** репозиторий `git@github.com:rebit-pro/admin-rebit-core.git`; админка (фронт) — `admin.rebit-pro.ru`; API — `api2.rebit-pro.ru`; реестр образов — GitHub Container Registry (`ghcr.io/rebit-pro`).
> Опирается на [`01-scenarios.md`](01-scenarios.md) §5–6 (cron, безопасность) и [`03-database.md`](03-database.md) §10 (миграции). Уровень **L** по [[rebit-backend]] (раздел «Деплой»).
> **Ревизия 2026-07-11 (консилиум №2, перед исполнением):** переведено в **brownfield**-режим — боевой хост 37.143.8.221 уже несёт живой прод P2P и общий Traefik v2.11; сняты внутренние противоречия (ветка, префикс образов, build-context, механика деплоя, VITE, migrate-gate). Отчёт: [`plans/prod-deploy/consilium-2026-07-11.md`](plans/prod-deploy/consilium-2026-07-11.md).

## 0. Принципы

1. **Три окружения — три compose-файла:** локально собираем образы, в CI тянем их из registry, в проде раскатываем как Swarm-стек. Один и тот же образ проходит весь путь (build once, run anywhere).
2. **Multi-stage образы:** dev (Xdebug + код через volume) ≠ prod (`composer --no-dev -o`, opcache, код вкопирован, non-root).
3. **Инфраструктура как код:** серверы — Ansible; кластер — Docker Swarm; шлюз — Traefik-стек; пайплайн — **GitHub Actions** (`.github/workflows/makefile.yml`, поверх целей Makefile); команды — Makefile.
4. **Секреты — вне образов и репозитория:** versioned docker secrets/configs (`*_FILE`), **GitHub Secrets** (чувствительное) и **GitHub Variables** (публичные `VITE_*`).
5. **Всё запинено:** явные версии образов (`postgres:17-alpine`, `php:8.5-fpm-alpine`, `nginx:1.29-alpine`, `node:24-alpine`), теги приложения по номеру сборки. Версию Traefik **не пиним**: шлюз общий и уже развёрнут (факт: v2.11), его жизненный цикл — вне объёма проекта (§5).
6. **Усиление (сверх эталона):** firewall/ufw, харднинг SSH, fail2ban, сканирование образов, resource limits, защита дашборда Traefik, TLS-опции, агрегация логов — то, чего в референсе нет, вводим осознанно (см. §11).
7. **Brownfield:** боевой хост 37.143.8.221 — живой shared-сервер (соседний прод P2P + общий шлюз + deploy-юзер третьего проекта). Никаких «разворачиваем с нуля»: аудит → точечный gap-fix → свой стек через `deploy.labels`. Любое изменение общих компонентов (шлюз, демон Docker, firewall) — отдельный согласованный change.

---

## 1. Топология

Триада Елисеева, но **brownfield**: кластер и шлюз на боевом хосте уже существуют и обслуживают соседний прод (P2P: `app.rebit-pro.ru` → 200, `api.rebit-pro.ru` → 401). Мы добавляем **свой стек рядом**, не трогая общее.

```
                    ┌───────────────────────────────────────────────┐
   Разработчик ──►  │  push → GitHub → GitHub Actions (CI/CD)        │
                    └───────────────┬───────────────────────────────┘
                                    │ docker build+push → ghcr.io
                                    │ ssh deploy@37.143.8.221: migrate-gate → stack deploy
                                    ▼
   Internet ─► :80/:443 ─► ┌─── Docker Swarm: ОДИН хост (manager = db), brownfield ───┐
                           │  ┌─────────┐  сеть overlay: traefik-public (существует)  │
                           │  │ Traefik │◄─ ОБЩИЙ шлюз, факт v2.11 (уже развёрнут,    │
                           │  └────┬────┘   вне объёма проекта — только labels)       │
                           │       │ по deploy.labels сервисов                        │
                           │  ┌────▼──── стек `admin` (наш) ─────┐ ┌─ соседи ────────┐│
                           │  │ frontend (nginx+Berry SPA)       │ │ p2p (live-прод) ││
                           │  │ api (nginx) ─► api-php-fpm       │ │ ...             ││
                           │  │ cron (swarm-cronjob*) replicas:0 │ └─────────────────┘│
                           │  │ api-postgres                     │                    │
                           │  │ api-postgres-backup (pg_dump→S3) │                    │
                           │  └──────────────────────────────────┘                    │
                           └──────────────────────────────────────────────────────────┘
   Миграции — НЕ сервис стека: синхронный gate-шаг в `make deploy` ДО `stack deploy` (§7).
   * наличие swarm-cronjob на живом swarm — проверить при инвентаризации (§4).
```

**Три репозитория:**
| Репо | Роль | Технология |
|---|---|---|
| `admin-rebit-core` (этот, монорепо) | приложение: `api/` + `frontend/` | Docker, compose ×3, Makefile, `.github/workflows` |
| `cluster` (эталон `/home/user/cluster`) | референс провижининга; на живом хосте — **только аудит + точечный gap-fix**, НЕ `site.yml` (§4) | Ansible |
| `traefik` (эталон `/home/user/traefik`) | референс конфигурации живого шлюза; **не деплоим и не апгрейдим** (§5) | Docker Swarm stack |

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
- **prod** (далее): `context: ., dockerfile: docker/production/<svc>/Dockerfile`, внутри `COPY api/ ./`.
- **`.dockerignore` переписать ДО первой prod-сборки:** текущие паттерны (`vendor`, `.env`, `var/cache`…) якорные к корню контекста и **не исключают** `api/.env`, `api/var`, `api/vendor`, `frontend/node_modules`, `frontend/dist`. Целевой набор: `api/.env`, `api/var/`, `**/vendor/`, `frontend/node_modules/`, `frontend/dist/`, `docs/`, `.github/`. Основной канал риска — локальные `make build/push` (в CI-checkout `.env` нет — он в `.gitignore`); плюс CI-гейт «в образе нет `.env`/`var`» (§6).

> **Статус:** реструктуризация **выполнена и проверена** — `make api-migrate/api-fixtures/api-test`, `/health`, вход через фронт-прокси — всё зелёное. Остаток #13: prod-Dockerfile'ы, `docker-compose-production.yml`, GitHub Actions, `deploy/`, uuid-перекладка.

---

## 2. Контейнеры и Dockerfile'ы

Текущее состояние (факт): `docker/php/Dockerfile` тривиален (`FROM php:8.5-fpm-alpine; WORKDIR /app`) — **нет `pdo_pgsql`, opcache, стадий сборки**. Именно поэтому проект пока живёт на SQLite. Ниже — целевая раскладка `docker/{common,development,production,testing}/…`, как у эталона.

### 2.1. API php-fpm
- **Dev** (`docker/development/php-fpm/Dockerfile`): `php:8.5-fpm-alpine` + `pdo_pgsql`, **Xdebug** (из исходников), `php.ini-development`, проброс `UID/GID` (ARG) для `www-data`, `HEALTHCHECK` через `cgi-fcgi` на `/ping`. Код — через volume.
- **Prod** (`docker/production/php-fpm/Dockerfile`, 2 стадии):
  - `builder` (`php:8.5-cli`): `COPY composer.json composer.lock` → `composer install --no-dev --prefer-dist --optimize-autoloader`; чистка кэша.
  - runtime (`php:8.5-fpm`): `pdo_pgsql` + **opcache** (`opcache.enable=1`, `validate_timestamps=0`), `php.ini-production`, `COPY --from=builder /app/vendor ./vendor`, `COPY api/ ./` + `COPY docker/production/php-fpm/conf.d/ ...` (пути — от корневого context `.`, §1a/§6), `chown www-data var -R`; **без Xdebug**, `expose_php=Off`.

> Введение `pdo_pgsql` здесь **закрывает открытый вопрос** из [`03-database.md`](03-database.md): после этого разработка и прод идут на Postgres, SQLite остаётся только для быстрых юнит-тестов, и двойная ветка миграций больше не нужна.

### 2.2. API php-cli (`docker/{development,production,testing}/php-cli/Dockerfile`)
Один образ для **консоли, миграций и cron**. Prod — 2 стадии, `composer install --no-dev -o`, `USER app` (non-root), `wait-for-it` в комплекте. Testing-вариант — с dev-зависимостями (для фикстур против прод-подобных образов). Используется: `api-php-cli` (ручные команды), **migrate-gate в `make deploy`** (`docker run --rm`, §7), cron-сервисы.

### 2.3. API nginx (`docker/{common,development,production}/nginx`)
- Общий `conf.d/default.conf` (развитие текущего `docker/nginx/default.conf`): `root /app/public`, `try_files $uri /index.php$is_args$args`, fastcgi на `api-php-fpm:9000`, `/health` → факт `GET /health` приложения, `/health/nginx` → `200` (факт), CORS-заголовки по **белому списку** (не `*`), preflight `OPTIONS → 204`.
- **Prod** дополнительно `COPY ./public ./public` (index.php внутрь образа), security-заголовки.

### 2.4. Frontend — Vue + Vuetify + Berry
Факт сборки (`frontend/package.json`): `build = vue-tsc --noEmit && vite build`; стек Vite 7 / Vuetify 3.10 / Pinia / vue-router / vue-tabler-icons.
- **Dev** (`frontend/docker/development/node`): `node:24-alpine`, `npm run dev -- --host` (Vite HMR), проброс `UID/GID`. Отдельный nginx-роутер `frontend` проксирует `/` на `frontend-node:5173` и `/api` на `api` (сейчас dev-фронт поднимается напрямую сервисом `frontend-node`, порт `5174:5173` — факт).
- **Prod** (`frontend/docker/production/nginx`, 2 стадии; файла в репо ещё НЕТ — создаётся по образцу P2P `frontend/docker/production/nginx/Dockerfile`, подняв базу до `node:24-alpine`):
  - `builder` (`node:24-alpine`): `npm ci && npm run build` → артефакт `dist/`.
  - runtime (`nginx:1.29`): `COPY --from=builder /app/dist ./public`; **SPA-fallback** `try_files $uri $uri/ /index.html`; кэш статики (`js/css expires 1y`); `X-Frame-Options SAMEORIGIN`, security-заголовки.
  - **`VITE_*` запекаются build-args'ами на сборке** (механика эталона P2P; вариант «плейсхолдеры + entrypoint-sed» отклонён консилиумом — его нет ни в эталоне, ни в коде). Полный контракт: `VITE_API_URL=https://api2.rebit-pro.ru`, `VITE_SMARTCAPTCHA_CLIENT_KEY` (Yandex SmartCaptcha, §16.3 — переименовать из `VITE_GEETEST_CAPTCHA_ID`; код фронта пока грузит GeeTest — замена интеграции является **предусловием прод-релиза**), `VITE_APP_VERSION=${{ github.run_number }}`, `VITE_API_MOCKS_ENABLED=false` (факт dev: `=true` — моки в localStorage). CI **валидирует непустые значения** build-args до сборки (шаг validate, как в P2P, §6). Образ окружение-зависимый — по образу на окружение.

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
Все сервисы — **готовые образы из registry** (`image: ${REGISTRY}/admin-rebit-core-*:${IMAGE_TAG}` — единый префикс по имени репозитория, §14.9), `APP_ENV=prod`, `APP_DEBUG=0`. Отдельный `testing-api-php-cli` (с dev-зависимостями) — для миграций и фикстур. Traefik без публикации портов. Прогон smoke + e2e (Playwright/Cucumber — факт: фронт уже содержит `test:e2e` на Cucumber+Playwright).

### 3.3. Прод — `docker-compose-production.yml` (Swarm stack)
`version: "3.9"`. Ключевые отличия от локалки:
- образы **из registry**; **нет** сервиса traefik (шлюз развёрнут отдельным стеком), сеть `traefik-public` — `external: true`;
- **реплики + rolling update с авто-откатом** у `frontend/api/api-php-fpm`:
  ```yaml
  deploy:
    mode: replicated
    replicas: 2                      # 1–2 — финализировать по обмеру ёмкости хоста под ТРИ стека (§4 шаг 0)
    update_config: { parallelism: 1, delay: 10s, order: start-first, failure_action: rollback }
    rollback_config: { parallelism: 1, delay: 5s }
    restart_policy: { condition: on-failure }
    resources:
      limits: { cpus: "1.0", memory: 512M }
      reservations: { cpus: "0.25", memory: 128M }   # значения — после обмера хоста; без reservations Swarm переподпишет ноду → OOM
  ```
  Плюс **healthcheck compose-уровня** у `api`/`api-php-fpm`/`frontend` (как в эталоне P2P) — без него `failure_action: rollback` и rolling update не срабатывают;
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
  **Имена `entryPoints`/`certResolver`/`middleware` выше — предварительные:** финализировать по фактической конфигурации живого шлюза v2.11 (`docker service inspect` при инвентаризации, §4 шаг 0) — labels обязаны ссылаться на реальные имена, иначе роутер молча не поднимется.
  **HTTP→HTTPS — своими роутерами:** catchall-редирект живого шлюза не работает (`HostRegexp(`.+`)` — невалидный v2-синтаксис; проверено: `http://admin.rebit-pro.ru` → 404 без редиректа). Для обоих хостов добавить парный роутер на entrypoint `http` с middleware `redirect-to-https`. Правка самого шлюза — отдельный change с его владельцем (§5).
  **Правило: no published ports** — сервисы стека не публикуют порты наружу (вход только через шлюз); в dev-compose порт БД перевесить на `127.0.0.1:54325` (docker-DNAT обходит ufw);
- **миграций в стеке НЕТ** — one-shot-сервис `api-migration` отклонён консилиумом: в Swarm `stack deploy` раскатывает все сервисы разом, и «сервис в стеке» гейтом не является (эталон P2P свой аналог держит закомментированным). Миграции — **синхронный gate-шаг в `make deploy` ДО `docker stack deploy`** (§7): упавший `migrate` валит деплой до переключения трафика;
- **cron** — сервис `crazymax/swarm-cronjob` на manager-ноде (см. §7);
- **Postgres: MVP-топология — один хост (manager = db, §14.7)**: `placement.constraints: [ node.role == manager ]`, `endpoint_mode: dnsrr`. Ansible-роль `swarm-labels` по живому хосту **не гонять** (`labels_state: replace` затирает чужие labels); если при инвентаризации на ноде обнаружится пригодный label — можно использовать его;
- **секреты/config** — `external: true`, versioned (`admin_backend_env_<BUILD>`, `<secret>_<BUILD>`); имена подставляет CI (GitHub Actions) через `deploy/swarm-publish-runtime.sh` (§8).

**Сводка local → prod:** build→pull · labels→deploy.labels · +replicas/rolling (start-first + auto-rollback) · +TLS/ACME/secure-headers · **migrate-gate ДО deploy** · +swarm-cronjob · secrets `${*_FILE}` · сеть `traefik-public` external · no published ports.

---

## 4. Кластер — brownfield: аудит живого хоста + точечный gap-fix (репо `cluster` — референс)

Эталон `/home/user/cluster` написан под провижининг **голых** серверов. Боевой хост 37.143.8.221 — **живой shared-прод** (Swarm с соседним стеком P2P, общий шлюз, deploy-юзер третьего проекта). **`site.yml` по нему не гонять** — конкретные угрозы соседям, найденные консилиумом в ролях эталона:
- роль `docker`: `docker-ce` со `state: latest` (внеплановый апгрейд с рестартом `dockerd` = даунтайм всех стеков), перезапись `daemon.json`, ежедневный `docker system prune -af` без label-фильтра;
- роль `swarm-manager`: `docker swarm init` на хосте с живым Swarm; `create user` без `append` (риск срезать группы существующего `deploy`);
- роль `swarm-labels`: `labels_state: replace` — затирает существующие labels ноды (риск уронить placement соседей при совпадении hostname);
- `add-ssh-key.yml` / `files/`: содержит **чужой ключ** (`tarasov.ae@nikamed-it.ru`) — вычистить из репо; `docker-login.yml` передаёт пароль в argv — перевести на `--password-stdin` + `no_log`.

**Шаг 0 — инвентаризация по SSH (до любых серверных работ и до финализации §3.3):** состояние Swarm и нод (`docker node ls` + labels), список стеков/сервисов/сетей (`traefik-public`?), **фактический конфиг живого шлюза** (`docker service inspect`: имена entrypoints / certResolver / middleware, версия), наличие `swarm-cronjob`, ёмкость (RAM/CPU/диск) под три стека, `authorized_keys` у `deploy` и `root`, владельцы/права `/srv`, содержимое `daemon.json`, версии docker/ОС.

**Gap-fix (только точечно, идемпотентно, по итогам аудита):** недостающее из эталонного набора (swap, prune **с label-фильтром**, python-docker SDK) — отдельными минимальными плейбуками/командами, не трогая `daemon.json`, версию docker-ce и существующие labels. Раздача SSH-ключей CI — `authorize-deploy.yml` по образцу (после гигиены ключей).

> **Харднинг (ufw/DOCKER-USER, sshd, fail2ban, unattended-upgrades) — этих ролей в эталоне НЕТ:** отдельная разработка с прогоном на чистой VM и внедрением в чендж-окно с консольным (не только SSH) доступом — см. §11. По живому хосту без обкатки не применять.

**Мультименеджер/масштаб:** MVP — single-manager = единственный хост (§14.7). Роль `swarm-worker` в эталоне никуда не подключена — для масштабирования (пост-MVP) нужен отдельный play.

---

## 5. Шлюз — Traefik: УЖЕ развёрнут, общий, вне объёма проекта

Живой шлюз на 37.143.8.221 (факт: **v2.11**, entrypoints 80/443 `mode: host`) обслуживает соседний прод P2P и как минимум ещё один стек. **В рамках admin-проекта шлюз не деплоим и не апгрейдим** — подключаемся к внешней overlay-сети `traefik-public` и объявляем маршруты **только своими `deploy.labels`** (§3.3). Репо `/home/user/traefik` — референс его конфигурации.

**Что известно о конфигурации (уточнить фактические имена при инвентаризации, §4 шаг 0):**
- `--providers.docker.swarmMode=true`, `--providers.docker.exposedByDefault=false`;
- entrypoints `http :80` / `https :443` (`mode: host`);
- **ACME/Let's Encrypt** (`httpChallenge`), `acme.json` в volume `traefik-public-certs`, e-mail оператора `vidmon83@vk.com` — сертификаты для `admin.`/`api2.` выпустятся по labels нашего стека (порт 80 открыт — проверено);
- middleware **`secure-headers`** (HSTS, CSP) — цепляем в своих роутерах;
- **глобальный redirect http→https НЕ работает** (catchall `HostRegexp(`.+`)` — невалидный синтаксис для v2; проверено live: `http://admin.rebit-pro.ru` → 404). Редирект для своих хостов — своими парными роутерами на entrypoint `http` (§3.3).

**Изменения самого шлюза** (фикс catchall-редиректа, `tls.options` minVersion/cipherSuites — это file-provider, не labels; basic-auth дашборда; rate-limit middleware; миграция v2→v3) — **отдельный чендж-трек с владельцем шлюза**, вне релизов админки. Владельца шлюза определить (кто сопровождает стек `traefik` и его Jenkins-контур). Факт эталона: шлюз деплоится **Jenkins'ом (ветка master) / ручным `make deploy`**, не GitHub Actions.

---

## 6. CI/CD — GitHub Actions (`.github/workflows/makefile.yml`)

Один workflow, по образцу Rabbit P2P (`/home/user/rebit-p2p/.github/workflows/makefile.yml`). **Ветка деплоя — `master` (§14.10, решение заказчика: в `main` НЕ переименовываем)** — все триггеры и if-гейты пишутся на `master`, не копировать `main` из эталона. **Триггеры:** `push`/`pull_request` в `master`. На PR — только проверки; **build/push/deploy — только `push` в `master`** (`if: github.event_name == 'push' && github.ref == 'refs/heads/master'`). Джобы опираются на цели `Makefile`.

**Jobs (граф зависимостей):**
1. **`api-checks`** — `actions/checkout@v5` → `shivammathur/setup-php@v2` (PHP **8.5**, ext `intl,mbstring,curl,json,openssl,pdo_pgsql`, `composer:v2`) → `actions/cache@v4` (`api/vendor` по `api/composer.lock`) → **`services: postgres:17-alpine`** (тесты на Postgres, синхронно с §13.1) → в `working-directory: api`: `composer install` → `lint` → `cs-check` → `psalm` → `test` (PHPUnit). **Пререквизит в репо:** скриптов `cs-check`/`psalm` в `api/composer.json` сейчас НЕТ, `psalm.xml` не создан — добавить до первого запуска (`cs-check` = `php-cs-fixer fix --dry-run --diff`; `vendor/bin/psalm --init`). Сюда же — `composer audit` + **gitleaks**.
2. **`frontend-checks`** — `setup-node@v4` (node **24**, cache по `frontend/package-lock.json`) → `npm ci` → `lint` → `typecheck` → `typecheck:e2e` → **мок-e2e** (Cucumber+Playwright против `VITE_API_MOCKS_ENABLED=true`-сервера — гейтит деплой; в P2P e2e под `if: false`, мы включаем) → `npm audit` → `build`-прогон.
3. **`build-api`** (`needs: api-checks`, только master) — `docker/setup-buildx` → `docker/login-action@v3` (ghcr, **`GITHUB_TOKEN`** с `permissions: packages: write` — PAT для push не используется, §14.3) → `docker/build-push-action@v6` **четыре** образа: `admin-rebit-core-api` (nginx), `-api-php-fpm`, `-api-php-cli`, `-api-postgres-backup`; **`context: .`, `file: docker/production/<svc>/Dockerfile`** (согласовано с §1a; «context: api» эталона работает только потому, что у P2P `docker/` лежит внутри `api/`), тег `${{ github.sha }}`, cache `type=gha` **с per-image `scope`**, `provenance: false`.
4. **`build-frontend`** (`needs: frontend-checks`, только master) — шаг **validate непустых `VITE_*`** (как в P2P) → образ `admin-rebit-core-frontend` с **build-args** `VITE_API_URL`, `VITE_SMARTCAPTCHA_CLIENT_KEY`, `VITE_APP_VERSION`, `VITE_API_MOCKS_ENABLED=false` (§2.4).
5. **`security-scan`** (`needs: [build-api, build-frontend]`) — **Trivy по построенным образам** (fail при HIGH/CRITICAL), проверка «в образе нет `.env`/`var`» и `APP_DEBUG=0`.
6. **`deploy`** (`needs: [build-api, build-frontend, security-scan]`, **`environment: production`** — только на этой джобе, с required reviewers на первые релизы; только master) — SSH от **`deploy`-юзера** (`SSH_PRIVATE_KEY` + **пин `SSH_KNOWN_HOSTS`** вместо `ssh-keyscan` на каждом прогоне — TOFU/MITM) → публикация versioned Swarm config/secrets (`deploy/swarm-publish-runtime.sh`, от `deploy`) → `make deploy` с **migrate-gate** (§7) → smoke → **тег `latest-stable`** на прошедшие smoke образы (`docker buildx imagetools create -t ...:latest-stable`).

**GitHub Secrets:** `REGISTRY`=`ghcr.io/rebit-pro`, `HOST`=`37.143.8.221`, `PORT`, `DEPLOY_USER`=`deploy`, `SSH_PRIVATE_KEY` (отдельный ed25519 CI-ключ, в `authorized_keys` — с `restrict`), `SSH_KNOWN_HOSTS` (пин host key), `GHCR_PULL_TOKEN` (**machine-PAT только `read:packages`** — для `docker login` на сервере через `--password-stdin`, не в argv; §14.3). **GitHub Variables** (repo/environment-scoped): `VITE_API_URL`=`https://api2.rebit-pro.ru`, `VITE_SMARTCAPTCHA_CLIENT_KEY`. Тег образов — `github.sha`; `BUILD_NUMBER`=`github.run_number` (версия + имена versioned config/secrets). Плюс **branch protection** на `master`.

> Полные e2e против прод-подобного testing-compose (§3.2) — между build и deploy, по мере готовности testing-контура; до этого деплой гейтят мок-e2e из `frontend-checks`.

---

## 7. Деплой, миграции, cron

**Деплой — одна механика: релизная схема P2P** (`rebit-p2p/Makefile`, цели deploy/rollback; ранее приводившийся envsubst-сниппет через `DOCKER_HOST=ssh://` — из другого эталона, удалён консилиумом как вторая конфликтующая механика):
1. `ssh deploy@$HOST` → `mkdir /srv/admin-rebit-core/site_${BUILD_NUMBER}/`;
2. `scp docker-compose-production.yml` в релиз; рядом — `.env` с `REGISTRY`, `IMAGE_TAG` и именами **versioned** Swarm config/secrets (напечатаны `swarm-publish-runtime.sh` в `$GITHUB_ENV`);
3. `docker login ghcr.io` (machine-PAT `read:packages`, `--password-stdin`) → `docker pull` образов релиза;
4. **migrate-gate (§15 CRITICAL):** синхронный `docker run --rm --network <сеть БД> --env-file … ${REGISTRY}/admin-rebit-core-api-php-cli:${IMAGE_TAG} php bin/app.php migrate` — **до** `stack deploy`; exit ≠ 0 валит деплой, трафик остаётся на старой версии; в `Migrator` — `pg_advisory_lock` на весь прогон; дисциплина **expand/contract** (обратносовместимые миграции: старый код обязан работать со схемой N+1);
5. atomic symlink `site → site_${BUILD_NUMBER}` (`KEEP_RELEASES=2`) → `docker stack deploy --with-registry-auth --prune --resolve-image=never -c site/docker-compose.yml admin`. Имя стека — **`admin`** (единое: §1/§3.3/`STACK_NAME ?= admin` в Makefile, §14.9).

**Bootstrap первого деплоя:** сначала стек только с `api-postgres` → migrate-gate → полный стек (иначе первый migrate не найдёт БД/сети).

**Миграции** идемпотентны через `schema_migrations` (факт — [`03-database.md`](03-database.md) §10); связка «образ ↔ схема» — откат образа только вместе с совместимой схемой (expand/contract это и обеспечивает).

**Cron** — `crazymax/swarm-cronjob` (наличие на живом swarm проверить при инвентаризации; если нет — поставить отдельным стеком, это общий компонент) читает labels у сервисов с `replicas: 0`. **`schedule:run`-диспетчер и таблица `cron_locks` отменены решением [`03-database.md`](03-database.md)** — каждая задача отдельным cron-сервисом:
```yaml
api-purge-tokens:
  image: ${REGISTRY}/admin-rebit-core-api-php-cli:${IMAGE_TAG}
  command: ["php","bin/app.php","auth:purge-expired-tokens"]
  deploy:
    replicas: 0
    labels:
      - swarm.cronjob.enable=true
      - swarm.cronjob.schedule=0 * * * *
      - swarm.cronjob.skip-running=true
    restart_policy: { condition: none }
```
MVP-набор: `auth:purge-expired-tokens` (`0 * * * *`; **команду написать** — в коде её ещё нет) и `api-postgres-backup` (`0 * * * *`, §10). `events:dispatch-outbox` — вместе с outbox (MVP-2). Двойной запуск исключён `skip-running`. CLI уже на `symfony/console` (факт `bin/app.php`) — остаётся только добавить cron-команды.

**Откат — штатная процедура `make rollback`** (перенос из P2P): symlink назад на предыдущий релиз `site_N-1` (его `.env` хранит versioned-имена секретов той сборки — совместимость гарантирована) → `docker stack deploy` из него. Плюс автоматический слой: `update_config.failure_action: rollback` по healthcheck (§3.3) и тег `latest-stable` (§6) как маркер последнего прошедшего smoke образа. Учения по rollback — до объявления готовности (§13).

---

## 8. Секреты и конфигурация

- **Прод (3 уровня, как в P2P):** несекретные env — Swarm **config** `admin_backend_env_<BUILD>` (монтируется как `.env`); секреты — **versioned Swarm secrets** `<name>_<BUILD>` (immutable → корректный rollback), публикуются `deploy/swarm-publish-runtime.sh` (наш форк — **от `deploy`, не root**) из `/srv/admin-rebit-core/swarm/secrets/*` (кладутся на сервер вручную из `deploy/secrets/*.example`). CI-доступы — **GitHub Secrets**, публичные `VITE_*` — **GitHub Variables**.
- **Инвентарь прод-секретов (полный):** `DB_PASSWORD`, серверный ключ **SmartCaptcha**, S3-ключи бэкапа (IAM PutObject-only, §10), `SENTRY_DSN`, SMTP-креды. Плюс серверные: machine-PAT `read:packages` (реестр), CI SSH-ключ.
- **Права на сервере:** владелец `/srv/admin-rebit-core/swarm` — `deploy`, каталоги `0700`, файлы `0600`; publish-скрипт **фейлится**, если секрет-файл group/other-readable. Правило: **CI ходит на сервер только от `deploy`; root-ключей в GitHub Secrets нет.**
- **`*_FILE` — пункт работ, не факт:** приложение сейчас читает только `$_ENV['DB_PASSWORD']` (`api/config/`, phpdotenv `safeLoad()`) — контракт `DB_PASSWORD_FILE` **не реализован**, без него Swarm-secrets в проде не подхватятся. Реализовать чтение `<NAME>_FILE` с приоритетом над `<NAME>` (§13); `.env` остаётся только для локалки.
- **Dev-секреты** — файлы `docker/development/secrets/*` в репозитории (заведомо небоевые значения), как в эталоне.
- **Правила:** секретов нет в образах и гите; `APP_DEBUG=false` на проде (факт `.env`: `APP_DEBUG=1` — только локально); переменные окружения — через compose `environment:`; runbook ротации — §15.

---

## 9. Наблюдаемость

- **Health-checks:** `GET /health` сделать **readiness** — с проверкой БД (`PDO SELECT 1`): сейчас он статический `{status: ok}` (факт — `HealthAction`), и авто-rollback §3.3 по нему не сработает; статический вариант оставить как liveness. `GET /health/nginx` (факт), php-fpm `/ping`. **HEALTHCHECK — на уровне compose** (как в эталоне P2P), не «во всех образах» — Swarm использует их для rolling-update/auto-rollback.
- **Логи:** Monolog → `php://stderr` → `docker service logs` (факт: зарегистрирован в контейнере). Формат — JSON для машинной обработки. **Ротация `json-file` (`max-size`/`max-file`)** — через `logging:` в compose-файле стека, НЕ через правку общего `daemon.json` (brownfield, §4).
- **Ошибки:** интеграция **Sentry** (`sentry/sdk`) через `SENTRY_DSN_FILE` — как в эталоне (decorator над обработчиком ошибок).
- **Аудит:** доменные события пишутся в `audit_log` ([`03-database.md`](03-database.md) §8), `SecurityAlert` при аномалиях.
> **Усиление:** централизованная агрегация логов (Loki/ELK) + метрики (cAdvisor/node-exporter → Prometheus → Grafana) + алерты (падение реплик, место на диске, ошибки ACME).

---

## 10. Бэкапы и восстановление

> **СТАТУС: отложено решением заказчика 2026-07-12** — бэкапы НЕ являются пререквизитом первого прод-деплоя. Артефакты готовы и ждут ввода: образ `docker/production/postgres-backup/` (в CI не собирается), сервис для compose (комментарий в `docker-compose-production.yml`), секрет `admin_backup_aws_secret_access_key` (optional в publish-скрипте). **Зафиксированный риск:** до ввода бэкапов RPO не гарантирован — отказ диска единственного хоста означает потерю всех данных админки; SLA §16.1 (RPO ≤ 1 ч) начинает действовать только после ввода.

- **БД:** `api-postgres-backup` — `pg_dump | gzip -9` → S3 (SSE), ежечасно (swarm-cronjob `0 * * * *`). Образ `admin-rebit-core-api-postgres-backup` собирается в CI (§6), скрипт — перенос `backup.sh` из slim-эталона.
- **Пререквизиты до первого прод-деплоя (§13):** провайдер — **Yandex Object Storage** (§14.5); создать бакет + lifecycle (ежечасные 7 дней / ежедневные 30 дней); IAM-ключи **PutObject-only** → в Swarm-секреты.
- **acme.json** (сертификаты Traefik) и docker secrets — в резерв отдельно (acme — зона владельца шлюза, §5; согласовать).
- **Restore-runbook — обязательный артефакт** (задокументировать и прогнать **до первого прод-релиза**, далее — ежемесячно): поднять `api-postgres`, `gunzip | psql`, затем migrate-gate доводит схему. RPO ≤ 1 ч (частота дампа), RTO ≤ 2 ч (§16.1).
- Данные БД — volume `api-postgres` вне контейнера; топология MVP — один хост (§14.7).

---

## 11. Безопасность (усиление сверх эталона)

Сводит инфраструктурный слой с прикладным ([`01-scenarios.md`](01-scenarios.md) §6, OWASP). **Жирным — то, чего в эталоне нет и что добавляем** (это и делает архитектуру «усиленной»).

**ОС / сеть (репо `cluster`):**
- пользователь `deploy` без пароля, вход только по SSH-ключу (факт эталона);
- GPG-signed apt Docker, registry-mirror, авто-prune (факт эталона; на живом хосте prune — только с label-фильтром, §4);
- **ufw/firewall + правила `DOCKER-USER`:** наружу только 22/80/443; deny-правила — **только после инвентаризации опубликованных портов ВСЕХ стеков** (на хосте живут соседи);
- **харднинг sshd** (`PasswordAuthentication no`, `PermitRootLogin prohibit-password`), **fail2ban** (`mode=normal` — чтобы не банить серии SSH-подключений CI), **unattended-upgrades**;
- ⚠️ **ролей firewall/sshd/fail2ban/unattended-upgrades в эталоне `cluster` НЕТ** — это отдельная разработка: обкатка на чистой VM → внедрение в чендж-окно с **консольным** (не только SSH) доступом к серверу (§4);
- **гигиена доступов:** вычистить чужой ключ из `cluster`-репо (`add-ssh-key.yml` + `files/`), ревизия `authorized_keys` у `deploy`/`root` на сервере; CI-ключ — отдельный ed25519 с `restrict` в `authorized_keys`.

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

**Факт:** Makefile существует — dev-цели `up/down/restart/build/logs`, `api-migrate` (не `api-migrations`), `api-fixtures`, `api-test`, `api-lint`, `api-fixer`, `api-cli`, `frontend-check`, `frontend-build`. **Недостающее для CI/CD** (по эталону P2P; `STACK_NAME ?= admin`, `UID/GID` в build-args):

| Группа | Цели (добавить) |
|---|---|
| API | `api-analyze` (psalm), `api-backup` |
| E2E | `test-smoke`, `test-e2e` (Playwright/Cucumber) |
| Образы | `build`, `try-build`, `push` (префикс `admin-rebit-core-*`, §14.9) |
| Testing | `testing-build`, `testing-init`, `testing-smoke`, `testing-e2e` |
| Деплой | `deploy` (релизная механика P2P + migrate-gate, §7), `rollback`, `deploy-clean` |

---

## 13. План внедрения (порядок, пересобран по консилиуму 2026-07-11)

Выполнено ранее: ~~реструктуризация в монорепо + git-репозиторий~~ (сделано; деплойная ветка — `master`, §14.10), ~~`symfony/console`~~ (сделано — `bin/app.php`), `pdo_pgsql` в dev-образе есть.

0. **Блокер-пакет (до любых работ, треки параллельны):**
   - **SSH-канарейка с GitHub-hosted runner'а:** минимальный `workflow_dispatch`-workflow (`ssh -o BatchMode deploy@37.143.8.221 true`) — из среды аудита SSH-баннер не приходит, доступность с раннеров неизвестна. Провал = развилка **self-hosted runner** до написания deploy-джобы. Предусловия: файл workflow на текущей default-ветке; CI-ключ заранее положен в `authorized_keys` `deploy` (руками, из среды с рабочим SSH).
   - **Инвентаризация сервера** (§4 шаг 0): swarm/стеки/сети, фактические имена entrypoints/certResolver/middleware живого шлюза, наличие swarm-cronjob, ёмкость под три стека, `authorized_keys`, `/srv`, `daemon.json`.
   - **Tenancy-решение заказчика письменно:** общий `deploy`-юзер (группа docker = root-эквивалент; общий blast-radius admin-core ↔ photo ↔ traefik) — или изоляция (отдельный пользователь/сервер).
1. **Prod-Dockerfile'ы API** (`docker/production/{php-fpm,php-cli,nginx}`, context `.`; §2) → полный перевод на Postgres, **отказ от SQLite** (тесты — на Postgres, в CI — `services: postgres`).
2. **Приложение к проду:** cron-команда `auth:purge-expired-tokens`; **`*_FILE`-чтение секретов** (§8); `/health` → readiness с `SELECT 1` (§9); **SmartCaptcha вместо GeeTest во фронте** (§2.4, §16.3).
3. `docker-compose.yml` до полного dev-набора (+ php-cli, mailer, secrets, traefik-dev); `Makefile` — цели из §12; `.dockerignore` (§1a); `cs-check`/`psalm` + `psalm.xml` (§6).
4. **GitHub-side setup:** ветка `master` остаётся деплойной (в `main` не переименовываем — решение заказчика); branch protection на `master`; environment `production` (+ required reviewers); secrets/vars по списку §6; `permissions: packages: write`.
5. **Сервер (brownfield, по итогам шага 0):** точечный gap-fix (§4) — БЕЗ `site.yml`, БЕЗ деплоя/апгрейда шлюза; гигиена ключей (§11); свой стек входит только labels'ами. Харднинг (ufw/DOCKER-USER, sshd, fail2ban) — отдельный трек с обкаткой на VM (§11), не блокирует деплой.
6. **Prod-образы** (API ×3 + Berry-фронт с build-args + postgres-backup) → push в ghcr; проверка `docker pull` с сервера от `deploy` (read-PAT).
7. **`docker-compose-production.yml`** (стек `admin`: реплики/rolling/auto-rollback, healthchecks, deploy.labels по фактическим именам шлюза, cron-сервисы, secrets `*_FILE`, no published ports; §3.3) + `deploy/` (форк `swarm-publish-runtime.sh` под `deploy`) + `make deploy/rollback` с **migrate-gate** (§7).
8. ~~Бэкап-пререквизиты~~ — **отложено решением заказчика 2026-07-12** (§10, риск зафиксирован). Остаются до первого прод-деплоя: минимальные алерты + ротация docker-логов (`logging:` в compose — сделано) + Sentry (PII-scrubbing, §16.4).
9. **GitHub Actions** — полный workflow §6; включать поэтапно: сначала только checks-джобы → build/push → deploy.
10. **Bootstrap-деплой** (§7: стек с БД → migrate → полный стек) → smoke `https://admin.`/`https://api2.` + http→https редирект → контроль нетронутости соседних стеков → **учения `make rollback` и restore из S3-дампа** (§10) → объявление готовности. Далее — observability-стек и HA-апгрейды (§15/§16).

## 15. Prod must-fix по итогам консилиума

Не блокеры `05-modules.md`, но обязательны до прод-релиза (сверх §11):
- **Миграции при деплое (CRITICAL):** реализация — конкретная механика §7 шаг 4: синхронный `docker run --rm … migrate` в `make deploy` **до** `docker stack deploy` (в Swarm `depends_on`/one-shot-сервис гейтом не являются); `pg_advisory_lock` на весь прогон; **expand/contract**-дисциплина (обратносовместимые миграции, две фазы), down-эквиваленты критичных шагов, health-gate.
- **HA/DR данных (CRITICAL):** для MVP явно зафиксировать **деградированный SLA** — single-node Postgres + ежечасный `pg_dump`, **RPO ≤ 1 ч, целевой RTO ≤ 2 ч**; WAL-архив/PITR + streaming-реплика (patroni/managed-PG) — первый апгрейд после MVP; **алертинг + ротация docker-логов (`json-file max-size/max-file`) — в CORE, не опция**.
- **Домены раздельные → CORS обязателен:** админка `admin.rebit-pro.ru`, API `api2.rebit-pro.ru`. api-nginx отдаёт **CORS-whitelist на `https://admin.rebit-pro.ru`** (не `*`), preflight `OPTIONS→204`, заголовки `Authorization/Content-Type`. Фронт — `VITE_API_URL=https://api2.rebit-pro.ru` (env-подстановка в прод-образ, §2.4).
- **Сетевой харднинг:** правила `DOCKER-USER` (ufw не применяется к опубликованным Docker-портам иначе), `--opt encrypted` на overlay-сетях, `chrony`/NTP.
- **Ресурсы:** к `limits` добавить `reservations` (иначе Swarm переподпишет ноду → OOM); значения — после обмера хоста под **три** стека (§4 шаг 0); при нехватке — `replicas: 1` у frontend, а не отказ от reservations.
- **Rollback-контур:** релизная механика P2P — `make rollback` = symlink на предыдущий `site_N` (его `.env` хранит versioned-имена секретов той сборки) + `stack deploy` (§7); авто-слой `failure_action: rollback` по healthcheck (§3.3); тег `latest-stable` проставляется конкретным шагом CI после smoke (`imagetools create`, §6); связка «образ ↔ схема» — через expand/contract; IMAGE_TAG с git-sha.
- **Секреты:** runbook ротации (docker secrets иммутабельны → версионирование `*_v2` + update сервиса); SSE/шифрование дампов; gitleaks в CI.
- **ACME:** при переходе на мультименеджер Traefik остаётся single-replica либо распределённое хранилище сертов; бэкап `acme.json`.

## 16. Решения по оставленным вопросам (зафиксировано)

1. **SLA данных (RTO/RPO).** MVP: single-node Postgres + ежечасный `pg_dump` → **RPO ≤ 1 ч, RTO ≤ 2 ч** (restore + `migrate`), деградированный SLA — согласовать с заказчиком письменно. **Апгрейд №1 после MVP:** WAL-архивация/PITR (цель **RPO ≤ 5 мин**) + streaming-реплика (patroni / managed-PG); тогда RTO ≤ 30 мин. Проверка restore — ежемесячно, автоматизированная.
2. **Observability — минимум в CORE, полный стек позже.** MVP (обязательно): health-checks (факт), Monolog→stderr в JSON + `docker service logs` + ротация `json-file` (`max-size`/`max-file`), Sentry с PII-scrubbing, **минимальные алерты** — email/Sentry на `severity=critical` из `audit_log`/`security.*`, падение реплик (по healthcheck), диск > 85%, ошибки ACME. **После MVP:** агрегация логов (Loki/ELK) + метрики (cAdvisor/node-exporter → Prometheus → Grafana) + Alertmanager. Логика: без базовых алертов single-node отказ не заметят; полный стек для мини-админки на старте — оверинжиниринг.
3. **Капча.** Yandex SmartCaptcha вместо GeeTest — см. [`01-scenarios.md`](01-scenarios.md) §6.9. **Статус: решено, но НЕ сделано** — код фронта фактически грузит GeeTest со `static.geetest.com` (`AuthLogin.vue`); замена интеграции (фронт + серверная проверка + `VITE_SMARTCAPTCHA_CLIENT_KEY` + серверный ключ в секреты §8) — **предусловие прод-релиза** (§13.2); иначе нерабочий логин за китайским CDN + трансграничная передача (152-ФЗ).
4. **Sentry — SaaS строго без ПДн** (MVP): `send_default_pii=false` + `before_send`-санитизация (убирать email/логины/IP), DSN — в Swarm-секрете; режим зафиксировать письменно у заказчика (трансграничная передача при ПДн недопустима). Альтернатива self-hosted/RU-хостинг — при возражении заказчика или появлении ПДн в событиях. До подключения Sentry алерты — email/`audit_log` (§16.2).
5. **Tenancy (ОТКРЫТО — блокер серверных шагов, §13.0):** ожидает письменного решения заказчика — общий `deploy`-юзер с соседними проектами (группа docker = root-эквивалент, общий blast-radius) или изоляция (отдельный пользователь/сервер).
6. **Бэкапы — отложены** (заказчик, 2026-07-12): не пререквизит прод-деплоя; риск потери данных при отказе диска зафиксирован в §10. Ввод — отдельной задачей (бакет YOS + lifecycle + IAM + возврат сервиса в стек + restore-учение).
7. **Инвентаризация сервера выполнена (2026-07-12):** один узел `p903785.kvmvps` (manager, label `db=db` есть), **1 CPU / 2 ГБ RAM / 59 ГБ диск (36 ГБ свободно)**; стеки: `site` (P2P, 13 сервисов), `solcoing` (2), `traefik` (1) — наш `admin` четвёртый; сеть `traefik-public` существует; **Traefik v2.11: entryPoints `http`/`https`, certResolver `letsEncrypt`, middlewares `secure-headers`, `redirect-to-https` — labels §3.3 подтверждены дословно**, catchall-редирект действительно сломан; `swarm-cronjob` НЕТ → разворачивается отдельным стеком (`deploy/swarm-cronjob-stack.yml`); `daemon.json` — только registry-mirror timeweb; в `authorized_keys` у `deploy` — единственный ключ `tarasov.ae@nikamed-it.ru` (гигиена §11 актуальна). По ёмкости: **replicas 1** у всех сервисов стека, CPU-reservations не задаём, memory-reservations минимальные (§3.3 обновлён).

---

## 14. Принятые решения

1. **Оркестратор — Docker Swarm** (эталон студии, минимум операционной сложности). Kubernetes — только если появятся требования по масштабу/экосистеме, которых сейчас нет (YAGNI).
2. **Домены — раздельные (по требованию заказчика):** админка — `admin.rebit-pro.ru`, API — `api2.rebit-pro.ru` (кросс-origin, как `app`/`api` в P2P). У каждого — свой Traefik-роутер + ACME-сертификат. Фронт бьёт на `VITE_API_URL=https://api2.rebit-pro.ru`; api-nginx отдаёт **CORS-whitelist на `https://admin.rebit-pro.ru`** (не `*`). *(Ранее рассмотренный same-origin `/api` — отклонён.)*
3. **Registry — GitHub Container Registry (`ghcr.io/rebit-pro`)** — единый с CI (GitHub Actions), как в P2P. **Токен-модель (уточнена консилиумом):** push из CI — встроенный **`GITHUB_TOKEN`** (`permissions: packages: write`, эфемерный); на сервере — отдельный **machine-PAT только `read:packages`** через `--password-stdin` (не в argv). Широкий PAT двойного назначения (`TOKEN_GIT_HUB` из эталона) отклонён: на shared-сервере PAT оседает plaintext в `~/.docker/config.json` и виден соседним проектам — write-скоуп там = supply-chain-вектор. Образы — private-пакеты GitHub, ПДн не содержат → ghcr вне 152-ФЗ-ограничений; `dockerhub.timeweb.cloud` в `daemon.json` остаётся только зеркалом базовых образов.
4. **CI/CD — GitHub Actions** (по образцу Rabbit P2P `.github/workflows/makefile.yml`), поверх целей `Makefile`; деплой по `push` в `master` через SSH + `docker stack deploy`. Jenkins эталона Елисеева **не используем**.
5. **Бэкапы — Yandex Object Storage (S3), один провайдер.** Выбран из пары YOS/Timeweb: хостинг сервера — Timeweb, бэкапы держим у **другого** провайдера (DR: недоступность хостера не уносит и дампы); 152-ФЗ-совместимо. Retention: ежечасные дампы 7 дней + ежедневные 30 дней (lifecycle бакета). Restore проверяется ежемесячно; первый прогон — до прод-релиза (§10).
6. **Без Redis на старте** — всё в Postgres (см. [`03-database.md`](03-database.md) §12). Вводится при росте нагрузки.
7. **MVP-топология — ОДИН хост: manager = db** (факт живого сервера; `worker-db` из эталона — вне объёма MVP). Placement БД — `node.role == manager` (§3.3). Переход на 3 менеджера (raft-кворум) и выделенную db-ноду — когда SLA потребует; роль `swarm-worker` в эталоне не подключена, для масштабирования нужен отдельный play (§4).
8. **Структура — монорепо Елисеева** (§1a): бэкенд в `api/`, фронт в `frontend/`, оркестрация (`Makefile`, `compose*`, `.github/`, `deploy/`) в корне. Репозиторий — `git@github.com:rebit-pro/admin-rebit-core.git`.
9. **Имя стека — `admin`; префикс образов — `admin-rebit-core-*`** (по имени репозитория, как `rebit-p2p-*` у эталона). Единые по всему контуру: §3.2/§3.3/§6/§7/Makefile (`STACK_NAME ?= admin`). Прежние варианты (`rebit-admin`, `rebit-admin-*`) — отклонены консилиумом как источник рассинхрона.
10. **Ветка деплоя — `master`** (решение заказчика 2026-07-11, отменяет прежнюю рекомендацию переименования в `main`): все триггеры/if-гейты §6 и branch protection — на `master`; при копировании workflow из эталона P2P заменить `main` → `master`. Рабочие ветки — по маске `<type>/task_<имя>_ДД-ММ-ГГГГ` (`type`: devOps/frontend/backend) — зафиксировано в CLAUDE.md.
