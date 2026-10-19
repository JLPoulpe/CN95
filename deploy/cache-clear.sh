#!/usr/bin/env bash
# Supprime le cache Symfony du serveur via SSH.
# Usage : bash deploy/cache-clear.sh [cible] (défaut : prod)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="${1:-prod}"

die() { echo "ERREUR : $*" >&2; exit 1; }

[[ $# -le 1 ]] || die "usage : bash deploy/cache-clear.sh [cible]"
[[ "$TARGET" =~ ^[A-Za-z0-9_-]+$ ]] || die "nom de cible invalide : $TARGET"
CONF="$ROOT/deploy/targets/$TARGET.conf"
[[ -f "$CONF" ]] || die "$CONF introuvable (copier deploy/targets/prod.conf.example)."
# shellcheck disable=SC1090
source "$CONF"
: "${SSH_TARGET:?}" "${REMOTE_DIR:?}"

echo "==> Suppression du cache : $TARGET ($SSH_TARGET)"
# Renommage puis suppression : le site peut recréer var/cache pendant la suppression
# (rm -rf échouait alors avec « Le dossier n'est pas vide »). Une fois renommé,
# l'ancien dossier n'est plus utilisé et les requêtes suivantes reconstruisent var/cache.
STAMP="$(date +%s)"
ssh "$SSH_TARGET" "cd $REMOTE_DIR && if [ -e var/cache ]; then mv var/cache var/cache.old-$STAMP && rm -rf -- var/cache.old-$STAMP; fi" \
  || die "suppression du cache impossible sur la cible $TARGET."
