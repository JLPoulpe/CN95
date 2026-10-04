#!/usr/bin/env bash
# Déploiement CN95 : build local, rsync vers l'hébergement, script web de migration, tag git.
# Usage : deploy/deploy.sh [cible] [--dry-run] [--first-run] [--skip-db] [--seed] [--no-tag] [--init-server]
# cible = deploy/targets/<cible>.conf (défaut : prod)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEPLOY_DIR="$ROOT/deploy"
BUILD="$DEPLOY_DIR/.build"
TARGET="prod"

DRY_RUN=0; FIRST_RUN=0; SKIP_DB=0; SEED=0; NO_TAG=0; INIT_SERVER=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --first-run) FIRST_RUN=1 ;;
    --skip-db) SKIP_DB=1 ;;
    --seed) SEED=1 ;;
    --no-tag) NO_TAG=1 ;;
    --init-server) INIT_SERVER=1 ;;
    -h|--help) sed -n '2,4p' "$0"; exit 0 ;;
    -*) echo "Option inconnue : $arg" >&2; exit 2 ;;
    *) TARGET="$arg" ;;
  esac
done

die() { echo "ERREUR : $*" >&2; exit 1; }
info() { echo "==> $*"; }

[[ "$TARGET" =~ ^[A-Za-z0-9_-]+$ ]] || die "nom de cible invalide : $TARGET"
CONF="$DEPLOY_DIR/targets/$TARGET.conf"
TAG_PREFIX="deploy/$TARGET/"
[[ -f "$CONF" ]] || die "$CONF introuvable (copier deploy/targets/prod.conf.example)."
# shellcheck disable=SC1090
source "$CONF"
: "${SSH_TARGET:?}" "${REMOTE_DIR:?}" "${PUBLIC_URL:?}" "${DB_HOST:?}" "${DB_USER:?}"
: "${DB_PORT:=3306}" "${DB_VERSION:=11.8.0-MariaDB}" "${BRANCH:=master}" "${BUILD_IMAGE:=cn95-web}" "${DB_NAME:=}"
info "Cible : $TARGET ($SSH_TARGET)"

for cmd in git rsync ssh docker openssl; do
  command -v "$cmd" >/dev/null || die "commande requise absente : $cmd"
done

cd "$ROOT"

# --- 1. Vérifications git -----------------------------------------------------
[[ "$(git rev-parse --abbrev-ref HEAD)" == "$BRANCH" ]] || die "il faut être sur la branche $BRANCH."
[[ -z "$(git status --porcelain --untracked-files=no)" ]] || die "arbre git modifié : commitez d'abord (le tag doit refléter ce qui est envoyé)."

# --- 2. Différences depuis le dernier tag ------------------------------------
LAST_TAG="$(git tag --list "${TAG_PREFIX}*" --sort=-creatordate | head -n1 || true)"
if [[ -z "$LAST_TAG" || $FIRST_RUN -eq 1 ]]; then
  info "Déploiement complet (aucun tag précédent ou --first-run)."
  CHANGED_FILES="$(git ls-tree -r --name-only HEAD -- app/)"
  NEW_MIGRATIONS="$(git ls-tree -r --name-only HEAD -- app/migrations/ | grep -E 'Version[0-9]+\.php$' || true)"
  FULL=1
else
  info "Dernier déploiement : $LAST_TAG"
  CHANGED_FILES="$(git diff --name-status "$LAST_TAG" HEAD -- app/)"
  NEW_MIGRATIONS="$(git diff --name-only --diff-filter=AM "$LAST_TAG" HEAD -- app/migrations/ | grep -E 'Version[0-9]+\.php$' || true)"
  FULL=0
fi

echo "--- Fichiers custom concernés ---"
echo "${CHANGED_FILES:-(aucun)}"
echo "--- Migrations à jouer ---"
echo "${NEW_MIGRATIONS:-(aucune)}"

if [[ $FULL -eq 0 && -z "$CHANGED_FILES" ]]; then
  info "Rien à déployer depuis $LAST_TAG."
  exit 0
fi
[[ -z "$NEW_MIGRATIONS" ]] && SKIP_DB=1

# --- 3. Build dans le staging -------------------------------------------------
info "Construction dans $BUILD"
rm -rf "$BUILD"; mkdir -p "$BUILD"
git archive HEAD app | tar -x -C "$BUILD"
APP="$BUILD/app"
rm -rf "$APP/tests" "$APP/phpunit.dist.xml" "$APP/.env.dev" "$APP/.env.test" "$APP/.phpunit.cache"
find "$APP" -name .gitignore -delete

# .env minimal (les secrets vont dans .env.local sur le serveur)
printf 'APP_ENV=prod\nAPP_DEBUG=0\n' > "$APP/.env"

DOCKER_RUN=(docker run --rm -u "$(id -u):$(id -g)" -v "$APP":/work -w /work
  -e COMPOSER_HOME=/tmp/composer -e HOME=/tmp
  -e APP_ENV=prod -e APP_DEBUG=0 -e APP_SECRET=build
  -e "DATABASE_URL=mysql://u:p@127.0.0.1:3306/db?serverVersion=11.4.0-MariaDB&charset=utf8mb4"
  "$BUILD_IMAGE")

info "composer install --no-dev"
"${DOCKER_RUN[@]}" composer install --no-dev --optimize-autoloader --no-scripts --no-interaction
info "Assets (importmap:install + asset-map:compile)"
"${DOCKER_RUN[@]}" php bin/console importmap:install --no-interaction
"${DOCKER_RUN[@]}" php bin/console asset-map:compile --no-interaction
rm -rf "$APP/var"

cp "$DEPLOY_DIR/templates/htaccess" "$APP/public/.htaccess"

# --- 4. Script web de migration ----------------------------------------------
MIGRATE_URL=""
if [[ $SKIP_DB -eq 0 ]]; then
  TOKEN="$(openssl rand -hex 32)"
  MIGRATE_NAME="_migrate_${TOKEN:0:16}.php"
  MIGRATIONS_LIST="$(echo "$NEW_MIGRATIONS" | xargs -n1 basename | tr '\n' ' ')"
  sed -e "s/__TOKEN__/$TOKEN/" \
      -e "s/__WITH_SEED__/$([[ $SEED -eq 1 ]] && echo true || echo false)/" \
      -e "s/__MIGRATIONS__/${MIGRATIONS_LIST% }/" \
      "$DEPLOY_DIR/templates/migrate.php.tpl" > "$APP/public/$MIGRATE_NAME"
  MIGRATE_URL="${PUBLIC_URL%/}/$MIGRATE_NAME?token=$TOKEN"
fi

# --- 5. Synchronisation -------------------------------------------------------
RSYNC=(rsync -az --delete-after --itemize-changes
  --exclude=/var/ --exclude=/.env.local --exclude=/.env.local.php --exclude=/.env
  -e ssh)
[[ $DRY_RUN -eq 1 ]] && RSYNC+=(--dry-run)

init_server() {
  # Crée REMOTE_DIR/.env.local (chmod 600). Le mot de passe DB est saisi ici, jamais stocké en local.
  [[ -n "$DB_NAME" ]] || read -r -p "Nom de la base de données : " DB_NAME
  local pass pass_enc secret
  read -r -s -p "Mot de passe DB de $DB_USER@$DB_HOST : " pass; echo
  [[ -n "$pass" ]] || die "mot de passe vide."
  pass_enc="$(printf '%s' "$pass" | docker run --rm -i "$BUILD_IMAGE" php -r 'echo rawurlencode(stream_get_contents(STDIN));')"
  unset pass
  # doctrine.yaml utilise %env(resolve:DATABASE_URL)% : chaque % doit être doublé
  pass_enc="${pass_enc//%/%%}"
  secret="$(openssl rand -hex 32)"
  ssh "$SSH_TARGET" "mkdir -p $REMOTE_DIR/var && umask 077 && cat > $REMOTE_DIR/.env.local && chmod 600 $REMOTE_DIR/.env.local" <<EOF
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=$secret
DATABASE_URL='mysql://$DB_USER:$pass_enc@$DB_HOST:$DB_PORT/$DB_NAME?serverVersion=$DB_VERSION&charset=utf8mb4'
DEFAULT_URI=${PUBLIC_URL%/}
MESSENGER_TRANSPORT_DSN=sync://
EOF
  unset pass_enc secret
  info "Version PHP du serveur :"
  ssh "$SSH_TARGET" "php -v | head -n1" || echo "ATTENTION : php CLI introuvable côté serveur (le script web utilisera la version choisie dans le panneau)."
}

info "Vérification de la configuration serveur"
if [[ $DRY_RUN -eq 1 ]]; then
  if [[ $INIT_SERVER -eq 1 ]] || ! ssh "$SSH_TARGET" "test -f $REMOTE_DIR/.env.local"; then
    info "--dry-run : .env.local serait créé (mot de passe DB demandé), rien n'est écrit."
  fi
elif [[ $INIT_SERVER -eq 1 ]] || ! ssh "$SSH_TARGET" "test -f $REMOTE_DIR/.env.local"; then
  info "Initialisation du serveur (.env.local)"
  init_server
else
  ssh "$SSH_TARGET" "mkdir -p $REMOTE_DIR/var"
fi

info "rsync vers $SSH_TARGET:$REMOTE_DIR"
"${RSYNC[@]}" "$APP/" "$SSH_TARGET:$REMOTE_DIR/"
# .env minimal : envoyé seulement s'il n'existe pas encore
rsync -az --ignore-existing $([[ $DRY_RUN -eq 1 ]] && echo --dry-run) -e ssh "$APP/.env" "$SSH_TARGET:$REMOTE_DIR/.env"

if [[ $DRY_RUN -eq 1 ]]; then
  info "--dry-run : rien envoyé, aucun tag créé."
  exit 0
fi

# --- 6. Migration web ---------------------------------------------------------
if [[ -n "$MIGRATE_URL" ]]; then
  echo
  info "Ouvrez cette URL pour mettre à jour la base (usage unique, le script se supprime) :"
  echo "    $MIGRATE_URL"
  echo
  read -r -p "Migration terminée avec succès ? [o/N] " ans
  [[ "$ans" =~ ^[oOyY]$ ]] || die "déploiement non finalisé : aucun tag créé. Relancez après correction."
fi

# --- 7. Tag -------------------------------------------------------------------
if [[ $NO_TAG -eq 0 ]]; then
  TAG="${TAG_PREFIX}$(date +%Y%m%d-%H%M%S)"
  git tag -a "$TAG" -m "Déploiement $(date '+%F %T')"
  info "Tag créé : $TAG"
  git push origin "$TAG" || echo "Push du tag impossible : à faire manuellement (git push origin $TAG)."
fi
info "Terminé."
