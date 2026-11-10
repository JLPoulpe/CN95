#!/usr/bin/env bash
# Importe un calendrier de saison (CSV) dans le planning d'un ou plusieurs environnements.
# Usage : deploy/import-planning.sh <fichier.csv> [--dry-run] <local|cible> [cible...]
#
#   --dry-run : valide le fichier et affiche le nombre de semaines/créneaux à créer, sans rien écrire ni demander de confirmation
#   Les environnements doivent être nommés explicitement : « local » (Docker) ou une cible de deploy/targets/*.conf.
#
# Format du fichier : Date;Jour;PN1;PE40;PN2;PN3;Événement / remarque (voir PlanningCsvImporter).
# Prérequis sur chaque environnement : aptitudes (N1, N2, PE40), activités (BLOC, PMT, APNEE, THEORIE…) et lieux d'activité.
# Une semaine déjà présente n'est jamais modifiée : le script est relançable.
# La commande app:import:planning doit avoir été déployée sur la cible (deploy/deploy.sh <cible>).
# Le fichier est copié temporairement sur le serveur (var/) et supprimé à la fin.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FICHIER=""
DRY_RUN=0
NAMES=()

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run) DRY_RUN=1; shift ;;
    -*) echo "ERREUR : option inconnue : $1" >&2; exit 2 ;;
    *) if [[ -z "$FICHIER" ]]; then FICHIER="$1"; else NAMES+=("$1"); fi; shift ;;
  esac
done

[[ -n "$FICHIER" && -f "$FICHIER" ]] || { echo "Usage : $0 <fichier.csv> [--dry-run] <local|cible> [cible...]" >&2; exit 2; }
[[ ${#NAMES[@]} -gt 0 ]] || { echo "ERREUR : indiquer au moins un environnement (local ou une cible de deploy/targets/)." >&2; exit 2; }

for name in "${NAMES[@]}"; do
  if [[ "$name" != "local" && ! "$name" =~ ^[A-Za-z0-9_-]+$ ]]; then
    echo "ERREUR : nom d'environnement invalide : $name" >&2; exit 2
  fi
  if [[ "$name" != "local" && ! -f "$ROOT/deploy/targets/$name.conf" ]]; then
    echo "ERREUR : deploy/targets/$name.conf introuvable." >&2; exit 2
  fi
done

if [[ $DRY_RUN -eq 0 ]]; then
  echo "Import de $FICHIER sur : ${NAMES[*]}"
  echo "Des semaines et des créneaux de planning vont être créés (les semaines existantes sont conservées)."
  read -r -p "Pour confirmer, tapez IMPORTER : " REPONSE
  if [[ "$REPONSE" != "IMPORTER" ]]; then
    echo "Import annulé."
    exit 0
  fi
fi

CMD_OPTS=(app:import:planning)
if [[ $DRY_RUN -eq 1 ]]; then CMD_OPTS+=(--dry-run); else CMD_OPTS+=(--force); fi
CMD_OPTS+=(--no-interaction)
TMP_NAME="import_planning_$$.csv"

ECHECS=()
for name in "${NAMES[@]}"; do
  if [[ "$name" == "local" ]]; then
    echo "===== local (Docker) ====="
    (
      cd "$ROOT" || exit 1
      docker compose exec -T -u app web sh -c "cat > /tmp/$TMP_NAME" < "$FICHIER" || exit 1
      docker compose exec -T -u app web php bin/console "${CMD_OPTS[@]}" "/tmp/$TMP_NAME"
      RC=$?
      docker compose exec -T -u app web rm -f "/tmp/$TMP_NAME"
      exit $RC
    ) || ECHECS+=("$name")
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
    ssh "$SSH_TARGET" "cat > $REMOTE_DIR/var/$TMP_NAME && chmod 600 $REMOTE_DIR/var/$TMP_NAME" < "$FICHIER" || exit 1
    ssh "$SSH_TARGET" "cd $REMOTE_DIR && P=$PHP_CLI; if ! command -v \"\$P\" >/dev/null 2>&1; then echo \"ATTENTION : \$P introuvable sur ce serveur, utilisation de php. Corriger PHP_CLI dans deploy/targets/$name.conf.\" >&2; P=php; fi; \"\$P\" bin/console ${CMD_OPTS[*]} var/$TMP_NAME --env=prod; RC=\$?; rm -f var/$TMP_NAME; exit \$RC"
    RC=$?
    [[ $RC -eq 0 ]] || echo "Si la commande est inconnue, la déployer d'abord : deploy/deploy.sh $name" >&2
    exit $RC
  ) || ECHECS+=("$name")
  echo
done

if [[ ${#ECHECS[@]} -gt 0 ]]; then
  echo "Échec pour : ${ECHECS[*]}" >&2
  exit 1
fi
echo "Terminé sur : ${NAMES[*]}"
