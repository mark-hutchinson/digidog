.PHONY: help up down logs shell migrate rollback migration
.DEFAULT_GOAL := help

PHINX = docker compose exec server vendor/bin/phinx

help: ## List available targets
	@grep -E '^[a-z]+:.*##' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  %-10s %s\n", $$1, $$2}'

up: ## Build and start containers, then run migrations
	docker compose up --build -d
	$(MAKE) migrate

down: ## Stop containers
	docker compose down

logs: ## Follow container logs
	docker compose logs -f

shell: ## Open a shell in the server container
	docker compose exec server bash

migrate: ## Run pending migrations
	$(PHINX) migrate

rollback: ## Roll back the last migration
	$(PHINX) rollback

migration: ## Create a migration: make migration name=CamelCaseName
	@test -n "$(name)" || (echo "Usage: make migration name=CamelCaseName" && exit 1)
	$(PHINX) create $(name)
