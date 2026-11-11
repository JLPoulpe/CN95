#!/usr/bin/env bash
# Pilote les conteneurs Docker locaux (web + db). Chaque action a sa cible make du même nom.
# Usage : bash deploy/docker.sh <up|down|restart|build|ps|logs|shell|db-shell|tools|clean>
#
#   up       : démarre web + db                                  (make up)
#   down     : arrête, données conservées                        (make down)
#   restart  : arrête puis redémarre                             (make restart)
#   build    : reconstruit l'image et démarre                    (make build)
#   ps       : état des conteneurs                               (make ps)
#   logs     : suit les logs (Ctrl+C pour quitter)               (make logs)
#   shell    : shell dans le conteneur web                       (make shell)
#   db-shell : client MariaDB                                    (make db-shell)
#   tools    : démarre aussi Adminer (http://localhost:8081)     (make tools)
#   clean    : arrête et SUPPRIME les volumes (base locale perdue), confirmation SUPPRIMER (make clean)
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 1

USAGE="usage : bash deploy/docker.sh <up|down|restart|build|ps|logs|shell|db-shell|tools|clean>"

case "${1:-}" in
  up)       docker compose up -d ;;
  down)     docker compose down ;;
  restart)  docker compose down && docker compose up -d ;;
  build)    docker compose up -d --build ;;
  ps)       docker compose ps ;;
  logs)     docker compose logs -f ;;
  shell)    docker compose exec -u app web bash ;;
  db-shell) docker compose exec db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' ;;
  tools)    docker compose --profile tools up -d ;;
  clean)
    echo "Suppression des conteneurs ET des volumes : la base locale sera perdue."
    read -r -p "Pour confirmer, tapez SUPPRIMER : " REPONSE
    if [[ "$REPONSE" != "SUPPRIMER" ]]; then
      echo "Suppression annulée."
      exit 0
    fi
    docker compose --profile tools down -v ;;
  -h|--help|"") echo "$USAGE"; [[ -n "${1:-}" ]] || exit 2 ;;
  *) echo "ERREUR : action inconnue : $1" >&2; echo "$USAGE" >&2; exit 2 ;;
esac
