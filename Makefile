# Executables (local)
DOCKER_COMP = docker compose

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php

# Executables
PHP      = $(PHP_CONT) php
COMPOSER = $(PHP_CONT) composer
SYMFONY  = $(PHP) bin/console

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh composer vendor sf cc test

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: ## Builds the Docker images
	@$(DOCKER_COMP) build --pull --no-cache

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

start: build up ## Build and start the containers

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

sh: ## Connect to the FrankenPHP container
	@$(PHP_CONT) sh

## —— Composer 🧙 ——————————————————————————————————————————————————————————————
composer: ## Run composer, pass the parameter "c=" to run a given command, example: make composer c='req symfony/orm-pack'
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction
vendor: composer

## —— Symfony 🎵 ———————————————————————————————————————————————————————————————
sf: ## List all Symfony commands or pass the parameter "c=" to run a given command, example: make sf c=about
	@$(eval c ?=)
	@$(SYMFONY) $(c)

cc: c=c:c ## Clear the cache
cc: sf

## —— Fixtures & Database ————————————————————————————————————————————————————
fixtures: ## Load Symfony Doctrine fixtures
	@$(SYMFONY) doctrine:fixtures:load --no-interaction

fixtures-append: ## Append fixtures without erasing existing data
	@$(SYMFONY) doctrine:fixtures:load --no-interaction --append

init-db: ## Drop, create, migrate DB and load fixtures
	@$(SYMFONY) doctrine:database:drop --force --if-exists
	@$(SYMFONY) doctrine:database:create --if-not-exists
	@$(SYMFONY) doctrine:migrations:migrate --no-interaction
	@$(SYMFONY) doctrine:fixtures:load --no-interaction

## —— Tests 🧪 ——————————————————————————————————————————————————————————————
init-test-db: ## Initialize test database
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/console doctrine:database:drop --force --if-exists
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/console doctrine:database:create --if-not-exists
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction

init-test-fixtures: ## Load fixtures in test database
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/console doctrine:fixtures:load --no-interaction

init-test: init-test-db init-test-fixtures ## Initialize complete test environment (DB + fixtures)

test-reset: init-test ## Reset test environment and run tests
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/phpunit

test: ## Run tests with PHPUnit
	@$(eval c ?=)
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/phpunit $(c)

test-coverage: ## Run PHPUnit tests with coverage (HTML)
	@$(DOCKER_COMP) exec -e XDEBUG_MODE=coverage -e APP_ENV=test php \
	   vendor/bin/phpunit --coverage-html var/coverage

test-coverage-text: ## Run PHPUnit tests with coverage (console output)
	@$(DOCKER_COMP) exec -e XDEBUG_MODE=coverage -e APP_ENV=test php \
	   vendor/bin/phpunit --coverage-text

test-unit: ## Run only unit tests
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/phpunit --testsuite=unit

test-integration: ## Run only integration tests
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/phpunit --testsuite=integration

test-functional: ## Run only functional tests
	@$(DOCKER_COMP) exec -e APP_ENV=test php bin/phpunit --testsuite=functional