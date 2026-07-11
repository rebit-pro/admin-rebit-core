# ReBit Admin Core — монорепо (api/ + frontend/).
# Единая точка команд поверх Docker Compose (структура как у Елисеева).

.PHONY: up down restart build logs \
	api-migrate api-fixtures api-test api-lint api-fixer api-cli \
	frontend-check frontend-build

## --- Жизненный цикл ---
up:
	docker compose up -d

down:
	docker compose down

restart: down up

build:
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

api-fixer:
	docker compose exec -T app php vendor/bin/php-cs-fixer fix

api-cli:
	docker compose exec app sh

## --- Frontend (Berry SPA) ---
frontend-check:
	docker compose run --rm --no-deps -T frontend-node sh -lc 'cd /app && npm run typecheck'

frontend-build:
	docker compose run --rm --no-deps -T frontend-node sh -lc 'cd /app && npm run build'
