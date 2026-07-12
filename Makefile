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

# --- Деплой (docs/04-devops.md §7): релизы /srv/admin-rebit-core/site_N + migrate-gate ---
PORT ?= 22
DEPLOY_USER ?= deploy
REMOTE = $(DEPLOY_USER)@$(HOST)
SSH = ssh -p $(PORT) $(REMOTE)
SRV_DIR ?= /srv/admin-rebit-core
RELEASE_DIR = $(SRV_DIR)/site_$(BUILD_NUMBER)
LINK_DIR = $(SRV_DIR)/site
COMPOSE_DST = docker-compose.yml
KEEP_RELEASES ?= 2
DB_DATABASE ?= rebit_admin
DB_USERNAME ?= rebit

.PHONY: up down restart docker-build logs \
	api-migrate api-fixtures api-test api-lint api-cs-check api-analyze api-fixer api-cli \
	frontend-check frontend-build \
	build build-api build-frontend try-build push \
	deploy deploy-check-env api-migrate-prod rollback deploy-clean

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
	docker push $(IMAGE_PREFIX)-api-postgres-backup:$(IMAGE_TAG)
	docker push $(IMAGE_PREFIX)-frontend:$(IMAGE_TAG)

## --- Прод-деплой (релизная механика P2P + migrate-gate, docs/04-devops.md §7) ---
# Требуемые переменные: HOST, BUILD_NUMBER, IMAGE_TAG (+ GHCR_PULL_TOKEN/GHCR_PULL_USER для docker login).
# Versioned-имена Swarm-объектов приходят из окружения (их печатает deploy/swarm-publish-runtime.sh):
#   BACKEND_ENV_CONFIG_NAME, ADMIN_DB_PASSWORD_SECRET_NAME,
#   ADMIN_SMARTCAPTCHA_SERVER_KEY_SECRET_NAME, ADMIN_BACKUP_AWS_SECRET_ACCESS_KEY_SECRET_NAME
# Bootstrap первого деплоя: make deploy SKIP_MIGRATE=1 && make api-migrate-prod (deploy/README.md).
deploy-check-env:
	@test -n "$(HOST)" || { echo "Set HOST"; exit 1; }
	@test -n "$(BUILD_NUMBER)" || { echo "Set BUILD_NUMBER"; exit 1; }
	@test -n "$(IMAGE_TAG)" || { echo "Set IMAGE_TAG"; exit 1; }

# Migrate-gate: синхронный прогон миграций ДО переключения трафика. Exit != 0 валит деплой.
api-migrate-prod: deploy-check-env
	$(SSH) 'docker run --rm \
		--network $(STACK_NAME)_default \
		-v $(SRV_DIR)/swarm/backend.env:/app/.env:ro \
		-v $(SRV_DIR)/swarm/secrets/admin_db_password:/run/secrets/admin_db_password:ro \
		-e DB_PASSWORD_FILE=/run/secrets/admin_db_password \
		$(REGISTRY)/admin-rebit-core-api-php-cli:$(IMAGE_TAG) \
		php bin/app.php migrate'

deploy: deploy-check-env
	scp -P $(PORT) docker-compose-production.yml $(REMOTE):~/
	$(SSH) ' \
		mkdir -p $(SRV_DIR) \
		&& rm -rf $(RELEASE_DIR) && mkdir $(RELEASE_DIR) \
		&& mv ~/docker-compose-production.yml $(RELEASE_DIR)/$(COMPOSE_DST) \
		&& cd $(RELEASE_DIR) \
		&& printf "REGISTRY=%s\nIMAGE_TAG=%s\nDB_DATABASE=%s\nDB_USERNAME=%s\nBACKUP_AWS_ACCESS_KEY_ID=%s\nBACKUP_AWS_DEFAULT_REGION=%s\nBACKUP_S3_ENDPOINT=%s\nBACKUP_S3_BUCKET=%s\nBACKEND_ENV_CONFIG_NAME=%s\nADMIN_DB_PASSWORD_SECRET_NAME=%s\nADMIN_SMARTCAPTCHA_SERVER_KEY_SECRET_NAME=%s\nADMIN_BACKUP_AWS_SECRET_ACCESS_KEY_SECRET_NAME=%s\n" \
			"$(REGISTRY)" \
			"$(IMAGE_TAG)" \
			"$(DB_DATABASE)" \
			"$(DB_USERNAME)" \
			"$(BACKUP_AWS_ACCESS_KEY_ID)" \
			"$(BACKUP_AWS_DEFAULT_REGION)" \
			"$(BACKUP_S3_ENDPOINT)" \
			"$(BACKUP_S3_BUCKET)" \
			"$(BACKEND_ENV_CONFIG_NAME)" \
			"$(ADMIN_DB_PASSWORD_SECRET_NAME)" \
			"$(ADMIN_SMARTCAPTCHA_SERVER_KEY_SECRET_NAME)" \
			"$(ADMIN_BACKUP_AWS_SECRET_ACCESS_KEY_SECRET_NAME)" > .env \
		&& if [ -n "$(GHCR_PULL_TOKEN)" ]; then echo "$(GHCR_PULL_TOKEN)" | docker login ghcr.io -u "$(GHCR_PULL_USER)" --password-stdin; fi \
		&& docker pull $(REGISTRY)/admin-rebit-core-frontend:$(IMAGE_TAG) \
		&& docker pull $(REGISTRY)/admin-rebit-core-api:$(IMAGE_TAG) \
		&& docker pull $(REGISTRY)/admin-rebit-core-api-php-fpm:$(IMAGE_TAG) \
		&& docker pull $(REGISTRY)/admin-rebit-core-api-php-cli:$(IMAGE_TAG) \
		&& docker pull $(REGISTRY)/admin-rebit-core-api-postgres-backup:$(IMAGE_TAG)'
	@if [ -z "$(SKIP_MIGRATE)" ]; then $(MAKE) api-migrate-prod; else echo "[deploy] SKIP_MIGRATE=1 — migrate-gate пропущен (bootstrap)"; fi
	$(SSH) ' \
		ln -sfn $(RELEASE_DIR) $(LINK_DIR) \
		&& cd $(LINK_DIR) && set -a && . ./.env && set +a \
		&& docker stack deploy --with-registry-auth --prune --resolve-image=never -c $(COMPOSE_DST) $(STACK_NAME)'
	$(MAKE) deploy-clean

deploy-clean:
	$(SSH) 'cd $(SRV_DIR) && ls -d site_* 2>/dev/null | sort -t_ -k2 -n | head -n -$(KEEP_RELEASES) | xargs -r rm -rf'
	$(SSH) 'docker image prune --force \
		|| { status=$$?; printf "[deploy][warn] docker image prune failed with exit %s; деплой уже применён, продолжаем.\n" "$$status" >&2; true; }'

# Откат на релиз N: его .env хранит versioned-имена секретов той сборки.
# Пример: HOST=37.143.8.221 ROLLBACK_BUILD_NUMBER=41 make rollback
rollback:
	@test -n "$(HOST)" || { echo "Set HOST"; exit 1; }
	@test -n "$(ROLLBACK_BUILD_NUMBER)" || { echo "Set ROLLBACK_BUILD_NUMBER"; exit 1; }
	$(SSH) 'test -d $(SRV_DIR)/site_$(ROLLBACK_BUILD_NUMBER)'
	$(SSH) 'ln -sfn $(SRV_DIR)/site_$(ROLLBACK_BUILD_NUMBER) $(LINK_DIR)'
	$(SSH) 'cd $(LINK_DIR) && set -a && . ./.env && set +a \
		&& docker stack deploy --with-registry-auth --prune --resolve-image=never -c $(COMPOSE_DST) $(STACK_NAME)'
