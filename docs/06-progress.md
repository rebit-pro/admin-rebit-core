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
