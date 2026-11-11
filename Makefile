.PHONY: help menu up down restart build logs ps shell db-shell tools clean cache-clear brevo-key purge import-adherents import-aptitudes import-lieux import-planning corriger-pn1 envoyer-activation deploy logs-serveur

# Chaque cible make appelle le script bash de deploy/ avec les mêmes arguments :
#   make <cible> VAR=...   ≡   bash deploy/<script>.sh ...   (table complète dans deploy/README.md)
# Plusieurs environnements : ENVS="local ionos" · Cible unique : TARGET=prod · Simulation : DRY_RUN=1
# Menu interactif qui regroupe tout : make menu   ≡   bash deploy/menu.sh

TARGET ?= prod
export TARGET

help: ## Affiche l'aide
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-10s %s\n", $$1, $$2}'

menu: ## Menu interactif (imports, purges, mails, déploiement, logs, Docker)
	bash deploy/menu.sh

up: ## Démarre les containers (web + db)
	bash deploy/docker.sh up

build: ## Reconstruit l'image et démarre
	bash deploy/docker.sh build

down: ## Arrête et supprime les containers (données conservées)
	bash deploy/docker.sh down

restart: ## Redémarre les containers
	bash deploy/docker.sh restart

ps: ## Liste l'état des containers
	bash deploy/docker.sh ps

logs: ## Suit les logs Docker
	bash deploy/docker.sh logs

shell: ## Shell dans le container web
	bash deploy/docker.sh shell

db-shell: ## Client MariaDB
	bash deploy/docker.sh db-shell

tools: ## Démarre aussi Adminer (http://localhost:8081)
	bash deploy/docker.sh tools

clean: ## Arrête et SUPPRIME aussi les volumes (perte des données, confirmation SUPPRIMER)
	bash deploy/docker.sh clean

deploy: ## Déploie une cible (TARGET=prod, DRY_RUN=1 pour simuler, OPTIONS="--skip-db --no-tag")
	bash deploy/deploy.sh "$(TARGET)" $(if $(DRY_RUN),--dry-run) $(OPTIONS)

logs-serveur: ## Logs Symfony d'une cible (TARGET=prod, MODE=errors|tail|all, N=5)
	bash deploy/logs.sh "$(TARGET)" $(MODE) $(N)

cache-clear: ## Supprime le cache distant (TARGET=prod, fichier deploy/targets/<cible>.conf)
	bash deploy/cache-clear.sh "$(TARGET)"

brevo-key: ## Affiche la clé API Brevo de chaque environnement (ENVS="local ionos cn95", MASQUER=1 pour masquer)
	bash deploy/brevo-key.sh $(if $(MASQUER),--masquer) $(ENVS)

purge: ## Purge la base de chaque environnement (NIVEAU=totale|configuration|planning, DRY_RUN=1 pour simuler, ENVS="local ionos cn95")
	bash deploy/purge.sh --niveau "$(NIVEAU)" $(if $(DRY_RUN),--dry-run) $(ENVS)

import-adherents: ## Importe un CSV d'adhérents (FICHIER=import/adherents_2027.csv ENVS="local ionos", DRY_RUN=1 pour simuler)
	bash deploy/import-adherents.sh "$(FICHIER)" $(if $(DRY_RUN),--dry-run) $(ENVS)

import-aptitudes: ## Importe un CSV d'aptitudes (FICHIER=import/referentiel_aptitudes_ffessm_verifie.csv ENVS="local ionos", DRY_RUN=1 pour simuler)
	bash deploy/import-aptitudes.sh "$(FICHIER)" $(if $(DRY_RUN),--dry-run) $(ENVS)

import-lieux: ## Importe un CSV de lieux d'activité (FICHIER=import/lieux_activite.csv ENVS="local cn95", DRY_RUN=1 pour simuler)
	bash deploy/import-lieux.sh "$(FICHIER)" $(if $(DRY_RUN),--dry-run) $(ENVS)

import-planning: ## Importe un calendrier de saison CSV dans le planning (FICHIER=import/calendrier.csv ENVS="local ionos", DRY_RUN=1 pour simuler)
	bash deploy/import-planning.sh "$(FICHIER)" $(if $(DRY_RUN),--dry-run) $(ENVS)

corriger-pn1: ## Ajoute PN1 aux créneaux déjà importés (FICHIER=import/calendrier.csv ENVS="local ionos", DRY_RUN=1 pour simuler)
	bash deploy/corriger-pn1.sh $(if $(DRY_RUN),--dry-run) $(if $(FICHIER),--fichier "$(FICHIER)") $(ENVS)

envoyer-activation: ## Envoie le mail d'activation (EMAIL=adresse ou TOUS=1, ENVS="local ionos", DRY_RUN=1 pour simuler)
	bash deploy/envoyer-activation.sh $(if $(EMAIL),--email "$(EMAIL)") $(if $(TOUS),--tous) $(if $(DRY_RUN),--dry-run) $(ENVS)
