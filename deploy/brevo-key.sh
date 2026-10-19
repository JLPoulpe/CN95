#!/usr/bin/env bash
# Affiche en clair la configuration d'envoi de mails (clé API Brevo) de chaque environnement.
# Usage : deploy/brevo-key.sh [--masquer] [local] [cible...]
#   sans argument : l'environnement local (Docker) puis toutes les cibles de deploy/targets/*.conf
#   --masquer     : ne montre que les 4 derniers caractères des secrets
#
# La configuration est chiffrée en base avec la clé APP_SECRET de chaque environnement : la commande
# app:mail:afficher-config est donc lancée sur chacun. Pour une cible distante, elle doit avoir été déployée
# (deploy/deploy.sh <cible>). Le résultat contient un secret : ne pas le coller dans un ticket ou un chat.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OPTS=()
NAMES=()
for arg in "$@"; do
  case "$arg" in
    --masquer) OPTS+=("--masquer") ;;
    -*) echo "ERREUR : option inconnue : $arg" >&2; exit 2 ;;
    *) NAMES+=("$arg") ;;
  esac
done

if [[ ${#NAMES[@]} -eq 0 ]]; then
  NAMES=(local)
  for conf in "$ROOT"/deploy/targets/*.conf; do
    [[ -e "$conf" ]] && NAMES+=("$(basename "$conf" .conf)")
  done
fi

ECHECS=()
for name in "${NAMES[@]}"; do
  [[ "$name" =~ ^[A-Za-z0-9_-]+$ ]] || { echo "ERREUR : nom d'environnement invalide : $name" >&2; ECHECS+=("$name"); continue; }

  if [[ "$name" == "local" ]]; then
    echo "===== local (Docker) ====="
    (cd "$ROOT" && docker compose exec -T -u app web php bin/console app:mail:afficher-config "${OPTS[@]}") || ECHECS+=("$name")
    echo
    continue
  fi

  CONF="$ROOT/deploy/targets/$name.conf"
  if [[ ! -f "$CONF" ]]; then
    echo "ERREUR : $CONF introuvable." >&2; ECHECS+=("$name"); continue
  fi

  # Sous-shell : les variables de la cible ne fuient pas vers la suivante
  (
    # shellcheck disable=SC1090
    source "$CONF"
    : "${SSH_TARGET:?}" "${REMOTE_DIR:?}" "${PHP_CLI:=php8.4}"
    echo "===== $name ($SSH_TARGET) ====="
    # REMOTE_DIR peut contenir ~ : il est développé côté serveur.
    # Si le binaire PHP de la cible n'existe pas, on retombe sur « php » en le signalant.
    ssh "$SSH_TARGET" "cd $REMOTE_DIR && P=$PHP_CLI; if ! command -v \"\$P\" >/dev/null 2>&1; then echo \"ATTENTION : \$P introuvable sur ce serveur, utilisation de php (\$(php -v 2>/dev/null | head -n1)). Corriger PHP_CLI dans deploy/targets/$name.conf.\" >&2; P=php; fi; \"\$P\" bin/console app:mail:afficher-config --env=prod --no-interaction ${OPTS[*]:-}" \
      || { echo "Si la commande est inconnue, la déployer d'abord : deploy/deploy.sh $name" >&2; exit 1; }
  ) || ECHECS+=("$name")
  echo
done

if [[ ${#ECHECS[@]} -gt 0 ]]; then
  echo "Échec pour : ${ECHECS[*]}" >&2
  exit 1
fi
