#!/usr/bin/env bash
# Purge la base de chaque environnement (local puis cibles de deploy/targets/*.conf).
# Usage : deploy/purge.sh --niveau totale|configuration|planning [--dry-run] [local] [cible...]
#
#   --niveau totale        : supprime tout sauf les administrateurs, les rôles et leurs permissions,
#                            et la configuration des mails. Aptitudes et lieux d'activité sont aussi supprimés.
#   --niveau configuration : idem, mais garde aussi « Configuration de l'outil » (aptitudes, lieux d'activité,
#                            configuration des mails, permissions).
#   --niveau planning      : supprime uniquement les semaines et créneaux du planning. Les lieux d'activité
#                            (lignes d'eau) et tout le reste sont conservés.
#   --dry-run              : affiche ce qui serait supprimé, sans rien supprimer ni demander de confirmation
#   sans cible             : l'environnement local puis toutes les cibles de deploy/targets/*.conf
#
# La commande app:purge doit avoir été déployée sur la cible (deploy/deploy.sh <cible>).
# La suppression est irréversible : une confirmation unique (taper PURGER) est demandée avant de commencer.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
NIVEAU=""
DRY_RUN=0
NAMES=()

while [[ $# -gt 0 ]]; do
  case "$1" in
    --niveau)
      [[ $# -ge 2 ]] || { echo "ERREUR : --niveau attend une valeur (totale, configuration ou planning)." >&2; exit 2; }
      NIVEAU="$2"; shift 2 ;;
    --niveau=*) NIVEAU="${1#--niveau=}"; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    -*) echo "ERREUR : option inconnue : $1" >&2; exit 2 ;;
    *) NAMES+=("$1"); shift ;;
  esac
done

case "$NIVEAU" in
  totale|configuration|planning) ;;
  *) echo "ERREUR : --niveau doit valoir totale, configuration ou planning (reçu : '${NIVEAU:-vide}')." >&2; exit 2 ;;
esac

if [[ ${#NAMES[@]} -eq 0 ]]; then
  NAMES=(local)
  for conf in "$ROOT"/deploy/targets/*.conf; do
    [[ -e "$conf" ]] && NAMES+=("$(basename "$conf" .conf)")
  done
fi

for name in "${NAMES[@]}"; do
  if [[ "$name" != "local" && ! "$name" =~ ^[A-Za-z0-9_-]+$ ]]; then
    echo "ERREUR : nom d'environnement invalide : $name" >&2; exit 2
  fi
  if [[ "$name" != "local" && ! -f "$ROOT/deploy/targets/$name.conf" ]]; then
    echo "ERREUR : deploy/targets/$name.conf introuvable." >&2; exit 2
  fi
done

if [[ $DRY_RUN -eq 0 ]]; then
  echo "Purge (niveau : $NIVEAU) sur : ${NAMES[*]}"
  echo "Les données supprimées ne peuvent pas être restaurées par cet outil."
  read -r -p "Pour confirmer, tapez PURGER : " REPONSE
  if [[ "$REPONSE" != "PURGER" ]]; then
    echo "Purge annulée."
    exit 0
  fi
fi

CMD_OPTS=(app:purge "--niveau=$NIVEAU" --no-interaction)
if [[ $DRY_RUN -eq 1 ]]; then
  CMD_OPTS+=(--dry-run)
else
  CMD_OPTS+=(--force)
fi

ECHECS=()
for name in "${NAMES[@]}"; do
  if [[ "$name" == "local" ]]; then
    echo "===== local (Docker) ====="
    (cd "$ROOT" && docker compose exec -T -u app web php bin/console "${CMD_OPTS[@]}") || ECHECS+=("$name")
    echo
    continue
  fi

  # Sous-shell : les variables de la cible ne fuient pas vers la suivante
  (
    # shellcheck disable=SC1090
    source "$ROOT/deploy/targets/$name.conf"
    : "${SSH_TARGET:?}" "${REMOTE_DIR:?}" "${PHP_CLI:=php8.4}"
    echo "===== $name ($SSH_TARGET) ====="
    # REMOTE_DIR peut contenir ~ : il est développé côté serveur.
    # Si le binaire PHP de la cible n'existe pas, on retombe sur « php » en le signalant.
    ssh "$SSH_TARGET" "cd $REMOTE_DIR && P=$PHP_CLI; if ! command -v \"\$P\" >/dev/null 2>&1; then echo \"ATTENTION : \$P introuvable sur ce serveur, utilisation de php (\$(php -v 2>/dev/null | head -n1)). Corriger PHP_CLI dans deploy/targets/$name.conf.\" >&2; P=php; fi; \"\$P\" bin/console ${CMD_OPTS[*]} --env=prod" \
      || { echo "Si la commande est inconnue, la déployer d'abord : deploy/deploy.sh $name" >&2; exit 1; }
  ) || ECHECS+=("$name")
  echo
done

if [[ ${#ECHECS[@]} -gt 0 ]]; then
  echo "Échec pour : ${ECHECS[*]}" >&2
  exit 1
fi
echo "Terminé sur : ${NAMES[*]}"
