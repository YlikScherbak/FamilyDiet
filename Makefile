.DEFAULT_GOAL := help
.NOTPARALLEL:

PHP = docker compose exec php

## ---- Перший запуск -------------------------------------------------------

init: up vendor migrate fixtures usda ## Повний запуск з нуля (збірка, залежності, БД, сід). УВАГА: fixtures очищають базу
	@echo ""
	@echo "Готово: застосунок — http://localhost:5173, API — http://localhost:8080/api/health"

## ---- E2E (Playwright, production-образ + одноразовий Postgres, порт 8090) ----

e2e-up: ## Підняти ізольований e2e-стек (збірка образу, міграції, фікстури, демо)
	docker compose -f e2e/docker-compose.yml up -d --build --wait

e2e: ## Прогнати Playwright-тести проти e2e-стеку (перед цим: make e2e-up; один раз — cd e2e && npm ci && npx playwright install chromium)
	cd e2e && npx playwright test

e2e-down: ## Зупинити e2e-стек і видалити його БД
	docker compose -f e2e/docker-compose.yml down -v

## ---- Життєвий цикл --------------------------------------------------------

up: ## Зібрати і підняти контейнери
	docker compose up -d --build

down: ## Зупинити контейнери
	docker compose down

logs: ## Логи всіх сервісів (follow)
	docker compose logs -f

ps: ## Стан контейнерів
	docker compose ps

## ---- Backend ---------------------------------------------------------------

vendor: ## Встановити composer-залежності
	$(PHP) composer install

migrate: ## Накатити міграції БД
	$(PHP) php bin/console doctrine:migrations:migrate --no-interaction

fixtures: ## Засіяти демо-дані (ОЧИЩАЄ базу: меню, вага і власні страви зникнуть)
	$(PHP) php bin/console doctrine:fixtures:load --no-interaction

demo: ## Демо-дані для огляду: меню на поточний тиждень + історія ваги (перезаписує їх!)
	$(PHP) php bin/console app:seed-demo

usda: ## Імпорт довідника USDA (~7 200 продуктів) + українські назви; ідемпотентно, базу не чистить
	$(PHP) php bin/console app:import-usda-products
	$(PHP) php bin/console app:import-ingredient-translations

stan: ## Статичний аналіз PHPStan (level 8)
	$(PHP) php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress

cs: ## Поправити код-стайл (php-cs-fixer)
	$(PHP) vendor/bin/php-cs-fixer fix

cs-check: ## Перевірити код-стайл без змін
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

lint: ## ESLint + Prettier-перевірка фронтенду
	docker compose exec node npx eslint src
	docker compose exec node npx prettier --check src

test: ## Тести PHPUnit (готує тестову БД familydiet_test)
	$(PHP) php bin/console doctrine:database:create --env=test --if-not-exists
	$(PHP) php bin/console doctrine:migrations:migrate --env=test --no-interaction
	$(PHP) php bin/phpunit

sh: ## Шел у php-контейнері
	$(PHP) sh

psql: ## Консоль PostgreSQL
	docker compose exec db psql -U app -d familydiet

## ---- Довідка ----------------------------------------------------------------

help: ## Список команд
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-10s\033[0m %s\n", $$1, $$2}'

.PHONY: init up down logs ps vendor migrate fixtures usda sh psql help e2e-up e2e e2e-down
