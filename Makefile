# ReBit Admin Core — монорепо (api/ + frontend/).
# Единая точка команд поверх Docker Compose (структура как у Елисеева).

REGISTRY ?= ghcr.io/rebit-pro
IMAGE_TAG ?= $(shell git rev-parse --short HEAD 2>/dev/null || echo dev)
STACK_NAME ?= admin
IMAGE_PREFIX = $(REGISTRY)/admin-rebit-core

# Контракт VITE_* прод-фронта (docs/04-devops.md §2.4); в CI приходят из GitHub Variables.
VITE_API_URL ?= https://api2.rebit-pro.ru
VITE_SMARTCAPTCHA_CLIENT_KEY ?=
VITE_APP_VERSION ?= $(IMAGE_TAG)

.PHONY: up down restart docker-build logs \
	api-migrate api-fixtures api-test api-lint api-cs-check api-analyze api-fixer api-cli \
	frontend-check frontend-build \
	build build-api build-frontend try-build push

## --- Жизненный цикл (dev) ---
up:
	docker compose up -d

down:
	docker compose down

restart: down up

docker-build:
	docker compose build

logs:
	docker compose logs -f

## --- API (в контейнере app, рабочая директория /app = ./api) ---
api-migrate:
	docker compose exec -T app php bin/app.php migrate

api-fixtures:
	docker compose exec -T app php bin/app.php fixtures

api-test:
	docker compose exec -T app php vendor/bin/phpunit

# composer в образе app отсутствует — линтуем напрямую через php -l
api-lint:
	docker compose exec -T app sh -c 'php -l public/index.php > /dev/null && find src config tests bin -name "*.php" -print0 | xargs -0 -n1 php -l > /dev/null && echo "Lint OK"'

api-cs-check:
	docker compose exec -T app php vendor/bin/php-cs-fixer fix --dry-run --diff

api-analyze:
	docker compose exec -T app php vendor/bin/psalm --no-cache

api-fixer:
	docker compose exec -T app php vendor/bin/php-cs-fixer fix

api-cli:
	docker compose exec app sh

## --- Frontend (Berry SPA) ---
frontend-check:
	docker compose run --rm --no-deps -T frontend-node sh -lc 'cd /app && npm run typecheck'

frontend-build:
	docker compose run --rm --no-deps -T frontend-node sh -lc 'cd /app && npm run build'

## --- Прод-образы (context: корень монорепо, docs/04-devops.md §1a/§6) ---
build: build-api build-frontend

build-api:
	docker build -f docker/production/nginx/Dockerfile -t $(IMAGE_PREFIX)-api:$(IMAGE_TAG) .
	docker build -f docker/production/php-fpm/Dockerfile -t $(IMAGE_PREFIX)-api-php-fpm:$(IMAGE_TAG) .
	docker build -f docker/production/php-cli/Dockerfile -t $(IMAGE_PREFIX)-api-php-cli:$(IMAGE_TAG) .

build-frontend:
	docker build -f frontend/docker/production/nginx/Dockerfile \
		--build-arg VITE_API_URL=$(VITE_API_URL) \
		--build-arg VITE_SMARTCAPTCHA_CLIENT_KEY=$(VITE_SMARTCAPTCHA_CLIENT_KEY) \
		--build-arg VITE_APP_VERSION=$(VITE_APP_VERSION) \
		--build-arg VITE_API_MOCKS_ENABLED=false \
		-t $(IMAGE_PREFIX)-frontend:$(IMAGE_TAG) frontend

try-build:
	$(MAKE) build IMAGE_TAG=try VITE_API_URL=http://localhost VITE_APP_VERSION=try

push:
	docker push $(IMAGE_PREFIX)-api:$(IMAGE_TAG)
	docker push $(IMAGE_PREFIX)-api-php-fpm:$(IMAGE_TAG)
	docker push $(IMAGE_PREFIX)-api-php-cli:$(IMAGE_TAG)
	docker push $(IMAGE_PREFIX)-frontend:$(IMAGE_TAG)
