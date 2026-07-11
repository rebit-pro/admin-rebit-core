# ReBit Admin Core Frontend

Vue 3 + Vite фронтенд для `rebit-admin-core`.

## Быстрый старт

### Переменные окружения

Скопируйте пример и настройте значения:

```bash
cp .env.example .env
```

Основные переменные:

- `VITE_API_URL` — базовый URL backend API
- `VITE_GEETEST_CAPTCHA_ID` — ID GeeTest CAPTCHA для реального логина
- `VITE_API_MOCKS_ENABLED=true` — включает локальные stateful-моки вместо реального API

Пример файла окружения лежит в `frontend/.env.example`.

## Mock API режим

При `VITE_API_MOCKS_ENABLED=true` фронтенд не ходит в backend, а использует локальный stateful mock-слой.
Состояние моков хранится в `localStorage`, поэтому между перезагрузками страницы сохраняются:

- авторизация
- подключение Bybit API ключей
- балансы и транзакции
- сценарии чата
- объявления
- сделки и чат сделки

### Тестовый пользователь

В mock-режиме доступен быстрый вход:

- email: `owner@rebit.test`
- password: `secret123`

GeeTest CAPTCHA в mock-режиме отключается автоматически.

### Минимальный сценарий, который покрывают моки

1. Пользователь авторизуется и подключает Bybit API ключи.
2. После подключения заполняются платежные методы, балансы и исторические данные.
3. Пользователь создаёт одношаговый сценарий:
   - текст с реквизитами перевода
   - QR / изображение
   - PDF
   - видео
4. Пользователь создаёт объявление и может сразу включить или выключить его.
5. Для активного объявления:
   - новая сделка появляется автоматически по polling
   - либо вручную из списка объявлений
6. Первый шаг сценария автоматически попадает в чат новой сделки.
7. Сделка в списке подсвечивается как новая и имеет статус ожидания оплаты.
8. На странице сделки одновременно доступны детали и чат.
9. После оплаты можно выполнить действие `Отпустить средства`.

### Отладка mock-состояния

В браузерном `window` доступен helper `__REBIT_MOCKS__`:

- `window.__REBIT_MOCKS__.reset()` — полный сброс mock-состояния
- `window.__REBIT_MOCKS__.snapshot()` — снимок текущего state
- `window.__REBIT_MOCKS__.createTrade(advertisementId?)` — вручную создать mock-сделку

## Команды

Если в окружении доступны `npm` и `node`:

```bash
npm install
npm run dev
npm run build
npm run typecheck
npm run typecheck:e2e
npm run lint
npm run lint:fix
npm run test:e2e:install
npm run test:e2e:run
```

Если локально уже установлены зависимости, но системный `npm` недоступен, можно использовать локальные бинарники проекта через `node_modules/.bin`.

## Frontend checks

- `npm run lint` — проверка ESLint без автофикса
- `npm run lint:fix` — автоисправление ESLint
- `npm run typecheck` — типизация приложения
- `npm run typecheck:e2e` — типизация e2e-инфраструктуры

## E2E: Cucumber + TypeScript

E2E-тесты лежат в `frontend/e2e` и запускаются через `@cucumber/cucumber` + Playwright.

По умолчанию e2e-тесты стартуют локальный Vite-сервер в mock-режиме:

- `VITE_API_MOCKS_ENABLED=true`
- GeeTest CAPTCHA отключена
- используется тестовый пользователь `owner@rebit.test / secret123`

Полезные команды:

```bash
npm run test:e2e:install
npm run test:e2e:run
npm run test:e2e:headed
```

После запуска HTML-отчёт Cucumber будет доступен в `frontend/reports/e2e/cucumber-report.html`.
