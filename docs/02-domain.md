# Предметная область — ReBit Admin Core

> Описание домена, ограниченных контекстов, агрегатов, инвариантов и доменных событий.
> Основан на [`01-scenarios.md`](01-scenarios.md); служит входом для [`03-database.md`](03-database.md).
> Архитектура: Slim в стиле Д. Елисеева (CQRS: Command/Handler, Query/Fetcher) + элементы DDD и гексагональной архитектуры. Уровень **L** по [[rebit-backend]].

## 1. Единый язык (Ubiquitous Language)

| Термин | Значение |
|---|---|
| **Пользователь (User)** | учётная запись с идентификатором входа (email), логином, ролью, профилем |
| **Идентификатор входа** | email (в MVP). Телефон — заложен, не активен |
| **Логин (Login)** | отображаемое уникальное имя, **не** канал входа; меняется отдельно от email |
| **Токен доступа (Access Token)** | opaque-строка; в БД хранится только её sha256-хэш с TTL; отзываемый |
| **Роль (Role)** | именованный набор прав (`owner/admin/user` в MVP) |
| **Право (Permission)** | атомарное разрешение (`users.manage`, `catalog.manage`, `elements.write`…) |
| **HL-блок (Block)** | тип сущности / справочник с настраиваемым набором полей (упрощённый highload-блок) |
| **Поле (Field)** | типизированное свойство блока (string/integer/decimal/enum/reference/file…) |
| **Элемент (Element)** | запись блока: системные атрибуты + значения пользовательских полей |
| **Каталог / Оффер** | не код, а сконфигурированные блоки: `catalog` (товары) и `offers` (предложения), связанные reference-полем |
| **Доменное событие** | факт свершившегося изменения (`ElementCreated`, `UserPasswordChanged`…) |
| **Outbox** | таблица исходящих событий, публикуемых фоново (at-least-once, идемпотентно) |
| **Задача (Job)** | консольная команда, запускаемая планировщиком по расписанию, с локом |
| **Аудит (Audit Entry)** | неизменяемая запись значимого действия (кто/что/когда/до-после/IP) |

---

## 2. Карта контекстов (Bounded Contexts) и модули

Каждый контекст = модуль в `src/` со строгой гексагональной структурой (см. §3). Зависимости идут внутрь: `Presentation → Application → Domain`, `Infrastructure` реализует порты `Domain`/`Application`. Между модулями — только через публичные Application-порты и доменные события, без прямого доступа к чужим репозиториям.

```
┌─────────────────────────────────────────────────────────────┐
│                      Presentation (HTTP / CLI)               │
└─────────────────────────────────────────────────────────────┘
        │            │             │            │
   ┌────▼────┐  ┌────▼────┐  ┌─────▼─────┐  ┌───▼────┐
   │  Auth   │  │ Access  │  │  Highload │  │ Audit  │   ← Bounded Contexts (модули)
   │ (IAM)   │  │ (RBAC)  │  │ (каталог) │  │        │
   └────┬────┘  └────┬────┘  └─────┬─────┘  └───┬────┘
        └────────────┴──────┬──────┴────────────┘
                     ┌───────▼────────┐
                     │     Shared     │  ← ядро платформы: EventBus, Outbox,
                     │  (Platform)    │    Scheduler, Http-kernel, Security,
                     └────────────────┘    Clock, Ids, Validation
```

| Модуль (namespace) | Контекст | Ответственность |
|---|---|---|
| `App\Auth` | Identity & Access Management | пользователи, аутентификация, токены, смена логина/email/пароля, профиль |
| `App\Access` | Authorization (RBAC) | роли, права, проверка доступа |
| `App\Highload` | Content / Catalog Engine | блоки, поля, элементы; на них строятся каталог и офферы |
| `App\Audit` | Observability | журнал значимых действий |
| `App\Shared` | Platform / Supporting | шина событий, outbox, планировщик, HTTP-ядро (middleware, error-handlers, responder), security, VO-примитивы (`Id`, `Clock`) |

> Текущий код (`App\Auth`, `App\Http`, `App\Database`) — плоский. Целевая раскладка: `App\Http` → `App\Shared\Http`; `App\Database` → `App\Shared\Persistence`; `App\Auth\*` перекладывается в 4 слоя (см. §3.2). Модуль `Auth` уже фактически реализует стиль «тонкий Action → Service → Repository» — это основа, которую доводим до Command/Handler + порты.

---

## 3. Архитектура модуля (гексагон + слои Елисеева)

### 3.1. Слои и правила зависимостей
```
Presentation/   HTTP-actions (тонкие), CLI-команды, HTTP-request DTO, маршруты, JSON-ресурсы
    │  (зависит от Application)
Application/     Command + Handler (write), Query + Fetcher (read), Application DTO,
    │            технические порты (Hasher, TokenGenerator, EventPublisher, ConfirmationSender),
    │            прикладные сервисы, orchestration
    │            NB (консилиум): driven-порты `*Repository` живут в Domain, НЕ здесь.
    │  (зависит от Domain, объявляет порты)
Domain/          Entity/Aggregate, Value Object, Enum, Domain Event, Domain Service,
    │            доменные исключения, интерфейсы репозиториев (порты домена), инварианты
    │  (ни от чего не зависит)
Infrastructure/  Адаптеры портов: PDO-репозитории, внешние клиенты, отправка писем/SMS,
                 реализация EventPublisher/Outbox, персистентный маппинг, миграции модуля
    (зависит от Domain/Application — реализует их порты)
```
**Правило:** `Domain` не знает о Slim, PDO, HTTP. Инфраструктура подключается через DI (PHP-DI) к портам. Это позволяет менять PDO→Doctrine или Outbox→RabbitMQ без правки домена.

### 3.2. Пример раскладки модуля `Auth`
```
src/Auth/
├── Domain/
│   ├── User/
│   │   ├── User.php                 # агрегат (приватный конструктор, фабрики, инварианты)
│   │   ├── UserId.php  Email.php  Login.php  Phone.php
│   │   ├── HashedPassword.php  Role.php (enum)  UserStatus.php (enum)
│   │   └── UserRepository.php        # порт (интерфейс)
│   ├── Token/AccessToken.php  TokenHash.php
│   ├── Event/UserLoggedIn.php  UserPasswordChanged.php  UserLoginChanged.php ...
│   └── Exception/AuthException.php   # с HTTP-статусом (факт: уже есть)
├── Application/
│   ├── Command/Login/{Command.php,Handler.php}
│   ├── Command/ChangePassword/{Command.php,Handler.php}
│   ├── Command/ChangeLogin/{Command.php,Handler.php}
│   ├── Command/ChangeEmail/{Request,Confirm}/...
│   ├── Query/CurrentUser/{Query.php,Fetcher.php}
│   └── Port/{PasswordHasher.php, TokenGenerator.php, EventPublisher.php, ConfirmationSender.php}
├── Infrastructure/
│   ├── Persistence/Pdo/PdoUserRepository.php     # адаптер порта (сейчас — AuthRepository)
│   ├── Security/Argon2PasswordHasher.php
│   ├── Security/RandomTokenGenerator.php         # сейчас — TokenFactory
│   └── Notification/StubConfirmationSender.php
└── Presentation/
    ├── Http/Action/{LoginAction, LogoutAction, ChangePasswordAction, ...}
    ├── Http/Request/{LoginRequest, ...}          # входные DTO + валидация
    └── routes.php                                # маршруты модуля
```
Поток write-сценария (в стиле Елисеева): `Action` разбирает запрос в `Command` → валидация → `Handler` оркестрирует домен (`User::changePassword(...)`) → репозиторий сохраняет → `EventPublisher` публикует события → `JsonResponder` отдаёт `{data}`. Read-сценарий: `Action` → `Query` → `Fetcher` (прямой SQL, минуя агрегат) → ресурс.

### 3.3. DI и сборка модулей
- Контейнер — PHP-DI (факт). Предлагается модульная регистрация: `config/modules/<module>.php` (аналог `config/common/*.php` у Елисеева) — привязка портов к адаптерам (`UserRepository::class => PdoUserRepository::class`) и параметры (`token_ttl`), склейка через агрегатор/glob. Простые сервисы — autowiring (факт).
- Маршруты собираются из `Presentation/routes.php` каждого модуля.

---

## 4. Контекст **Auth** (IAM)

### Агрегат `User`
Корень агрегата. Инкапсулирует учётные данные и профиль.

**Идентичность:** `UserId` (UUID или bigint — см. [`03-database.md`](03-database.md)).

**Атрибуты / Value Objects:**
- `Email` — нормализованный (lowercase, trim), валидный формат; уникален; канал входа.
- `Login` — уникальный, `^[a-z0-9][a-z0-9._-]{2,31}$`; не канал входа.
- `Phone` — нормализованный E.164 (заложен, необязателен).
- `HashedPassword` — argon2id/bcrypt-хэш; сырой пароль в домене не хранится.
- `Role` (enum: `owner|admin|user`) — связь с контекстом Access.
- `UserStatus` (enum: `active|blocked`).
- профиль: `name`, `address` (факт — есть в `Identity`).

**Фабрики (именованные конструкторы):**
- `User::register(id, email, login, hashedPassword, role, now)` — создание.
- `User::fromNetwork(...)` — точка расширения (соц-логин), в MVP не используется.

**Поведение / инварианты:**
- `changePassword(newHashed)` — сверку текущего пароля делает Handler через порт `PasswordHasher` (домен не знает про хеширование); агрегат принимает уже проверенный `newHashed`, порождает `UserPasswordChanged`. Политика токенов (консилиум): **отозвать ВСЕ токены → выдать новый** (без исключений «кроме текущего»).
- `changeLogin(newLogin)` — проверка уникальности делегируется репозиторию (доменный сервис `LoginUniquenessChecker`); порождает `UserLoginChanged`.
- `changeEmail(newEmail)` — только после подтверждения; порождает `UserEmailChanged`.
- `assignRole(role)` — с проверкой правил (см. Access): нельзя понизить последнего владельца.
- `block()/unblock()` — заблокированный не проходит аутентификацию; нельзя заблокировать последнего владельца.
- Инвариант: `email` и `login` уникальны глобально (обеспечивается БД + проверкой в Handler).

### Агрегат `AccessToken`
- `TokenHash` (sha256 от opaque-строки), `UserId`, `expiresAt`.
- Правила: валиден, если не истёк и существует; отзыв при logout, смене пароля.
- Инвариант: в БД — только хэш, никогда сырой токен (факт — `TokenFactory::hash`).

### Сущность `ConfirmationCode` (регистрация / смена email / сброс пароля)
- email/канал, хэш кода, срок, `resend_available_at` (факт — `auth_registration_codes`). Единый механизм для подтверждений.

### Доменные события Auth
`UserRegistered`, `UserLoggedIn`, `UserLoginFailed`, `UserLoggedOut`, `UserPasswordChanged`, `UserLoginChanged`, `UserEmailChanged`, `UserBlocked`, `UserUnblocked`, `UserDeleted`.

---

## 5. Контекст **Access** (RBAC)

Отделён от Auth: Auth отвечает «кто ты», Access — «что тебе можно».

**Агрегаты / сущности:**
- `Role` — `code` (`owner/admin/user`), `name`, набор `Permission`.
- `Permission` — `code` (`users.manage`, `access.manage`, `catalog.manage`, `elements.read`, `elements.write`), `description`. Каталог прав — фиксированный список констант в коде (единый источник истины), проецируемый в БД.
- Связь `Role ↔ Permission` — many-to-many.

**Правила:**
- Проверка доступа — доменный сервис `AccessDecision::isAllowed(identity, permission, ?resource)`; вызывается из middleware/Policy на каждое действие.
- Инварианты: право `access.manage` — только у `owner`; в системе всегда ≥1 активный `owner`.
- IDOR-защита: для ресурс-специфичных прав проверяется владелец (`resourceOwnerId === identity.id` либо наличие manage-права).

**Границы Auth ↔ Access (консилиум).** Auth хранит только идентичность + **ссылку на роль по коду** (`auth_users.role`). Вся семантика прав, сервис `AccessDecision` и инвариант «≥1 активный owner» принадлежат **Access** (не дублируются в агрегате `User`). Кросс-контекстные операции (назначить роль, проверить право) — через Application-порт Access (`RoleAssignmentService`/`AccessQuery`), а не через общую таблицу. Каталог прав — **код единственный источник истины**; таблицы `access_*` — read-модель, наполняемая сидером.

**MVP-упрощение:** у пользователя одна роль (поле `role` в `auth_users`, факт). В MVP-1 маппинг «роль → права» допустим на **хардкод-проверках** без таблиц `access_*`. **Но per-resource ownership-check (IDOR, СЦ-2.1/6.3) неотключаем даже при хардкод-RBAC** — это требование безопасности, а не гибкости. Таблицы `roles`/`permissions`/`role_permissions` и many-to-many `user_roles` — точки расширения (MVP-2).

**События:** `RoleCreated`, `RolePermissionsChanged`, `UserRoleChanged`.

---

## 6. Контекст **Highload** (движок каталога)

**Классификация (консилиум): это Generic/Supporting-подсистема, а НЕ Core-домен.** Highload — обобщённый metadata-driven конструктор хранения; реальный домен (каталог, офферы, цены) живёт в данных/конфигурации и в надстроечных модулях. Жёсткое правило: **вся доменная логика — в надстройках (`Catalog` и т.п.), Highload только хранит и валидирует значения по метаданным.** Иначе движок обрастёт бизнес-правилами и превратится в «мини-Bitrix» с размытыми границами.

**Ограничение MVP (консилиум):** типы полей сужены до `string | text | integer | decimal | boolean | datetime | enum | reference`; `file` и `multiple` отложены до после-MVP (при отложенном `file` сид каталога не содержит поля `image`). Индексируемая сортировка/фильтрация возможна только по **предобъявленным** фильтр-полям (их индексы заводит сидер) — движок не обещает производительную сортировку по произвольному пользовательскому полю без runtime-DDL. Три агрегата.

### Агрегат `Block` (тип сущности / справочник)
- `BlockCode` (`^[a-z][a-z0-9_]*$`, уникален), `name`, `description`, `active`.
- Содержит определения полей (`Field`) — как часть агрегата (изменение схемы блока — через корень).
- Инвариант: код блока уникален; нельзя удалить блок с элементами без `force`.

### Сущность `Field` (определение поля)
- `FieldCode` (уникален в пределах блока), `FieldType` (enum), `required`, `multiple`, `settings` (VO, зависит от типа), `showInFilter`, `sort`.
- `FieldType`: `string | text | integer | decimal | boolean | datetime | enum | file | reference`.
- `FieldSettings` — типозависимый VO:
  - `string`: maxLength, pattern; `decimal`: precision, scale; `enum`: allowedValues; `reference`: targetBlockCode; `file`: allowedMime, maxSize.
- Инварианты: `enum` требует непустой список; `reference` — существующий целевой блок; смена типа поля с данными — только через `force` + миграция значений.

### Агрегат `Element` (запись блока)
- Системные атрибуты: `ElementId`, `active`, `sort`, `code?`, `name`, `createdAt`, `updatedAt`.
- Пользовательские значения: `map<FieldCode, value>` — валидируются **по определению полей блока** (`ElementValidator` — доменный сервис).
- Инварианты:
  - каждое значение соответствует типу/настройкам поля; обязательные поля заполнены;
  - `reference`-значение указывает на существующий элемент целевого блока (проверка на уровне приложения — как связи по ID в Bitrix HL-блоках, без FK в БД);
  - **инвариант reference-целостности (консилиум): удаление целевого элемента ЗАПРЕЩЕНО при наличии ссылающихся (RESTRICT на уровне приложения).** Обратный поиск «кто ссылается» — существующим GIN-индексом (`data @> '{"<ref>":"<uuid>"}'`), отдельный индекс не нужен. Фоновый GC — только safety-net для `force`-удаления/прямых правок БД, не часть штатной политики;
  - `code` (если задан) уникален в пределах блока.

### Каталог и офферы как конфигурация
- `catalog` — блок с полями товара; `offers` — блок с `reference→catalog` + торговые атрибуты. Это **данные** (сидируются), а не отдельные классы. При усложнении бизнес-логики поверх движка добавляется тонкий модуль `Catalog` (Application-use-case'ы), переиспользующий хранилище `Highload`.

**События:** `BlockCreated/Updated/Deleted`, `FieldCreated/Updated/Deleted`, `ElementCreated/Updated/Deleted`.

---

## 7. Контекст **Audit**

- `AuditEntry` (неизменяемая): `actorId`, `action`, `subjectType`, `subjectId`, `changes` (before/after, JSONB), `ip`, `userAgent`, `createdAt`.
- Наполняется **синхронным** подписчиком на доменные события (§8) — не бизнес-код пишет аудит, а обработчик события. Только append; правки/удаления — запрещены (кроме ротации по СЦ-5).

---

## 8. Доменные события — общая модель

- **Контракт события:** `eventId` (UUID), `name`, `occurredAt`, `payload` (сериализуемый), `aggregateType`, `aggregateId`.
- **Диспетчеризация (порт `EventPublisher` в `Shared`). Модель уточнена консилиумом — sync-подписчики разведены по фазе относительно коммита:**
  1. Транзакционную границу задаёт **UnitOfWork / транзакционный декоратор в `Shared`** вокруг Handler'а (без него «атомарность» декларативна).
  2. **`in-transaction`-подписчики** (аудит) — в той же транзакции, что и изменение агрегата: «нет операции без записи в журнал». Это осознанный размен доступности (падение аудита откатывает бизнес-операцию).
  3. **`after-commit`-подписчики** (инвалидация кэша, внешние интеграции) — строго ПОСЛЕ коммита; их ошибка НЕ откатывает операцию (инвалидацию нельзя делать до коммита — гонка/репопуляция устаревшего значения).
  4. **MVP — только синхронная in-process шина** (пп. 2–3). **`event_outbox` и асинхронная доставка отложены до MVP-2** (порт `EventPublisher` позволяет добавить их без правки домена). При вводе outbox добавляется таблица доставок per-subscriber + consumer-side идемпотентность по `(eventId, handler)`.
- **Реестр подписок** `event_subscriptions`: `eventName → handler`, sync/async, вкл/выкл, ретраи/backoff, dead-letter при исчерпании.
- **Замена транспорта:** порт `EventPublisher` позволяет заменить outbox+cron на RabbitMQ/Redis без изменения доменного кода (как Messenger в P2P).

---

## 9. Планировщик (Scheduler) — доменная модель

- `CronJob`: `code` (команда), `expression` (cron), `active`, `lastRunAt`, `lastStatus`.
- `JobRun`: `jobCode`, `startedAt`, `finishedAt`, `status` (`success|failed|running`), `output`.
- `Lock`: защита от параллельного запуска (`cron_locks` или `symfony/lock`).
- Диспетчер `schedule:run` (запускается системным cron ежеминутно) выбирает готовые задачи, берёт лок, исполняет консольную команду, пишет `JobRun`. Cron-задачи — это те же консольные команды (принцип Елисеева: cron = console command + внешний планировщик).

---

## 10. Сквозные принципы (Shared / Platform)

- **VO-примитивы:** `Id` (UUID v7 предпочтительно — сортируемость), `Clock` (инъекция времени, тестопригодность), `Email`, `Login`.
- **Валидация:** входные DTO валидируются на границе (Presentation/Application); строгая денормализация — неизвестные поля отклоняются (анти-mass-assignment).
- **Ошибки → единый JSON** (факт — `JsonResponder`): доменные исключения с HTTP-статусом (факт — `AuthException::status()`) мапятся middleware в `{error:{message, errors}}`. Стиль Елисеева: отдельные middleware-перехватчики (`DomainExceptionHandler` → 409, `ValidationExceptionHandler` → 422).
- **Безопасность** (детали — [`01-scenarios.md`](01-scenarios.md) §6): middleware аутентификации, RBAC, rate-limit, security-заголовки, CORS-whitelist, prepared statements, argon2, секреты в env.
- **Идемпотентность** — для outbox и подтверждений (по внешнему id/коду).

---

## 11. Границы MVP и точки расширения

| Возможность | MVP | Расширение |
|---|---|---|
| Канал входа | email + пароль | + телефон/OTP (модель готова) |
| Токены | opaque + хэш в БД | + OAuth2/JWT (league), refresh, PKCE (как у Елисеева) |
| Роли | одна роль на юзера | many-to-many `user_roles`, группы |
| События | sync + outbox (БД) | брокер (RabbitMQ/Redis) через порт `EventPublisher` |
| ORM | PDO-репозитории | Doctrine ORM (порты не меняются) |
| Каталог | блоки-данные `catalog`/`offers` | доменный модуль `Catalog` поверх движка |
| Хранение значений | системные колонки + JSONB | dynamic-table на блок / EAV (см. [`03-database.md`](03-database.md)) |
