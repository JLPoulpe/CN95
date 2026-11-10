#!/usr/bin/env bash
# Correctif planning : ajoute l'aptitude PN1 aux créneaux déjà importés, d'après la colonne PN1 du calendrier.
# Usage : deploy/corriger-pn1.sh [--dry-run] [--fichier <csv>] <local|cible> [cible...]
#
#   --dry-run       : compte les créneaux concernés sans rien écrire (à faire en premier)
#   --fichier <csv> : calendrier à utiliser (défaut : import/calendrier_saison_2026_2027_libelles.csv)
#   Les environnements doivent être nommés explicitement : « local » (Docker) ou une cible de deploy/targets/*.conf.
#
# Idempotent : relancer ne change rien. Ne crée ni ne supprime aucun créneau.
# Le CSV est envoyé sur l'entrée standard : rien n'est copié sur le serveur.
# La commande app:planning:corriger-pn1 doit avoir été déployée sur la cible (deploy/deploy.sh <cible>).
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FICHIER="$ROOT/import/calendrier_saison_2026_2027_libelles.csv"
DRY_RUN=0
NAMES=()

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run) DRY_RUN=1; shift ;;
    --fichier)
      [[ $# -ge 2 ]] || { echo "ERREUR : --fichier attend un chemin." >&2; exit 2; }
      FICHIER="$2"; shift 2 ;;
    --fichier=*) FICHIER="${1#--fichier=}"; shift ;;
    -*) echo "ERREUR : option inconnue : $1" >&2; exit 2 ;;
    *) NAMES+=("$1"); shift ;;
  esac
done

[[ -f "$FICHIER" ]] || { echo "ERREUR : calendrier introuvable : $FICHIER" >&2; exit 2; }
[[ ${#NAMES[@]} -gt 0 ]] || { echo "ERREUR : indiquer au moins un environnement (local ou une cible de deploy/targets/)." >&2; exit 2; }

for name in "${NAMES[@]}"; do
  if [[ "$name" != "local" && ! "$name" =~ ^[A-Za-z0-9_-]+$ ]]; then
    echo "ERREUR : nom d'environnement invalide : $name" >&2; exit 2
  fi
  if [[ "$name" != "local" && ! -f "$ROOT/deploy/targets/$name.conf" ]]; then
    echo "ERREUR : deploy/targets/$name.conf introuvable." >&2; exit 2
  fi
done

CMD_OPTS=(app:planning:corriger-pn1 --fichier=- --no-interaction)
if [[ $DRY_RUN -eq 1 ]]; then
  CMD_OPTS+=(--dry-run)
fi

ECHECS=()
for name in "${NAMES[@]}"; do
  if [[ "$name" == "local" ]]; then
    echo "===== local (Docker) ====="
    (cd "$ROOT" && docker compose exec -T -u app web php bin/console "${CMD_OPTS[@]}" < "$FICHIER") || ECHECS+=("$name")
    echo
    continue
  fi

  # Sous-shell : les variables de la cible ne fuient pas vers la suivante
  (
    # shellcheck disable=SC1090
    source "$ROOT/deploy/targets/$name.conf"
    : "${SSH_TARGET:?}" "${REMOTE_DIR:?}" "${PHP_CLI:=php8.4}"
    echo "===== $name ($SSH_TARGET) ====="
    if [[ $DRY_RUN -eq 0 ]]; then
      echo "Ajout de l'aptitude PN1 aux créneaux de $name à partir de : $FICHIER"
      read -r -p "Pour confirmer, tapez CORRIGER : " REPONSE
      if [[ "$REPONSE" != "CORRIGER" ]]; then
        echo "Correctif annulé sur $name."
        exit 0
      fi
    fi
    ssh "$SSH_TARGET" "cd $REMOTE_DIR && P=$PHP_CLI; if ! command -v \"\$P\" >/dev/null 2>&1; then echo \"ATTENTION : \$P introuvable sur ce serveur, utilisation de php. Corriger PHP_CLI dans deploy/targets/$name.conf.\" >&2; P=php; fi; \"\$P\" bin/console ${CMD_OPTS[*]} --env=prod" < "$FICHIER" \
      || { echo "Si la commande est inconnue, la déployer d'abord : deploy/deploy.sh $name" >&2; exit 1; }
  ) || ECHECS+=("$name")
  echo
done

if [[ ${#ECHECS[@]} -gt 0 ]]; then
  echo "Échec pour : ${ECHECS[*]}" >&2
  exit 1
fi
echo "Terminé sur : ${NAMES[*]}"
