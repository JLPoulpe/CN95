.PHONY: help up down restart build logs ps shell db-shell tools clean

help: ## Affiche l'aide
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-10s %s\n", $$1, $$2}'

up: ## Démarre les containers (web + db)
	docker compose up -d

build: ## Reconstruit l'image et démarre
	docker compose up -d --build

down: ## Arrête et supprime les containers (données conservées)
	docker compose down

restart: down up ## Redémarre les containers

ps: ## Liste l'état des containers
	docker compose ps

logs: ## Suit les logs
	docker compose logs -f

shell: ## Shell dans le container web
	docker compose exec -u app web bash

db-shell: ## Client MariaDB
	docker compose exec db sh -c 'mariadb -u"$$MARIADB_USER" -p"$$MARIADB_PASSWORD" "$$MARIADB_DATABASE"'

tools: ## Démarre aussi Adminer (http://localhost:8081)
	docker compose --profile tools up -d

clean: ## Arrête et SUPPRIME aussi les volumes (perte des données)
	docker compose --profile tools down -v
