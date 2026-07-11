# Карта модулей — ReBit Admin Core

> Карта модулей (bounded contexts), их слоёв, портов, публичных контрактов, зависимостей и очередности реализации.
> Синтезирует [`01-scenarios.md`](01-scenarios.md), [`02-domain.md`](02-domain.md), [`03-database.md`](03-database.md), [`04-devops.md`](04-devops.md) **с учётом итогов консилиума** (см. §9).
> Архитектура — Slim в стиле Д. Елисеева + DDD/гексагон. Namespace-корень `App\`. PHP 8.5, PDO, PostgreSQL 17.

## 1. Как читать

Модуль = ограниченный контекст в `src/<Module>/` с четырьмя слоями `Domain / Application / Infrastructure / Presentation` (§3 [`02-domain.md`](02-domain.md)). Правило зависимостей: `Presentation → Application → Domain`; `Infrastructure` реализует порты; `Domain` не зависит ни от чего. Между модулями — **только** через публичные Application-порты и доменные события; прямого доступа к чужим репозиториям/таблицам нет.

**Тип подсистемы** (влияет на глубину домена): `Core` — ключевая ценность, богатый домен; `Supporting` — поддерживает Core; `Generic` — обобщённый механизм/«фреймворк», домен минимален.

## 2. Реестр модулей

| Модуль | Namespace | Тип | Ответственность | Владеет таблицами |
|---|---|---|---|---|
| **Auth** | `App\Auth` | Core | идентичность, аутентификация, токены, смена логина/email/пароля, профиль | `auth_users`, `auth_access_tokens`, `auth_confirmations`, `auth_login_attempts`, `user_consents` |
| **Access** | `App\Access` | Supporting | роли, права, решение о доступе (RBAC), инвариант «≥1 owner» | `access_permissions`, `access_roles`, `access_role_permissions`, (`access_user_roles` — MVP-2) |
| **Highload** | `App\Highload` | **Generic** | конструктор сущностей: блоки, поля, элементы; хранит+валидирует по метаданным | `hl_blocks`, `hl_fields`, `hl_elements` |
| **Catalog** *(MVP-2+)* | `App\Catalog` | Core | доменная логика каталога/офферов поверх Highload (цены, скидки) — только когда появится логика | — (использует `hl_*`) |
| **Audit** | `App\Audit` | Supporting | журнал значимых действий, security-события | `audit_log` (+ `security_alerts` — MVP-2) |
| **Shared** | `App\Shared` | Platform | шина событий, UnitOfWork, Http-ядро, Console-ядро, Security, `Id`/`Clock`, Validation | `cron_job_runs` (логи запусков) |

> Текущий код (факт): `App\Auth\*` (плоско: `AuthService`, `AuthRepository`, `TokenFactory`, `Identity`, `AuthException`, `Fixture`), `App\Http\*` (`Action\*`, `JsonResponder`), `App\Database\Migrator`. Целевая перекладка: `App\Http` → `App\Shared\Http`; `App\Database` → `App\Shared\Persistence`; `App\Auth` → 4 слоя. **Рабочий Auth не переписываем ради чистоты — дорастает органически при добавлении соседних модулей (консилиум, YAGNI).**

## 3. Матрица зависимостей

Строка зависит от столбца (через порт `P` или события `E`). `—` нет зависимости.

| ↓ зависит \ от → | Auth | Access | Highload | Audit | Shared |
|---|---|---|---|---|---|
| **Auth** | — | `P` AccessQuery (проверка прав на управление юзерами) | — | `E` (публикует события) | `P` EventPublisher, UoW, Hasher, TokenGenerator, Id, Clock |
| **Access** | `P` UserLookup (по id) | — | — | `E` | `P` EventPublisher, UoW, Id |
| **Highload** | — | — | — | `E` | `P` EventPublisher, UoW, Id, Clock |
| **Catalog** (MVP-2) | — | — | `P` Highload Application (CRUD элементов) | `E` | `P` EventPublisher, UoW |
| **Audit** | — | — | — | — | `P` подписка на шину, Clock |
| **Shared** | — | — | — | — | — |

Ключевые правила (консилиум):
- **Auth ↔ Access:** Auth хранит только `role`-код; вся семантика прав и инвариант «≥1 owner» — в Access. Кросс-контекст — через `App\Access\Application\Port\AccessQuery` (проверка права) и `RoleAssignmentService` (назначение роли), не через общую таблицу.
- **Audit** ни от кого не зависит по данным — только подписывается на события через шину `Shared`.
- **Highload** не зависит от доменных модулей: он Generic. Доменная логика каталога — в `Catalog` (MVP-2), не в Highload.

## 4. Модули детально

### 4.1. `App\Auth` (Core)
- **Агрегаты:** `User` (богатый: приватный конструктор, фабрики `register`; поведение `changePassword(newHashed)`, `changeLogin`, `changeEmail`, `block/unblock`), `AccessToken`, сущность `Confirmation`.
- **VO:** `UserId`(uuid v7), `Email`, `Login`, `Phone`, `HashedPassword`, `Role`(код), `UserStatus`, `TokenHash`.
- **Driven-порты (в Domain):** `UserRepository`, `AccessTokenRepository`, `ConfirmationRepository`.
- **Технические порты (в Application):** `PasswordHasher`, `TokenGenerator`, `ConfirmationSender`.
- **Публичная поверхность (Application):** `UserLookup` (для Access — получить пользователя/роль по id); команды `Login/Logout/ChangePassword/ChangeLogin/ChangeEmail`; запрос `CurrentUser`.
- **События:** `UserRegistered`, `UserLoggedIn`, `UserLoginFailed`, `UserLoggedOut`, `UserPasswordChanged`, `UserLoginChanged`, `UserEmailChanged`, `UserBlocked`, `UserUnblocked`, `UserDeleted`.
- **Правила безопасности:** сверка текущего пароля — в Handler через `PasswordHasher`; при смене пароля — **отозвать все токены → выдать новый**; anti-enumeration; лимит попыток кода (СЦ-6.9).

### 4.2. `App\Access` (Supporting)
- **Сущности:** `Role`(code, permissions), `Permission`(code). Каталог прав — **код = единственный источник истины**; таблицы `access_*` — read-модель из сидера.
- **Driven-порты:** `RoleRepository`, `PermissionCatalog`.
- **Публичная поверхность (Application):** `AccessQuery::isAllowed(identity, permission, ?resource)`, `RoleAssignmentService::assign(userId, roleCode)`.
- **Инварианты:** `access.manage` — только owner; **всегда ≥1 активный owner** (проверка атомарна: advisory-lock/`FOR UPDATE`); **IDOR/owner-check неотключаем** даже при хардкод-RBAC в MVP-1.
- **События:** `RoleAssignedToUser`, `RolePermissionsChanged`.
- **MVP-1:** маппинг «роль→права» допустим на хардкоде без таблиц `access_*`.

### 4.3. `App\Highload` (Generic)
- **Агрегаты:** `Block`(code, поля как часть агрегата), `Element`(системные атрибуты + `data`). `Field` — сущность в агрегате `Block`.
- **VO:** `BlockCode`, `FieldCode`, `FieldType`(enum), `FieldSettings`(типозависимый), `ElementId`.
- **Доменный сервис:** `ElementValidator` (валидирует `data` по определению полей блока **до записи**).
- **Driven-порты:** `BlockRepository`, `ElementRepository`.
- **Публичная поверхность (Application):** CRUD блоков/полей/элементов; листинг элементов (keyset-пагинация, фильтр по `show_in_filter`-полям).
- **События:** `BlockCreated/Updated/Deleted`, `FieldCreated/Updated/Deleted`, `ElementCreated/Updated/Deleted`.
- **Инварианты (консилиум):** значения — по контракту хранения ([`03-database.md`](03-database.md) §5.3: `decimal` — строка, `integer/boolean` — нативный JSON); **reference-целостность RESTRICT** (запрет удаления цели при ссылках); индексируются только предобъявленные фильтр-поля (без runtime-DDL на произвольное поле).
- **Ограничение MVP:** типы `string|text|integer|decimal|boolean|datetime|enum|reference`; `file`/`multiple` — MVP-2 (при отложенном `file` сид каталога без поля `image`).
- **Каталог/офферы** — сидированные блоки `catalog`/`offers` (данные, не код); связь оффер→товар — reference-поле.

### 4.4. `App\Audit` (Supporting)
- **Сущность:** `AuditEntry` (append-only). **Наполняется `in-transaction`-подписчиком на события** — бизнес-код не пишет аудит напрямую.
- **Driven-порт:** `AuditRepository`.
- **Redaction (консилиум):** запрещено писать `password_hash`/токены/`code_hash`/сырые пароли — только whitelist.
- **Security-события** в MVP — `audit_log.action='security.*'`; отдельная `security_alerts` + алертинг — MVP-2.

### 4.5. `App\Shared` (Platform) — публичная поверхность
Ядро, от которого зависят все модули. Публичные порты/сервисы:
- **`EventPublisher`** (порт) + `EventBus` (реализация) — синхронная in-process шина; подписчики двух фаз: `in-transaction` (аудит) и `after-commit` (кэш/интеграции). Outbox — MVP-2.
- **`UnitOfWork` / `TransactionalDecorator`** — задаёт транзакционную границу вокруг Handler+`in-transaction`-подписчиков (без него атомарность аудита декларативна — консилиум).
- **`Id`** — генерация/парсинг **UUID v7 в приложении** (`symfony/uid`; в PG17 нет `uuidv7()`), `Clock` — инъекция времени.
- **Http-ядро** (`App\Shared\Http`): middleware (аутентификация Bearer, RBAC, rate-limit, security-заголовки, CORS-whitelist-резерв), error-handlers (доменные → 409, валидация → 422), `JsonResponder` (`{data}`/`{error}` — факт).
- **Console-ядро** (`App\Shared\Console`): `symfony/console`-kernel; cron-команды (`auth:purge-expired-tokens`, `events:*` при вводе outbox, `hl:gc-orphan-references` как safety-net).
- **Validation** — строгая денормализация DTO (анти-mass-assignment).
- **Persistence** (`App\Shared\Persistence`): PDO-обвязка, `Migrator` (факт), базовый PDO-репозиторий.

## 5. Сквозные контракты

- **Контракт события:** `{ eventId(uuid), name, occurredAt, aggregateType, aggregateId, payload }` (с redaction).
- **Транзакционная граница:** Handler исполняется внутри `UnitOfWork`; изменение агрегата + запись аудита — атомарны; `after-commit`-эффекты — вне транзакции.
- **Контракт ответа/ошибок:** `{data}` / `{error:{message, errors}}`; коды `200/201/204/400/401/403/404/409/422/429/500`.
- **Идентификаторы:** uuid v7 (агрегаты) генерит приложение; лог-таблицы — `bigint IDENTITY`.

## 6. Граница MVP (консилиум)

**MVP-1 — тонкий вертикальный срез до прода (~3–4 недели бэк+DevOps):**
- `Auth` (факт) + `Account` (смена пароля/логина/email) + `Users` CRUD.
- `Access` на **хардкод-проверках роли** (owner/admin/user) + обязательный IDOR/owner-check (без таблиц `access_*`).
- Синхронная шина событий + `Audit` (in-transaction).
- Один прод-деплой: single-node Swarm/compose + Traefik(TLS) + миграции-gate + бэкап + алертинг.
- Отвязка Berry-фронта от моков по этим сценариям.

**MVP-2:** `Highload` (блоки/поля/элементы, типы без `file/multiple`) + каталог/офферы (сид) + RBAC-таблицы + `security_alerts`/алертинг.
**MVP-3:** `event_outbox` + async-доставка (при появлении интеграций), `file`/`multiple`, `hl:reindex`, восстановление пароля, вход по телефону/OTP; HA (PITR/реплика), Redis, observability-стек.

**Отложено (YAGNI, консилиум):** `event_outbox`/`event_subscriptions`, `cron_jobs`/`schedule:run` (только swarm-cronjob), `access_user_roles`, `cron_locks`, партиционирование, миграция PK на greenfield-таблицах вместо перекладки (перекладку `auth_users`→uuid делаем до первого прод-деплоя).

## 7. Очерёдность реализации (по модулям)

1. **`Shared` (минимум):** `Id`(uuid v7), `Clock`, `UnitOfWork`, `EventBus`(sync), Http-ядро (auth/RBAC/rate-limit middleware, error-handlers), Console-ядро. Перевод `App\Http`/`App\Database` под `Shared`.
2. **`Auth` → 4 слоя:** переложить существующий код в `Domain/Application/Infrastructure/Presentation`, довести до Command/Handler; добавить Account-команды; uuid-перекладка `auth_users` (до прода).
3. **`Access` (хардкод MVP-1):** `AccessQuery`, Policy-middleware, IDOR; Users CRUD (в Auth) с проверками Access.
4. **`Audit`:** in-transaction подписчик + redaction.
5. **DevOps MVP-1** (см. [`04-devops.md`](04-devops.md) §13): pdo_pgsql→Postgres, prod-стек, миграции-gate, бэкап, алертинг.
6. **`Highload` (MVP-2):** блоки/поля/элементы + контракт хранения + reference-RESTRICT; сид `catalog`/`offers`.
7. **Access-таблицы, security_alerts, outbox** — по мере надобности (MVP-2/3).

## 8. Definition of Done (на модуль)

- Слои разложены по правилу зависимостей; `Domain` без Slim/PDO/HTTP.
- Driven-порты в `Domain`, технические — в `Application`; адаптеры в `Infrastructure` подключены через PHP-DI.
- Каждый эндпоинт: валидация входа + единый формат ошибок; права проверяются (RBAC + IDOR).
- Инварианты агрегатов покрыты **юнит-тестами домена** (уровень L); ключевые сценарии — feature/e2e (Playwright/Cucumber) на Postgres.
- События публикуются через `EventPublisher`; аудит атомарен; redaction соблюдён.
- Схема — миграциями (Postgres), индексы под запросы; секреты в env/secrets; `APP_DEBUG=false`.

## 9. Статус по консилиуму

Документы прошли консилиум (5 экспертов + синтез + скептик) — общий вердикт **YELLOW**, фундамент принят. **7 блокеров карты модулей исправлены** в исходных документах и учтены здесь:
1. Порты: driven `*Repository` — в Domain, технические — в Application ([`02-domain.md`](02-domain.md) §3.1).
2. Границы Auth↔Access + неотключаемый IDOR ([`02-domain.md`](02-domain.md) §5).
3. Событийная модель: sync-шина (audit in-tx / cache after-commit) + UoW; outbox → MVP-2 ([`02-domain.md`](02-domain.md) §8, [`01-scenarios.md`](01-scenarios.md) §4).
4. Highload = Generic, сужение типов, без runtime-DDL на произвольное поле, сид без `image(file)` ([`02-domain.md`](02-domain.md) §6).
5. Контракт хранения: `decimal`=строка, `integer/boolean`=нативный JSON, partial per-block индексы ([`03-database.md`](03-database.md) §5.3).
6. Reference-целостность = RESTRICT (без отдельного индекса и без GC-как-политики) ([`03-database.md`](03-database.md) §5.3, [`02-domain.md`](02-domain.md) §6).
7. Планировщик = только swarm-cronjob; `cron_jobs`/`schedule:run`/`cron_locks` не заводим, FK снят ([`03-database.md`](03-database.md) §7).

**Prod must-fix** (не блокеры карты, обязательны до релиза) — в [`04-devops.md`](04-devops.md) §15 и [`01-scenarios.md`](01-scenarios.md) §6.9: миграции-gate/expand-contract, HA/SLA + алертинг в core, раздельные домены (`admin.rebit-pro.ru` / `api2.rebit-pro.ru`) + CORS-whitelist, сетевой харднинг, 152-ФЗ (согласия/сроки/GeeTest/шифрование/redaction/Sentry-scrubbing), брутфорс кода.
**Оставшиеся вопросы — зафиксированы рекомендациями** ([`04-devops.md`](04-devops.md) §16, [`01-scenarios.md`](01-scenarios.md) §6.9): SLA данных — RPO ≤ 1 ч / RTO ≤ 2 ч в MVP (PITR+реплика первым апгрейдом); observability — минимум (health+Monolog+Sentry+базовые алерты+ротация логов) в core, полный стек (Loki/Prometheus) после MVP; капча — Yandex SmartCaptcha вместо GeeTest.
