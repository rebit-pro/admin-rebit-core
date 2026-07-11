# Проектирование базы данных — ReBit Admin Core

> Схема БД под сценарии ([`01-scenarios.md`](01-scenarios.md)) и доменную модель ([`02-domain.md`](02-domain.md)).
> СУБД: **PostgreSQL 17** (прод/дев), **SQLite** (локальный fallback — факт: `DB_CONNECTION=sqlite` по умолчанию, пока в PHP-образе нет `pdo_pgsql`).
> Миграции: самописный `App\Database\Migrator` (SQL-файлы, две ветки — `migrations/` для Postgres и `migrations/sqlite/`). Статус: проектный документ.

## 1. Соглашения

- **Именование:** `snake_case`, таблицы во множественном числе, префикс по контексту: `auth_*`, `access_*`, `hl_*`, `event_*`, `cron_*`, `audit_*`.
- **Первичные ключи:** `uuid` (UUID v7 — сортируемость по времени) для доменных агрегатов; для «толстых» справочных/лог-таблиц допустим `bigint GENERATED ALWAYS AS IDENTITY`. Ниже используем UUID для `users`, `blocks`, `elements`; bigint — для лог/outbox/audit (объёмные, append-only).
- **Временные метки:** `created_at`, `updated_at` — `timestamptz` (pg) / `TEXT` ISO-8601 (sqlite, как в текущем `Migrator`).
- **Деньги / точные числа:** `numeric(precision, scale)` — **не** float (правило [[rebit-backend]]). В JSONB decimal хранится строкой и валидируется.
- **Внешние ключи:** явные `FK` там, где связь внутри одного контекста и уместен каскад. Связи HL-элементов по `reference` — **без FK** (целостность на уровне приложения, как в Bitrix HL-блоках).
- **Индексы:** под фактические запросы (фильтры/сортировки из сценариев); для JSONB — GIN и выражения.
- **Диалекты:** каждая таблица описана для Postgres; отличия sqlite отмечены (нет `timestamptz`/`jsonb`/частичных индексов/`GENERATED IDENTITY` — заменяются на `TEXT`/`TEXT(json)`/`INTEGER PK AUTOINCREMENT`).

**Уже существующие таблицы (факт):** `schema_migrations`, `auth_users`, `auth_registration_codes`, `auth_access_tokens`. Ниже они расширяются, а не пересоздаются.

---

## 2. Ключевое решение — хранение значений HL-элементов

Рассмотрены три подхода:

| Подход | Плюсы | Минусы |
|---|---|---|
| **A. EAV** (таблица значений «элемент-поле-значение») | гибкость, без DDL | «join-ад», слабая типизация, сложные фильтры/сортировки |
| **B. Таблица на блок** (как Bitrix: `CREATE TABLE hl_<code>`) | типизированные колонки, индексы | runtime-DDL, динамический маппинг, миграции значений при смене полей |
| **C. Гибрид: системные колонки + `JSONB`** ✅ | без runtime-DDL, гибкие поля, GIN/expression-индексы, нативно для PG17 | фильтрация по значению чуть сложнее, чем по колонке; sqlite — только json1 |

**Выбрано C (по умолчанию):** одна таблица `hl_elements` с системными колонками и `data jsonb`. Пользовательские поля валидируются приложением по `hl_fields`, значения — в `data`. Для часто фильтруемых полей — **expression-индексы** по путям JSONB. Это «современно» (PG17 JSONB), консистентно с текущим PDO-стеком (без ORM/runtime-DDL) и достаточно для каталога/офферов.

> При росте требований к аналитике конкретный блок можно «материализовать» в подход B без изменения API. Решение зафиксировано как открытый вопрос №1 в [`01-scenarios.md`](01-scenarios.md).

---

## 3. Контекст Auth

### 3.1. `auth_users` (расширение существующей)
Факт (уже есть): `id, email UNIQUE, password_hash, name, role DEFAULT 'admin', created_at, updated_at` + добавленные `login (UNIQUE), phone, address`.
Целевые изменения:

```sql
-- Postgres
CREATE TABLE auth_users (
    id            uuid PRIMARY KEY,                 -- было: bigint/serial → мигрируем на uuid v7
    email         varchar(255) NOT NULL,
    login         varchar(32)  NOT NULL,
    phone         varchar(20),                       -- E.164, задел под вход по телефону (не в MVP)
    password_hash varchar(255) NOT NULL,             -- argon2id/bcrypt
    name          varchar(255) NOT NULL DEFAULT '',
    address       text,
    role          varchar(32)  NOT NULL DEFAULT 'user',  -- FK-семантика на access_roles.code
    status        varchar(16)  NOT NULL DEFAULT 'active', -- active|blocked
    failed_login_count smallint NOT NULL DEFAULT 0,   -- анти-brute-force (СЦ-6.2)
    locked_until  timestamptz,                        -- временная блокировка
    last_login_at timestamptz,
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX ux_auth_users_email ON auth_users (lower(email));
CREATE UNIQUE INDEX ux_auth_users_login ON auth_users (lower(login));
CREATE UNIQUE INDEX ux_auth_users_phone ON auth_users (phone) WHERE phone IS NOT NULL;
CREATE INDEX ix_auth_users_role   ON auth_users (role);
CREATE INDEX ix_auth_users_status ON auth_users (status);
```
Примечания: уникальность email/login — регистронезависимая. `role` по умолчанию меняем с `'admin'` на `'user'` (безопаснее). Пароль/токены — только хэши.

### 3.2. `auth_access_tokens` (факт, уточняем)
```sql
CREATE TABLE auth_access_tokens (
    token_hash  varchar(64) PRIMARY KEY,             -- sha256(hex) от opaque-токена (факт: TokenFactory)
    user_id     uuid NOT NULL REFERENCES auth_users(id) ON DELETE CASCADE,
    expires_at  timestamptz NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now(),
    last_used_at timestamptz,
    user_agent  varchar(255),
    ip          inet
);
CREATE INDEX ix_tokens_user    ON auth_access_tokens (user_id);
CREATE INDEX ix_tokens_expires ON auth_access_tokens (expires_at);
```
Чистится задачей `auth:purge-expired-tokens` (СЦ-5.3). Отзыв при logout/смене пароля — `DELETE by user_id`.

### 3.3. `auth_confirmations` (обобщение `auth_registration_codes`)
Единый механизм подтверждений: регистрация, смена email, сброс пароля.
```sql
CREATE TABLE auth_confirmations (
    id            uuid PRIMARY KEY,
    purpose       varchar(32) NOT NULL,   -- registration | email_change | password_reset
    user_id       uuid REFERENCES auth_users(id) ON DELETE CASCADE, -- NULL для регистрации
    channel       varchar(255) NOT NULL,  -- email (в будущем phone)
    payload       jsonb NOT NULL DEFAULT '{}', -- напр. новый email/имя/хэш пароля до подтверждения
    code_hash     varchar(64) NOT NULL,   -- хэш кода, не сам код
    expires_at    timestamptz NOT NULL,
    resend_available_at timestamptz,
    consumed_at   timestamptz,
    created_at    timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ix_confirmations_channel ON auth_confirmations (purpose, channel);
CREATE INDEX ix_confirmations_expires ON auth_confirmations (expires_at);
```
> Существующая `auth_registration_codes` мигрирует в этот вид (или остаётся как частный случай `purpose='registration'`). Код в MVP — заглушка (факт: `123456`), но хранится как хэш.

### 3.4. `auth_login_attempts` (rate-limit / brute-force, СЦ-6.1/6.2)
```sql
CREATE TABLE auth_login_attempts (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email       varchar(255),
    ip          inet NOT NULL,
    successful  boolean NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ix_login_attempts_email_time ON auth_login_attempts (email, created_at);
CREATE INDEX ix_login_attempts_ip_time    ON auth_login_attempts (ip, created_at);
```
> При наличии Redis счётчики лимитов уходят туда; таблица остаётся для аудита попыток.

---

## 4. Контекст Access (RBAC)

```sql
CREATE TABLE access_permissions (
    code        varchar(64) PRIMARY KEY,   -- users.manage, catalog.manage, elements.write ...
    description varchar(255) NOT NULL DEFAULT ''
);

CREATE TABLE access_roles (
    code        varchar(32) PRIMARY KEY,   -- owner | admin | user
    name        varchar(64) NOT NULL,
    is_system   boolean NOT NULL DEFAULT false, -- системные роли не удаляются
    created_at  timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE access_role_permissions (
    role_code       varchar(32) NOT NULL REFERENCES access_roles(code) ON DELETE CASCADE,
    permission_code varchar(64) NOT NULL REFERENCES access_permissions(code) ON DELETE CASCADE,
    PRIMARY KEY (role_code, permission_code)
);

-- Точка расширения (многоролевость, вне MVP):
CREATE TABLE access_user_roles (
    user_id   uuid NOT NULL REFERENCES auth_users(id) ON DELETE CASCADE,
    role_code varchar(32) NOT NULL REFERENCES access_roles(code) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_code)
);
```
- MVP: у пользователя одна роль (`auth_users.role`). `access_user_roles` — задел на много-ролевость.
- Каталог `access_permissions` наполняется сидером из списка констант в коде (единый источник истины — см. [`02-domain.md`](02-domain.md) §5).
- Инвариант «≥1 активный owner» проверяется приложением (нет чисто-декларативного способа в SQL).

---

## 5. Контекст Highload (движок каталога)

### 5.1. `hl_blocks` — типы сущностей
```sql
CREATE TABLE hl_blocks (
    id          uuid PRIMARY KEY,
    code        varchar(64) NOT NULL,          -- ^[a-z][a-z0-9_]*$
    name        varchar(255) NOT NULL,
    description text,
    active      boolean NOT NULL DEFAULT true,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX ux_hl_blocks_code ON hl_blocks (code);
```

### 5.2. `hl_fields` — определения полей
```sql
CREATE TABLE hl_fields (
    id            uuid PRIMARY KEY,
    block_id      uuid NOT NULL REFERENCES hl_blocks(id) ON DELETE CASCADE,
    code          varchar(64) NOT NULL,        -- уникален в пределах блока
    name          varchar(255) NOT NULL,
    type          varchar(16) NOT NULL,        -- string|text|integer|decimal|boolean|datetime|enum|file|reference
    required      boolean NOT NULL DEFAULT false,
    multiple      boolean NOT NULL DEFAULT false,
    settings      jsonb NOT NULL DEFAULT '{}', -- {maxLength}|{precision,scale}|{allowedValues:[]}|{targetBlockCode}|{allowedMime,maxSize}
    show_in_filter boolean NOT NULL DEFAULT false,
    sort          integer NOT NULL DEFAULT 500,
    created_at    timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX ux_hl_fields_block_code ON hl_fields (block_id, code);
CREATE INDEX ix_hl_fields_block ON hl_fields (block_id, sort);
```
`type` можно оформить как `enum`-домен PG (`CREATE TYPE hl_field_type AS ENUM (...)`); в sqlite — `CHECK(type IN (...))`.

### 5.3. `hl_elements` — записи (подход C: колонки + JSONB)
```sql
CREATE TABLE hl_elements (
    id          uuid PRIMARY KEY,
    block_id    uuid NOT NULL REFERENCES hl_blocks(id) ON DELETE CASCADE,
    code        varchar(255),                  -- символьный код (уникален в блоке, если задан)
    name        varchar(255) NOT NULL DEFAULT '',
    active      boolean NOT NULL DEFAULT true,
    sort        integer NOT NULL DEFAULT 500,
    data        jsonb NOT NULL DEFAULT '{}',   -- значения пользовательских полей: {"price":"1990.00","brand":"<uuid>"}
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ix_hl_elements_block ON hl_elements (block_id, active, sort);
CREATE UNIQUE INDEX ux_hl_elements_block_code ON hl_elements (block_id, code) WHERE code IS NOT NULL;
-- Гибкая фильтрация по любому пути JSONB:
CREATE INDEX gin_hl_elements_data ON hl_elements USING gin (data jsonb_path_ops);
-- Пример точечного expression-индекса под частый фильтр (offers.status):
-- CREATE INDEX ix_offers_status ON hl_elements ((data->>'status')) WHERE block_id = '<offers-uuid>';
```
- Валидация `data` по `hl_fields` — в приложении (`ElementValidator`), **до записи** (гарантирует формат для индексов-кастов).
- **Контракт хранения по `FieldType` (консилиум) — критично для корректности:**
  - `integer`, `boolean` — **нативные JSON-типы** (`{"qty": 3, "in_stock": true}`) → фильтр равенства через `data @> '{"in_stock": true}'` работает по GIN;
  - `decimal` / деньги — **строка** (`{"price": "1990.00"}`), НЕ JSON-число: `json_decode` превратил бы число в `float` → потеря точности (правило `rebit-backend`: деньги — `numeric`, не float). При тяжёлых диапазонах/агрегатах поле промотируется в реальную `numeric`-колонку;
  - `reference` — uuid-строка; `datetime` — ISO-8601-строка; `enum` — строка; `multiple` (после MVP) — JSON-массив.
- **Индексы под фильтр/сортировку (консилиум):**
  - равенство/containment — общий GIN `data jsonb_path_ops` (оператор `@>`; `data->>'x' = 'y'` его НЕ использует — писать фильтры как `@>`);
  - сортировка/диапазоны — **partial expression-btree, scoped на конкретный `block_id` и поле**: `CREATE INDEX ... ON hl_elements ((data->>'price')::numeric) WHERE block_id = '<uuid>'`. Глобальный (без `WHERE block_id`) каст-индекс **упадёт** на первом же элементе другого блока с неприводимым значением — таблица мультиблоковая. Такие индексы заводит **сидер/обработчик `FieldUpdated`** (для полей `show_in_filter=true`), не структурная миграция;
  - следствие (консилиум): индексируемая сортировка возможна только по **предобъявленным** фильтр-полям; произвольное пользовательское поле сортируется без гарантии производительности (осознанное ограничение подхода C — [`02-domain.md`](02-domain.md) §6).
- `reference`-значения (`data->>'product'` = UUID элемента другого блока) — **без FK**, целостность в приложении (СЦ-3.3). **Политика удаления цели (консилиум): RESTRICT** — удаление элемента запрещено при наличии ссылающихся; обратный поиск — существующим GIN (`data @> '{"product":"<uuid>"}'`). GC осиротевших — только safety-net для `force`.
- Полнотекстовый поиск по `name` — btree/`ILIKE` или `pg_trgm` (индекс `gin (name gin_trgm_ops)`) при необходимости.
- **Пагинация (консилиум):** keyset по `(sort, id)` (индекс `(block_id, active, sort, id)`); OFFSET — только для админ-выборок с малыми страницами.
- `updated_at` **не** автообновляется (нет триггера) — это ответственность репозитория при записи.

### 5.4. Каталог и офферы = данные (сидер)
Никаких отдельных таблиц. Сидер создаёт:
- блок `catalog` + поля `price(decimal)`, `sku(string)`, `brand(reference→brands)`, `image(file)`, `in_stock(boolean)`;
- блок `offers` + поля `product(reference→catalog)`, `price(decimal)`, `qty(integer)`, `min_amount(decimal)`, `payment_method(enum)`, `status(enum)`.
Связь оффер→товар — `hl_elements.data->>'product'` (UUID элемента блока `catalog`) — прямой аналог `Advertisement.UF_CURRENCY_PAIR_ID` из P2P.

---

## 6. Контекст событий (Events)

### 6.1. `event_outbox` — transactional outbox
```sql
CREATE TABLE event_outbox (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    event_id       uuid NOT NULL,               -- идемпотентность (at-least-once)
    name           varchar(128) NOT NULL,       -- ElementCreated, UserPasswordChanged ...
    aggregate_type varchar(64),
    aggregate_id   varchar(64),
    payload        jsonb NOT NULL DEFAULT '{}',
    status         varchar(16) NOT NULL DEFAULT 'pending', -- pending|published|failed
    attempts       smallint NOT NULL DEFAULT 0,
    available_at   timestamptz NOT NULL DEFAULT now(),      -- backoff
    published_at   timestamptz,
    last_error     text,
    occurred_at    timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX ux_outbox_event ON event_outbox (event_id);
CREATE INDEX ix_outbox_pickup ON event_outbox (status, available_at);
```
Обработчик `events:dispatch-outbox` выбирает `status='pending' AND available_at<=now()` c `FOR UPDATE SKIP LOCKED` (PG), публикует подписчикам, ставит `published`/`failed` + backoff (СЦ-5.2).

### 6.2. `event_subscriptions` — реестр подписок
```sql
CREATE TABLE event_subscriptions (
    id           uuid PRIMARY KEY,
    event_name   varchar(128) NOT NULL,
    handler      varchar(255) NOT NULL,   -- FQCN обработчика
    mode         varchar(8) NOT NULL DEFAULT 'async', -- sync|async
    active       boolean NOT NULL DEFAULT true,
    max_attempts smallint NOT NULL DEFAULT 5
);
CREATE INDEX ix_subscriptions_event ON event_subscriptions (event_name, active);
```
> Реестр можно вести и в коде (конфиг); таблица нужна, если подписки настраиваются из админки.

---

## 7. Контекст планировщика (Cron)

```sql
CREATE TABLE cron_jobs (
    code        varchar(64) PRIMARY KEY,     -- events:dispatch-outbox, auth:purge-expired-tokens ...
    expression  varchar(64) NOT NULL,        -- cron-выражение
    active      boolean NOT NULL DEFAULT true,
    last_run_at timestamptz,
    last_status varchar(16),                 -- success|failed
    created_at  timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE cron_job_runs (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    job_code    varchar(64) NOT NULL REFERENCES cron_jobs(code) ON DELETE CASCADE,
    started_at  timestamptz NOT NULL DEFAULT now(),
    finished_at timestamptz,
    status      varchar(16) NOT NULL DEFAULT 'running', -- running|success|failed
    output      text
);
CREATE INDEX ix_job_runs_job_time ON cron_job_runs (job_code, started_at DESC);

CREATE TABLE cron_locks (                     -- защита от параллельного запуска
    name        varchar(64) PRIMARY KEY,
    acquired_at timestamptz NOT NULL DEFAULT now(),
    expires_at  timestamptz NOT NULL
);
```
**Решение консилиума (планировщик):** в проде задачи запускает **swarm-cronjob по команде** ([`04-devops.md`](04-devops.md) §7) — это единственная система расписаний. Поэтому:
- таблицы `cron_jobs` и диспетчер `schedule:run` в MVP **не заводятся** (иначе — две конкурирующие системы расписаний);
- `cron_job_runs` остаётся **только для логов запусков**, но **без FK `→ cron_jobs`** (снять `REFERENCES cron_jobs`, оставить `job_code varchar`);
- `cron_locks` в MVP **не нужна** (swarm-cronjob + `skip-running=true` на single-manager достаточно); защита от параллелизма — `skip-running`, при необходимости `symfony/lock`. Таблица `cron_locks` — точка расширения при мультименеджере.

---

## 8. Контекст Audit

```sql
CREATE TABLE audit_log (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    actor_id     uuid REFERENCES auth_users(id) ON DELETE SET NULL, -- NULL для системных
    action       varchar(128) NOT NULL,      -- совпадает с именем события/операции
    subject_type varchar(64),                -- user|block|element|role ...
    subject_id   varchar(64),
    changes      jsonb NOT NULL DEFAULT '{}', -- {before:{...}, after:{...}}
    ip           inet,
    user_agent   varchar(255),
    created_at   timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ix_audit_actor   ON audit_log (actor_id, created_at DESC);
CREATE INDEX ix_audit_subject ON audit_log (subject_type, subject_id);
CREATE INDEX ix_audit_action  ON audit_log (action, created_at DESC);
```
Append-only; наполняется синхронным подписчиком на события. Ротация — `audit:rotate` (СЦ-5). `SecurityAlert` (СЦ-6.2) можно вести отдельной таблицей `security_alerts` того же вида или как `audit_log.action='security.alert'`.

---

## 9. ER-обзор (текстовый)

```
auth_users 1──* auth_access_tokens
auth_users 1──* auth_confirmations
auth_users 1──* access_user_roles *──1 access_roles *──* access_permissions
                                          (через access_role_permissions)
auth_users.role ─── access_roles.code                (MVP: одиночная роль)

hl_blocks 1──* hl_fields
hl_blocks 1──* hl_elements
hl_elements.data->>'<ref_field>' ··· hl_elements.id  (reference, без FK, целостность в приложении)

event_outbox            (генерится в транзакциях доменных операций)
event_subscriptions
cron_jobs 1──* cron_job_runs ;  cron_locks
audit_log *──1 auth_users (actor)
```

---

## 10. Стратегия миграций

- Формат — как сейчас: `migrations/YYYYMMDDHHMMSS_name.sql` (Postgres) и `migrations/sqlite/…` (SQLite), применяются `Migrator`-ом в транзакции, версия пишется в `schema_migrations` (факт).
- Порядок ввода (по одному файлу на шаг, с `down`-эквивалентом там, где нужно):
  1. `extend_auth_users_security` — `status, failed_login_count, locked_until, last_login_at`; смена дефолта роли на `user`; при переходе PK на uuid — отдельная выверенная миграция.
  2. `create_auth_confirmations`, `create_auth_login_attempts` (+ перенос `auth_registration_codes`).
  3. `create_access_rbac` (`permissions, roles, role_permissions, user_roles`) + сидер прав/ролей.
  4. `create_hl_blocks`, `create_hl_fields`, `create_hl_elements` (+ GIN-индекс).
  5. `create_event_outbox`, `create_event_subscriptions`.
  6. `create_cron_jobs`, `create_cron_job_runs`, `create_cron_locks`.
  7. `create_audit_log`.
  8. `seed_catalog_offers` (блоки `catalog`/`offers` + поля) — фикстура/сидер, не структурная миграция.
- **Диалектные различия** (sqlite): `uuid`→`TEXT`; `timestamptz`→`TEXT`; `jsonb`→`TEXT` (json1-функции для валидации/фильтра); `inet`→`TEXT`; частичные/выражательные индексы и `GIN` — опускаются или заменяются; `GENERATED AS IDENTITY`→`INTEGER PRIMARY KEY AUTOINCREMENT`. Логика ветвления по драйверу — в `bin/app.php` (факт: уже выбирает набор миграций по `DB_CONNECTION`).
- **Рекомендация:** довести PHP-образ до `pdo_pgsql` и вести разработку на Postgres, оставив sqlite только для быстрых юнит-тестов, — чтобы не поддерживать две расходящиеся ветки JSONB/индексов вручную.

---

## 11. Безопасность на уровне БД (сводка)

- Пароли — только `password_hash` (argon2id); токены — только `sha256`-хэш; коды подтверждений — хэш (не открытый текст).
- Никакой конкатенации SQL — только prepared statements (факт: PDO с плейсхолдерами) → защита от SQL-инъекций.
- Уникальные индексы (email/login/phone/block.code/field.code) — на уровне БД, не только в коде.
- `ON DELETE CASCADE`/`SET NULL` расставлены осознанно (токены/подтверждения — каскад за пользователем; аудит — `SET NULL`, чтобы сохранить историю).
- Секреты подключения — в `.env` (факт), не в репозитории; `APP_DEBUG=false` на проде.
- Резервное копирование БД и health-check — обязательны к вводу перед продом (DoD [[rebit-backend]]).

---

## 12. Принятые решения (по схеме)

1. **PK — `uuid` (v7)** для доменных агрегатов (`auth_users`, `hl_blocks`, `hl_fields`, `hl_elements`, `access_*`-справочники — по `code`), `bigint IDENTITY` — для append-only лог-таблиц (`audit_log`, `security_alerts`, `cron_job_runs`, `login_attempts`). **UUID v7 не нативен в PostgreSQL 17** (`uuidv7()` только с PG18), поэтому **`id` всегда генерирует приложение** (VO `Id` на `symfony/uid` или `ramsey/uuid ≥4.7`); `DEFAULT` у uuid-колонок **сознательно отсутствует**. Ломающая перекладка `auth_users.id bigint→uuid` (вместе с зависимыми `auth_access_tokens.user_id`, `auth_confirmations.user_id`) делается **до первого прод-деплоя** — прод-окружения ещё нет (весь [`04-devops.md`](04-devops.md) — план внедрения), поэтому truncate/recreate дёшев и совместим с expand/contract-дисциплиной (§10).
7. **Рост append-only — только purge/retention в MVP** (консилиум): задачи очистки `login_attempts` и (при вводе) `published`-событий; ротация `audit_log`/`cron_job_runs` по сроку. **Декларативное партиционирование — отложено** (для мини-админки оверинжиниринг; к тому же RANGE-партиция по `created_at` потребовала бы композитного PK `(id, created_at)`). Partial-индекс pickup — по `WHERE status='pending'`.
2. **`hl_elements` — подход C (системные колонки + `JSONB`)** утверждён. GIN по `data`, expression-индексы под частые фильтры блоков.
3. **Подписки на события — в коде (конфиг-реестр) в MVP.** Таблица `event_subscriptions` (§6.2) — опциональное расширение для админ-управляемых подписок; в MVP не создаётся. `event_outbox` — обязательна.
4. **Redis не вводим на старте.** `event_outbox`, `cron_locks`, счётчики `auth_login_attempts` — в Postgres. Redis — при росте нагрузки (кэш/локи/лимиты), без изменения доменных портов.
5. **`security_alerts` — отдельная таблица** (ниже): у алертов иной жизненный цикл и retention, чем у аудита, и отдельная точка для мониторинга/SIEM.
6. **Единая PostgreSQL 17**; SQLite — только юнит-тесты. После ввода `pdo_pgsql` ([`04-devops.md`](04-devops.md)) ветка `migrations/sqlite/` замораживается/упраздняется.

```sql
CREATE TABLE security_alerts (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    kind        varchar(64) NOT NULL,       -- brute_force | idor_attempt | rate_limit | suspicious_login
    severity    varchar(16) NOT NULL DEFAULT 'warning', -- info|warning|critical
    user_id     uuid REFERENCES auth_users(id) ON DELETE SET NULL,
    ip          inet,
    context     jsonb NOT NULL DEFAULT '{}',
    resolved_at timestamptz,
    created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX ix_security_alerts_kind ON security_alerts (kind, created_at DESC);
CREATE INDEX ix_security_alerts_open ON security_alerts (created_at DESC) WHERE resolved_at IS NULL;
```
