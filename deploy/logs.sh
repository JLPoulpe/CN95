#!/usr/bin/env bash
# Lit les logs Symfony du serveur via SSH.
# Usage : deploy/logs.sh [cible] [errors|tail|all] [N]
#   errors (défaut) : les N dernières erreurs (500 inclus), N=5
#   tail            : suit le log en direct (Ctrl+C pour quitter)
#   all             : les N dernières lignes brutes, N=100
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="prod"; MODE="errors"; N=""
for arg in "$@"; do
  case "$arg" in
    errors|tail|all) MODE="$arg" ;;
    ''|*[!0-9]*) TARGET="$arg" ;;
    *) N="$arg" ;;
  esac
done

CONF="$ROOT/deploy/targets/$TARGET.conf"
[[ -f "$CONF" ]] || { echo "Cible inconnue : $CONF" >&2; exit 1; }
# shellcheck disable=SC1090
source "$CONF"

LOGDIR="$REMOTE_DIR/var/log"
case "$MODE" in
  errors) REMOTE="grep -h -E '\"level_name\":\"(ERROR|CRITICAL|ALERT|EMERGENCY)\"' $LOGDIR/prod-*.log | tail -n ${N:-5}" ;;
  tail)   REMOTE="tail -n 20 -F \$(ls -t $LOGDIR/prod-*.log | head -n1)" ;;
  all)    REMOTE="cat \$(ls -t $LOGDIR/prod-*.log | head -n1) | tail -n ${N:-100}" ;;
esac

ssh "$SSH_TARGET" "$REMOTE" | if command -v jq >/dev/null; then
  jq -r '"\(.datetime) [\(.level_name)] \(.message)\n\(.context.exception // "" | tostring | .[0:1500])\n"' 2>/dev/null || cat
else
  cat
fi
