# Прогресс реализации — ReBit Admin Core

> Живой документ фиксации результатов. Обновляется по мере выполнения задач.
> План основан на `05-modules.md §6–7` (границы MVP, очерёдность), проверен консилиумом.
> Легенда статусов: ✅ готово (проверено) · 🔄 в работе · ⏳ ожидает · ⛔ отложено.

## Обзор MVP-1

MVP-1 = тонкий вертикальный срез до прода: `Auth` + `Account` + `Users CRUD` + `RBAC (хардкод)` + один прод-деплой. Крупные механизмы (outbox, Highload, мультиролевость) — в MVP-2/3.

| # | Задача | Статус |
|---|---|---|
| 8 | Фундамент `Shared` + Postgres | ✅ |
| 9 | Ядро `Shared` (kernel) | ✅ |
| 10 | `Auth` в 4 слоя + Account | ✅ |
| 11 | `Access` (хардкод) + Users CRUD | ✅ |
| 12 | `Audit` (in-transaction) | ✅ |
| 13 | DevOps (single-node прод) | 🔄 (реструктуризация ✅) |
| 14 | Фронт Berry без моков | ✅ |

---

## Выполнено

### #8 — Фундамент `Shared` + Postgres ✅
- `composer`: `symfony/uid`, `symfony/console`; `docker/php/Dockerfile`: `pdo_pgsql` (opcache — в прод-Dockerfile #13).
- `src/Shared/Domain/ValueObject/Id.php` (UUID v7, генерация в приложении), `Clock`/`SystemClock`.
- `.env` → PostgreSQL (`DB_CONNECTION=pgsql`); проект уходит с SQLite.
- **Проверено:** образ с `pdo_pgsql`, `migrate`/`fixtures` на Postgres, `auth_users=6`, `/health` ok, `Id` v7, PHPUnit зелёный.

### #9 — Ядро `Shared` ✅
- События: `DomainEvent`, `RecordsEvents`, порт `EventPublisher`, `EventSubscriber`, `SubscriberPhase`, `SyncEventBus` (фазы in-transaction / after-commit).
- `UnitOfWork` (порт) + `PdoUnitOfWork` (flush after-commit при коммите, discard при откате).
- HTTP-ядро: `ErrorJsonMiddleware` (единый `{error}`, 422/404/500), `SecurityHeadersMiddleware` — подключены в `public/index.php`.
- Console-kernel (symfony/console): `MigrateCommand`, `LoadFixturesCommand` (заменили `match($argv)`).
- Модульный DI (`ContainerBuilder` + `config/di/*.php`), `Migrator` → `App\Shared\Persistence`.
- **Проверено:** `list`/`migrate` через консоль, PHPUnit **8 tests OK** (Id + SyncEventBus), `/health` + security-заголовки, 404 → JSON.

### #10 — Account (смена пароля/логина/email) ✅
- `AuthException` → реализует `Shared\Http\HttpException` (единый маппинг ошибок).
- Порт `PasswordHasher` + `NativePasswordHasher` (argon2id; verify совместим с bcrypt-фикстурами).
- `AuthRepository` расширен: `findUserById`, `updatePassword/Login/Email`, `isLoginTaken/isEmailTaken`, `deleteAllAccessTokensForUser`.
- Слой `Application`: `ChangePassword/ChangeLogin/ChangeEmail` (Command + Handler) через `UnitOfWork` + публикация событий.
- Доменные события: `UserPasswordChanged/LoginChanged/EmailChanged` (с redaction).
- Слой `Presentation`: `AuthenticationMiddleware` (Bearer → `identity`), 3 экшена, маршруты `/api/v1/account/*` за middleware; `config/di/auth.php`.
- **Проверено (HTTP, живой Postgres):** 401 без токена / 401 неверный текущий / 422 слабый / 200 смена + отзыв старого токена + новый валиден + вход новым паролем; смена логина 200, дубликат 409; смена email 401/200. PHPUnit **8 tests OK**.
- **Отложено:** полный переезд login/register в 4 слоя (органически); uuid-перекладка (#13); подтверждение смены email по коду + rate-limit (hardening); юнит-тесты хендлеров (после ввода порта репозитория).

### #11a — Access RBAC + Users чтение/создание ✅
- `Access`: `Permission` (enum), `RolePermissions` (хардкод owner/admin/user), `AccessDecision`, `RequirePermissionMiddleware`.
- `Shared\Http\Exception\HttpError` (message + status) для прикладных ошибок.
- Миграция `20260707120000_add_auth_users_status.sql` (`status` + индекс).
- `AuthRepository`: `createManagedUser`, `listUsers`/`countUsers` (поиск), `managedUserById`, `countActiveOwners`, `updateUserRole`, `setUserStatus`, `deleteUser`.
- `UserManagementPolicy` (инварианты owner), `UserCreated` событие, `ListUsers` Fetcher, `CreateUser` (Command+Handler).
- Маршруты `/api/v1/users` (GET список, POST создание, GET /{id}) за `Authentication` + `RequirePermission(users.manage)`.
- **Проверено (HTTP):** 401 без токена; **403** для роли `user`; 200 для owner/admin; **403** admin→owner (политика); 201 owner→admin; 409 дубликат; 422 слабый пароль; 200/404 get. PHPUnit **8 OK**.

### #11b — Users update-role / block / delete (инварианты owner) ✅
- Команды `ChangeUserRole`, `SetUserStatus` (block/unblock), `DeleteUser` (Command+Handler); события `UserRoleChanged/Blocked/Deleted`.
- `UserManagementPolicy` дополнена: `ensureNotSelf`, `ensureNotLastActiveOwner`.
- Блокировка отзывает токены; **вход блокируется** (`AuthService.login` → 403 `Account is blocked`; статус добавлен в чтения через `?? 'active'`).
- Маршруты `PATCH /users/{id}`, `POST /users/{id}/block|unblock`, `DELETE /users/{id}`.
- **Проверено (HTTP):** admin→owner блок 403; owner блок admin 200 + вход заблокированного 403 + разблок 200; смена роли 200; admin→owner 403; демоушен последнего owner 409; удаление себя 409; удаление admin 204 → 404. PHPUnit **8 OK**.

### #12 — Audit (in-transaction) ✅
- Миграция `20260707130000_create_audit_log.sql` (`audit_log` + индексы actor/subject/action; `actor_id` BIGINT FK→auth_users ON DELETE SET NULL).
- `Shared\Application\ActorContext` (request-scoped: actorId/ip/userAgent) — заполняется `AuthenticationMiddleware`.
- Порт `Audit\Application\Port\AuditLog` + `PdoAuditLog` (CAST в jsonb/inet).
- `AuditSubscriber` (phase **InTransaction**) подписан на события Auth/Users; подключён в `SyncEventBus` (первый реальный подписчик шины).
- Redaction — за счёт самих событий (payload без секретов).
- **Проверено (HTTP):** создание пользователя и смена пароля пишут `auth.user.created`/`auth.user.password_changed` с actor/subject/ip; **0 строк** с секретами в `changes`. PHPUnit **10 tests OK**.

### #14a — Фронт: реальная аутентификация ✅
- `frontend/.env`(+`.env.example`): `VITE_API_MOCKS_ENABLED=false`, `VITE_API_URL=` (пусто — устранён двойной `/api`).
- `src/api/auth.ts`: тип `AuthUser` расширен (`login/role/phone/address`), добавлен `getUser()` (`GET /api/v1/auth/user`).
- `src/stores/auth.ts`: `fetchUser()` (+ разовое обновление профиля при инициализации, 401 обрабатывает interceptor).
- **Проверено:** `vue-tsc` без ошибок; SPA на vite:5174 отдаётся (200); вход `owner/secret123` через vite-proxy→nginx→Slim вернул **реальный** токен и полный профиль; конверт ошибок проходит. Моки off.
### #14b — Экран настроек аккаунта ✅
- `src/api/account.ts` + действия стора `changePassword/changeLogin/changeEmail` (смена пароля обновляет сессию новым токеном).
- `src/views/account/AccountSettingsPage.vue` — три формы (пароль/логин/email) + snackbar, маршрут `/account/settings`, пункт меню.

### #14c — Управление пользователями ✅
- `src/api/users.ts` (list/create/changeRole/block/unblock/remove).
- `src/views/users/UsersPage.vue` — таблица (поиск, пагинация), создание, смена роли, блок/разблок, удаление с подтверждением; маршрут `/users`, пункт меню.

**Проверка #14b/c:** `vue-tsc` без ошибок; `vite build` собрал оба чанка (`AccountSettingsPage`, `UsersPage`); точные HTTP-запросы экранов прогнаны через реальный путь браузера (vite-proxy→nginx→Slim): `GET /users` → `{data:{items}}`, `POST /users` → 201, `POST /account/change-login` → 200. *Живой клик в браузере не гонялся (chromium не закеширован); UI-логика подтверждена typecheck+build, сеть — реальными запросами.*

### #13.0 — Реструктуризация в монорепо ✅
- Бэкенд перенесён из корня в **`api/`** (`bin config public src tests migrations composer* phpunit.xml .env* var vendor`).
- **`docker/` и `.dockerignore` — в корень монорепо** (по требованию заказчика: инфраструктура/технические файлы вне `api/`).
- Корень: `Makefile` (новый), `docker-compose.yml`, `docker/`, `api/`, `frontend/`, `docs/`; `.gitignore` под монорепо.
- `docker-compose.yml` перенастроен: `app` build `context: ./docker` + `dockerfile: php/Dockerfile`, монтирования `./api:/app`, nginx-конфиг `./docker/nginx/default.conf`.
- **Проверено:** `make api-migrate/api-fixtures/api-test` (10 tests OK), `/health` 200, вход через фронт-прокси 5174→nginx→Slim → реальный токен, `GET /users` 200. Стек полностью работает из новой раскладки.
- **Осталось по #13:** prod-Dockerfile'ы (multi-stage, `docker/production/*`), `docker-compose-production.yml` (Traefik `admin`/`api2.rebit-pro.ru`, versioned secrets, миграции-gate), `.github/workflows/makefile.yml` + `deploy/swarm-publish-runtime.sh`, бэкап/алерты, uuid-перекладка `auth_users`.

---

## Решения по ходу (фиксация)

- **DevOps-план пересмотрен (по требованию заказчика):** CI/CD — **GitHub Actions** (образец Rabbit P2P `makefile.yml`), **не** Jenkins; registry — **GitHub Container Registry** `ghcr.io/rebit-pro`; структура — **монорепо Елисеева** (бэкенд из корня → `api/`); домены **раздельные** — `admin.rebit-pro.ru` (админка) и `api2.rebit-pro.ru` (API), CORS-whitelist; репозиторий `git@github.com:rebit-pro/admin-rebit-core.git`. Детали — [`04-devops.md`](04-devops.md) §1a, §6, §14.

- **uuid-перекладка `auth_users` (bigint→uuid) — ОТЛОЖЕНА на пред-прод (#13).** Прод-окружения нет; `03-database.md §12` допускает «до первого прод-деплоя». Новые таблицы (`Access`, `Highload`) — сразу uuid; `auth_users` пока `bigint`. Снимает риск перекладки PK в середине MVP.
- **Auth не переписываем целиком в 4 слоя** (консилиум, YAGNI). Account и новый код — в слоях `Application/Presentation` с портами; легаси `AuthService`/`AuthRepository` сосуществуют и дорастают органически.
- **Пароли:** новые — argon2id (`PasswordHasher` порт); проверка `password_verify` совместима со старыми bcrypt-хэшами фикстур.
- **Смена пароля:** отозвать ВСЕ токены → выдать новый (консилиум).
- **Смена email в MVP:** с проверкой текущего пароля + уникальности; шаг подтверждения по коду (+ SmartCaptcha) — hardening на потом.

---

## Отложено в рамках MVP-1 (напоминание)

- Bearer/RBAC/rate-limit middleware — auth-middleware вводится в #10, RBAC — в #11, rate-limit — в security-инкременте.
- Перенос `JsonResponder` → `Shared\Http` — вместе с полным переездом Auth-презентации.
- `composer` в dev-образе (сейчас тесты через `php vendor/bin/phpunit`).

## Следующий шаг
**#13 (последняя задача MVP-1):** DevOps single-node прод по обновлённому плану ([`04-devops.md`](04-devops.md)) —
0) **реструктуризация в монорепо**: бэкенд из корня → `api/` (структура Елисеева), git-репо `git@github.com:rebit-pro/admin-rebit-core.git`;
1) multi-stage prod-Dockerfile'ы (API + Berry build→nginx), `docker-compose-production.yml` (Traefik: `admin.rebit-pro.ru` + `api2.rebit-pro.ru`, CORS-whitelist, миграции-gate, versioned secrets);
2) **GitHub Actions** `.github/workflows/makefile.yml` (образец P2P) + `deploy/swarm-publish-runtime.sh`, registry **ghcr.io/rebit-pro**;
3) бэкап + базовые алерты; uuid-перекладка `auth_users` перед первым деплоем.

---

## 2026-07-11 Консилиум по 04-devops.md перед задачей #13 (прод-деплой через GitHub Actions)

**Проведён консилиум** (4 эксперта → синтезатор → скептик, вето нет): решение — **not_ready**, план требует ревизии до начала работ. Полный отчёт: [`plans/prod-deploy/consilium-2026-07-11.md`](plans/prod-deploy/consilium-2026-07-11.md).

Ключевое:
- **Brownfield, не greenfield:** на 37.143.8.221 уже живёт прод P2P (`app.rebit-pro.ru` → 200, `api.rebit-pro.ru` → 401) за общим Traefik **v2.11** (план пинит 3.x) + deploy-юзер третьего проекта. §13 шаги 4–5 (site.yml, деплой шлюза) по живому хосту гонять нельзя — риск уронить соседний прод. Только `deploy.labels` своего стека.
- **Внутренние противоречия плана:** ветка `main` (факт: `master`); `context: api` (§6) vs `docker/` в корне (§1a); два префикса имён образов; три имени стека; две механики деплоя в §7; три версии VITE-контракта; §15-gate миграций vs one-shot в стеке.
- **SSH-канал не подтверждён:** из среды аудита баннер с 37.143.8.221 не приходит; доступность с GitHub-раннеров неизвестна → канарейка до написания deploy-джобы (развилка hosted/self-hosted runner).
- **План устарел местами:** symfony/console уже внедрён (bin/app.php), pdo_pgsql в dev-образе есть; фронтового prod-Dockerfile в репо нет (то, что принимали за факт, — эталон P2P).
- **До прод-релиза:** SmartCaptcha вместо GeeTest (152-ФЗ, код грузит static.geetest.com), `*_FILE`-чтение секретов в api (сейчас только `$_ENV`), бэкап-пререквизиты (S3, restore-runbook), `.dockerignore`, cs-check/psalm-скрипты.
- DNS обоих доменов уже указывает на боевой IP; 80/443 открыты, ACME возможен.

**Следующий шаг:** ревизия 04-devops.md по blocker-пакету + параллельно SSH-канарейка и инвентаризация сервера; затем repo-артефакты (см. порядок в отчёте консилиума).

## 2026-07-11 Ревизия 04-devops.md по итогам консилиума (blocker-пакет внесён)

План переведён из greenfield в **brownfield** и очищен от внутренних противоречий (диф ~140/143 строк):
- §0/§1/§4/§5: принцип brownfield, честная топология (один хост manager=db, общий Traefik v2.11 вне объёма, сосед P2P), §4 переписан на «аудит + точечный gap-fix» с перечнем угроз site.yml, §5 — «шлюз не деплоим, только labels» + факт сломанного catchall-редиректа.
- Пакет решений зафиксирован в §14: №9 стек `admin` + префикс образов `admin-rebit-core-*`; №10 ветка `main` (переименование master → main на GitHub-side шаге); §14.3 токен-модель GITHUB_TOKEN + read-PAT; §14.5 бэкапы — Yandex Object Storage; §14.7 один хост.
- §6 переписан: context `.`, services: postgres, security-scan-джоба в графе, build-args VITE_* + validate, SSH_KNOWN_HOSTS-пин, environment production только на deploy, latest-stable шаг; пререквизит cs-check/psalm отмечен.
- §7: одна механика деплоя (P2P scp-releases), **migrate-gate до stack deploy** (+advisory lock, expand/contract, bootstrap первого деплоя), cron без schedule:run (отдельные сервисы, MVP: auth:purge-expired-tokens — команду написать), штатный `make rollback`.
- §8: полный инвентарь секретов, /srv от deploy 0700/0600, «*_FILE — пункт работ, не факт»; §9: /health → readiness, ротация логов через compose logging; §10: конкретика бэкапов + restore-runbook до релиза; §11: харднинг-роли = отдельная разработка с обкаткой на VM, гигиена ключей.
- §13 пересобран: шаг 0 — блокер-пакет (SSH-канарейка с GH-раннера, инвентаризация сервера, tenancy-решение заказчика), выполненное отмечено; §16: капча «решено, но НЕ сделано» (код на GeeTest — предусловие релиза), Sentry SaaS-без-ПДн, tenancy — ОТКРЫТО.

**Следующий шаг:** шаг 0 из §13 — SSH-канарейка + инвентаризация 37.143.8.221 (нужна среда с рабочим SSH — из текущей баннер не приходит) + письменное tenancy-решение заказчика. Параллельно можно начинать шаги 1–3 (prod-Dockerfile'ы, приложение к проду, dev-набор/Makefile).

## 2026-07-11 Шаги 1–3 плана §13 (04-devops.md) выполнены

**Шаг 1 — prod-Dockerfile'ы API + отказ от SQLite:**
- `docker/production/{php-fpm,php-cli,nginx}` — двухстадийные, context `.` (корень), `COPY api/ ./`, non-root (www-data/app), opcache `validate_timestamps=0`, `expose_php=Off`, php-fpm `/ping`; php-cli — POSIX `wait-for` (в alpine нет bash); nginx — envsubst-шаблон (`PHP_FPM_HOST`, `CORS_ALLOWED_ORIGIN`), CORS-whitelist + preflight 204, security-заголовки. ✅ nginx-образ собран, `nginx -t` чист. ⚠️ php-образы собрать локально нельзя (Docker Hub и зеркало timeweb из среды недоступны) — проверка `make try-build` при первом CI-прогоне.
- SQLite упразднён: ветка в `di/database.php`, driver-развилка в `Migrator`, `migrations/sqlite/`, sqlite-дефолт `.env.example`.

**Шаг 2 — приложение к проду:**
- `*_FILE`-чтение: `Shared/Config/EnvFileResolver` в bootstrap (приоритет `_FILE` над plain), юнит-тесты; **проверено вживую** — dev-стек переведён на `DB_PASSWORD_FILE=/run/secrets/db_password`, миграции/логин работают.
- `/health` → readiness (ленивый `SELECT 1` через контейнер): 200 при живой БД, **503 при лежащей** (проверено остановкой db); `/health/liveness` — статический.
- `auth:purge-expired-tokens` (+ чистка протухших regcodes) — прогнана: «Удалено: 2 токенов».
- **SmartCaptcha вместо GeeTest**: фронт (invisible-виджет, execute на submit, reset после ошибки, типы в env.d.ts, `VITE_SMARTCAPTCHA_CLIENT_KEY` везде), бэк (`CaptchaVerifier`-порт, `SmartCaptchaVerifier` fail-closed / `NullCaptchaVerifier` при пустом ключе, IP в validate, 422), прод-Dockerfile фронта переписан (node:24, nginx:1.29, `npm ci` строго, build-args по контракту §2.4). GeeTest в коде не осталось.

**Шаг 3 — dev-контур и тулинг:**
- compose: + `php-cli` (profile tools), + `mailer` (mailpit, 127.0.0.1:8025), + file-secrets (`docker/development/secrets/*`), порт БД → loopback.
- `.dockerignore` переписан (якорные пути: api/.env, api/var, **/vendor, node_modules, dist, docs, .github).
- Makefile: `REGISTRY/IMAGE_TAG/STACK_NAME=admin`, `api-cs-check`, `api-analyze` (psalm), `build/build-api/build-frontend/try-build/push` (префикс `admin-rebit-core-*`); compose-сборка переименована в `docker-build`.
- composer: `cs-check`/`cs-fix`/`psalm`, `check` расширен; `psalm.xml` (level 4). Psalm-долг разобран: 59 → 0 (авто `#[Override]` + реальные фиксы: stale-шейпы `AuthRepository` без `status`, nullable-возвраты в 3 Application-хендлерах → явный 404-throw).
- Prettier-долг перенесённого кода (24 ошибки в UsersPage/DashboardPage/vite.config — завалил бы CI) закрыт `lint:fix`.

**Verify:** php -l ✅ · cs-check 0 ✅ · psalm 0 ✅ · PHPUnit 13/13 ✅ · vue-tsc ✅ · eslint ✅ · /health 200/503 ✅ · login+token ✅ · purge ✅ · mailpit UI 200 ✅. Отложено с пометкой: traefik-dev и s3mock/backup в dev-compose (появятся с шагами 6–8), e2e-прогон (браузеры Playwright не ставились).

**Следующий шаг:** §13.4 GitHub-side setup (переименование ветки в main, protection, environment, secrets) и §13.0 (SSH-канарейка, инвентаризация сервера, tenancy) — требуют действий на GitHub/сервере.

## 2026-07-11 Шаги 7–9 (репозиторная часть): прод-стек, деплой-контур, CI/CD

**Шаг 7 — `docker-compose-production.yml` (стек `admin`) + `deploy/` + Makefile:**
- Стек: frontend/api/api-php-fpm (replicas 2, `start-first` + `failure_action: rollback`, healthchecks: wget /health/nginx, wget /health через цепочку nginx→fpm→БД, cgi-fcgi /ping), api-postgres (manager=db, dnsrr, `POSTGRES_PASSWORD_FILE`), cron-сервисы `api-purge-tokens` и `api-postgres-backup` (replicas 0, swarm-cronjob `0 * * * *`); logging json-file 10m×3 через якорь; limits+reservations (стартовые, до обмера); no published ports; configs/secrets — external versioned через `${*_NAME}`.
- Labels: https-роутеры admin/api2 + **парные http-роутеры с `redirect-to-https`** (мидлвар живого шлюза валиден, сломан только его catchall); имена certResolver/entryPoints — предварительные, TODO-инвентаризация.
- `deploy/swarm-publish-runtime.sh` — форк P2P под `deploy`: свои дефолты (`/srv/admin-rebit-core/swarm`, секреты admin_*), **fail при group/other-readable** секрет-файле; `deploy/backend.env.example`, `deploy/secrets/*.example`, `deploy/README.md` (закладка, релиз, bootstrap, откат); `.gitignore` — боевые секреты в `deploy/secrets/` неигнорируемы только как `*.example`.
- Makefile: `deploy` = scp в релиз `/srv/admin-rebit-core/site_N` → `.env` (versioned-имена) → login/pull → **`api-migrate-prod` (gate: `docker run --rm --network admin_default … migrate`, bind backend.env+секрет)** → symlink → `stack deploy`; `SKIP_MIGRATE=1` для bootstrap; `rollback` по релизу N; `deploy-clean` (KEEP_RELEASES=2 + image prune с warn-tolerance).
- Образ `api-postgres-backup` (перенос из slim): alpine + postgresql17-client + aws-cli, entrypoint `*_FILE→ENV`, `pg_dump | gzip -9 → aws s3 cp` (YOS), non-root, wait-for.

**Шаг 9 — `.github/workflows/`:**
- `makefile.yml` (CI/CD, **триггеры на master**): `api-checks` (setup-php 8.5, `services: postgres:17`, lint→cs-check→psalm→test→composer audit→gitleaks-бинарь), `frontend-checks` (lint, typecheck×2, **мок-e2e Playwright включены** — гейтят деплой, npm audit critical), `build-api` (4 образа, context `.`, **GITHUB_TOKEN + packages:write**, per-image gha-cache, provenance:false), `build-frontend` (validate непустых `vars.VITE_*` → build-args), `security-scan` (Trivy HIGH/CRITICAL по всем 5 образам + assert «нет /app/.env, expose_php=Off»), `deploy` (environment production, concurrency, пиненый `SSH_KNOWN_HOSTS`, публикация versioned-объектов по ssh + имена в `$GITHUB_ENV`, `make deploy`, smoke admin/api2 + проверка http→https, **тег `latest-stable`** через imagetools после smoke).
- `ssh-canary.yml` (workflow_dispatch) — проба SSH с hosted-раннера + состояние Swarm (шаг 0 §13).

**Verify:** YAML обоих workflow распаршен; `docker compose -f docker-compose-production.yml config` валиден; `bash -n`/`sh -n` всех скриптов чисто; `make -n deploy/rollback/api-migrate-prod` рендерятся корректно; api-lint/api-test — зелёные.

**Осталось вне репо (нужны доступы):** GitHub-side (secrets: HOST/PORT/DEPLOY_USER/SSH_PRIVATE_KEY/SSH_KNOWN_HOSTS/REGISTRY/GHCR_PULL_USER+TOKEN/BACKUP_AWS_ACCESS_KEY_ID; vars: VITE_API_URL/VITE_SMARTCAPTCHA_CLIENT_KEY/BACKUP_S3_BUCKET; environment production + reviewers; branch protection), CI-ключ в authorized_keys deploy → прогон канарейки, инвентаризация сервера (финализация имён labels + limits), tenancy-решение, S3-бакет + lifecycle, ключи SmartCaptcha, первый прогон CI (соберёт php-образы — задача #4), bootstrap-деплой + учения rollback/restore.

## 2026-07-12 Инвентаризация сервера выполнена; бэкапы отложены; SSH-ключи CI готовы

**SSH заработал** (`ssh rebit-pro`, root; ранее баннер не приходил — причина не установлена, канал с GitHub-раннера подтвердит канарейка). Проведена **инвентаризация §4 шаг 0** (итоги — §16.7 плана):
- Узел один: `p903785.kvmvps`, manager, label `db=db` есть; **1 CPU / 2 ГБ RAM** (available ~938 МБ), диск 59 ГБ (36 свободно), docker engine 29.1.1.
- Стеки-соседи: `site` (P2P, 13 сервисов, 2/2 реплики), `solcoing` (mariadb+web), `traefik` (v2.11). Сеть `traefik-public` существует.
- **Labels подтверждены дословно**: entryPoints `http`/`https`, certResolver `letsEncrypt`, middlewares `secure-headers` + `redirect-to-https` (определены на самом traefik-сервисе, доступны из чужих стеков — P2P так и живёт); catchall-редирект сломан (HostRegexp v2) — наши парные http-роутеры обязательны. Правок в labels не потребовалось.
- **swarm-cronjob отсутствует** → добавлен `deploy/swarm-cronjob-stack.yml` (разовый разворот до первого деплоя, лимит 32М).
- `daemon.json` — только registry-mirror timeweb (ротация логов у нас через `logging:` в compose — верно).
- В `authorized_keys` `deploy` — единственный чужой ключ `tarasov.ae@nikamed-it.ru` (гигиена §11; наш CI-ключ дописывать, не заменять).

**По ёмкости пересобран прод-стек**: replicas 2→**1** у frontend/api/api-php-fpm, CPU-reservations убраны, memory-reservations минимальны (16/16/64/128М), limits ужаты (суммарно ~576М) — прежние reservations (1.15 CPU) физически не заскедулились бы на 1 CPU.

**Бэкапы отложены решением заказчика** (§10, §16.6): сервис убран из compose, сборка образа — из CI, `BACKUP_*` — из Makefile/workflow/secrets; Dockerfile и publish-опция остаются на будущее. Риск зафиксирован: до ввода RPO не гарантирован (отказ диска = потеря данных админки).

**Ключи CI сгенерированы**: `~/.ssh/admin-rebit-core-ci{,.pub}` (ed25519) и пин `~/.ssh/admin-rebit-core-known_hosts` (ed25519 SHA256:bwtF…9wqU) — значения для GitHub Secrets готовы, чек-лист обновлён.

**Verify:** workflow YAML ok, prod-compose `config` валиден (с новыми replicas/resources, без backup-сервиса), publish.sh `bash -n` ok, `make -n deploy` без BACKUP-ссылок.

**Следующее:** внести Secrets/Variables в GitHub + дописать CI-ключ на сервер (C1) → push ветки, PR, канарейка → капча + закладка секретов + swarm-cronjob-стек → merge и полный прогон.

## 2026-07-12 CI-ключ установлен на сервер (C1 закрыт)

Секреты/vars внесены в GitHub пользователем. CI-ключ дописан в `/home/deploy/.ssh/authorized_keys` (строка `restrict <ed25519> ci@admin-rebit-core`; существующий ключ tarasov не тронут; права 700/600, владелец deploy). Проверено по самому CI-ключу: вход `deploy@37.143.8.221` ок, `docker info` доступен (deploy в группе `docker`, swarm=active/manager), **scp под `restrict` работает** (важно для `make deploy`). Осталось из блокеров: канарейка с GitHub-раннера (workflow_dispatch после push ветки), капча + закладка секретов на сервер + разворот swarm-cronjob-стека, tenancy-решение.
