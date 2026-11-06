#!/usr/bin/env bash
# Envoie le mail d'activation de compte (celui de l'ajout d'un adhérent) depuis un environnement.
# Usage : deploy/envoyer-activation.sh (--email <adresse> | --tous) [--dry-run] <local|cible> [cible...]
#
#   --email <adresse> : un seul destinataire (pour tester sur soi-même)
#   --tous            : tous les comptes dont l'activation est en attente (confirmation ENVOYER demandée)
#   --dry-run         : liste les destinataires sans rien envoyer
#   Les environnements doivent être nommés explicitement : « local » (Docker) ou une cible de deploy/targets/*.conf.
#
# Un nouveau lien d'activation (valable 7 jours) est généré à chaque envoi : le précédent n'est plus valable.
# Les comptes déjà activés ne reçoivent jamais de mail. Le lien pointe vers l'URL de l'environnement (DEFAULT_URI).
# ATTENTION : la base locale peut contenir la vraie configuration Brevo et de vraies adresses.
# La commande app:user:envoyer-activation doit avoir été déployée sur la cible (deploy/deploy.sh <cible>).
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
EMAIL=""
TOUS=0
DRY_RUN=0
NAMES=()

while [[ $# -gt 0 ]]; do
  case "$1" in
    --email)
      [[ $# -ge 2 ]] || { echo "ERREUR : --email attend une adresse." >&2; exit 2; }
      EMAIL="$2"; shift 2 ;;
    --email=*) EMAIL="${1#--email=}"; shift ;;
    --tous) TOUS=1; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    -*) echo "ERREUR : option inconnue : $1" >&2; exit 2 ;;
    *) NAMES+=("$1"); shift ;;
  esac
done

if [[ $TOUS -eq 1 && -n "$EMAIL" ]] || [[ $TOUS -eq 0 && -z "$EMAIL" ]]; then
  echo "ERREUR : choisir exactement une cible : --email <adresse> ou --tous." >&2; exit 2
fi
if [[ -n "$EMAIL" && ! "$EMAIL" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]]; then
  echo "ERREUR : adresse email invalide : $EMAIL" >&2; exit 2
fi
[[ ${#NAMES[@]} -gt 0 ]] || { echo "ERREUR : indiquer au moins un environnement (local ou une cible de deploy/targets/)." >&2; exit 2; }

for name in "${NAMES[@]}"; do
  if [[ "$name" != "local" && ! "$name" =~ ^[A-Za-z0-9_-]+$ ]]; then
    echo "ERREUR : nom d'environnement invalide : $name" >&2; exit 2
  fi
  if [[ "$name" != "local" && ! -f "$ROOT/deploy/targets/$name.conf" ]]; then
    echo "ERREUR : deploy/targets/$name.conf introuvable." >&2; exit 2
  fi
done

CMD_OPTS=(app:user:envoyer-activation --no-interaction)
if [[ -n "$EMAIL" ]]; then CMD_OPTS+=("--email=$EMAIL"); else CMD_OPTS+=(--tous); fi

if [[ $DRY_RUN -eq 1 ]]; then
  CMD_OPTS+=(--dry-run)
elif [[ $TOUS -eq 1 ]]; then
  echo "Envoi du mail d'activation à TOUS les comptes en attente sur : ${NAMES[*]}"
  read -r -p "Pour confirmer, tapez ENVOYER : " REPONSE
  if [[ "$REPONSE" != "ENVOYER" ]]; then
    echo "Envoi annulé."
    exit 0
  fi
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
    ssh "$SSH_TARGET" "cd $REMOTE_DIR && P=$PHP_CLI; if ! command -v \"\$P\" >/dev/null 2>&1; then echo \"ATTENTION : \$P introuvable sur ce serveur, utilisation de php. Corriger PHP_CLI dans deploy/targets/$name.conf.\" >&2; P=php; fi; \"\$P\" bin/console ${CMD_OPTS[*]} --env=prod" \
      || { echo "Si la commande est inconnue, la déployer d'abord : deploy/deploy.sh $name" >&2; exit 1; }
  ) || ECHECS+=("$name")
  echo
done

if [[ ${#ECHECS[@]} -gt 0 ]]; then
  echo "Échec pour : ${ECHECS[*]}" >&2
  exit 1
fi
echo "Terminé sur : ${NAMES[*]}"
