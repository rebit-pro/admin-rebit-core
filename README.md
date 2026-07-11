# ReBit Admin Core

Universal admin panel scaffold for ReBit Studio.

## Stack

- PHP 8.5
- Slim 4
- PHP-DI
- PostgreSQL 17
- Vue 3 + Vite + Vuetify frontend transferred from Rebit P2P
- Docker Compose for local development only

## Local start

```bash
cd /home/user/projects/rebit-admin-core
composer install --no-dev --ignore-platform-reqs
docker compose build
docker compose up -d
curl http://localhost:8085/health
```

Frontend dependencies and start:

```bash
docker compose run --rm frontend-node npm ci
docker compose up -d frontend-node
```

## Services

- API: `http://localhost:8085`
- Frontend: `http://localhost:5174`
- PostgreSQL: `localhost:54325`

## Current environment notes

- PHP runtime is verified in Docker: `PHP 8.5.7`.
- Runtime Composer dependencies are installed in `vendor/`.
- Frontend auth shell, GeeTest integration and first protected screen are transferred from Rebit P2P.
- `frontend/.env` runs with `VITE_API_MOCKS_ENABLED=true` until Slim auth endpoints are implemented.
- Production compose is intentionally not present.

## Scope of this scaffold

This repository currently contains the Slim API foundation and the transferred Vue/Vuetify frontend stack. Production compose, CI/CD, Slim auth backend, roles and CRUD modules are intentionally outside this step.