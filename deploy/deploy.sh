#!/usr/bin/env bash
# Déploiement CN95 : build local, rsync vers l'hébergement, script web de migration, tag git.
# Usage : deploy/deploy.sh [cible] [--dry-run] [--first-run] [--skip-db] [--seed] [--no-tag] [--init-server] [--create-admin]
# cible = deploy/targets/<cible>.conf (défaut : prod)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEPLOY_DIR="$ROOT/deploy"
BUILD="$DEPLOY_DIR/.build"
TARGET="prod"

DRY_RUN=0; FIRST_RUN=0; SKIP_DB=0; SEED=0; NO_TAG=0; INIT_SERVER=0; CREATE_ADMIN=0
for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --first-run) FIRST_RUN=1 ;;
    --skip-db) SKIP_DB=1 ;;
    --seed) SEED=1 ;;
    --no-tag) NO_TAG=1 ;;
    --init-server) INIT_SERVER=1 ;;
    --create-admin) CREATE_ADMIN=1 ;;
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
: "${PUBLIC_SUBPATH=/public}" "${DB_PORT:=3306}" "${DB_VERSION:=11.8.0-MariaDB}" "${BRANCH:=master}" "${BUILD_IMAGE:=cn95-web}" "${DB_NAME:=}" "${PHP_CLI:=php8.4}" "${ADMIN_USERNAME:=admin}"
info "Cible : $TARGET ($SSH_TARGET)"

create_admin() {
  # Mot de passe généré ici, transmis par stdin (jamais en argument), affiché une seule fois.
  local pw
  pw="$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)"
  info "Création du compte admin '$ADMIN_USERNAME' (app:seed, $PHP_CLI)"
  printf '%s\n' "$pw" | ssh "$SSH_TARGET" \
    "IFS= read -r ADMIN_PASSWORD; export ADMIN_PASSWORD; cd $REMOTE_DIR && $PHP_CLI bin/console app:seed --admin-username=$ADMIN_USERNAME --env=prod --no-interaction" \
    || die "création de l'admin impossible (migrations appliquées ? $PHP_CLI disponible ?)."
  echo
  echo "    Identifiant  : $ADMIN_USERNAME"
  echo "    Mot de passe : $pw   (affiché une seule fois ; ignoré si le compte existait déjà)"
  echo
}

generate_migration() {
  # Rejoue TOUTES les migrations déjà présentes (déployées + en attente) dans une
  # base MariaDB jetable, puis lance doctrine:migrations:diff contre les entités
  # actuelles. Idempotent : si une migration en attente couvre déjà l'état des
  # entités, rien n'est recréé ni supprimé (sinon chaque relance avant le tag
  # suivant régénérerait un nouveau fichier et committerait en boucle).
  local dbc i new_migration tmp_var
  info "Vérification des migrations (base jetable)"
  dbc="cn95_migrate_$$"
  # Sous $DEPLOY_DIR/.build (pas /tmp) : Docker en snap confine les montages
  # hors du home/projet, ce qui corrompt l'appartenance des fichiers montés.
  tmp_var="$DEPLOY_DIR/.build/migrate-var-$$"
  mkdir -p "$tmp_var"
  trap 'docker rm -f "$dbc" >/dev/null 2>&1 || true; rm -rf "$tmp_var"' EXIT RETURN

  docker run -d --rm --name "$dbc" \
      -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=baseline \
      mariadb:11 >/dev/null || die "impossible de démarrer la base jetable."

  info "Attente de la base jetable"
  for i in $(seq 1 30); do
    docker exec "$dbc" healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1 && break
    [[ $i -eq 30 ]] && die "la base jetable n'a pas démarré à temps."
    sleep 1
  done

  local RUN_TMP=(docker run --rm -u "$(id -u):$(id -g)"
    -v "$ROOT/app":/var/www/html -v "$tmp_var":/var/www/html/var
    -w /var/www/html --network "container:$dbc"
    -e APP_ENV=dev -e APP_SECRET=build
    -e "DATABASE_URL=mysql://root:root@127.0.0.1:3306/baseline?serverVersion=11.4.0-MariaDB&charset=utf8mb4"
    "$BUILD_IMAGE")

  "${RUN_TMP[@]}" php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration \
      || die "échec du rejeu des migrations dans la base jetable."

  find app/migrations -maxdepth 1 -name 'Version*.php' | sort > "$tmp_var/.before"
  local diff_output diff_rc=0
  diff_output="$("${RUN_TMP[@]}" php bin/console doctrine:migrations:diff --no-interaction 2>&1)" || diff_rc=$?
  if [[ $diff_rc -ne 0 ]]; then
    # "No changes detected" sort avec un code non nul : pas un échec, rien à générer.
    if ! grep -q "No changes detected" <<<"$diff_output"; then
      echo "$diff_output" >&2
      die "échec de la génération de la migration (doctrine:migrations:diff)."
    fi
  else
    echo "$diff_output"
  fi
  find app/migrations -maxdepth 1 -name 'Version*.php' | sort > "$tmp_var/.after"
  new_migration="$(comm -13 "$tmp_var/.before" "$tmp_var/.after" || true)"

  docker rm -f "$dbc" >/dev/null 2>&1 || true
  rm -rf "$tmp_var"
  trap - EXIT RETURN

  if [[ -z "$new_migration" ]]; then
    info "Migrations à jour avec les entités : rien à générer."
    return 0
  fi

  info "Migration générée : $new_migration"
  cat "$new_migration"
  read -r -p "Valider et committer cette migration ? [o/N] " ans
  if [[ ! "$ans" =~ ^[oOyY]$ ]]; then
    rm -f "$new_migration"
    die "annulé : corrigez les entités ou la migration, puis relancez."
  fi
  git add "$new_migration"
  git commit -m "Migration : $(basename "$new_migration")" >/dev/null
  info "Migration committée."
}

for cmd in git rsync ssh docker openssl; do
  command -v "$cmd" >/dev/null || die "commande requise absente : $cmd"
done

cd "$ROOT"

LAST_TAG="$(git tag --list "${TAG_PREFIX}*" --sort=-creatordate | head -n1 || true)"

# --- 1. Vérifications git -----------------------------------------------------
[[ "$(git rev-parse --abbrev-ref HEAD)" == "$BRANCH" ]] || die "il faut être sur la branche $BRANCH."

if [[ $SKIP_DB -eq 0 && $DRY_RUN -eq 0 && -n "$LAST_TAG" && $FIRST_RUN -eq 0 ]]; then
  generate_migration
elif [[ $DRY_RUN -eq 1 && $SKIP_DB -eq 0 && -n "$LAST_TAG" && $FIRST_RUN -eq 0 ]]; then
  info "--dry-run : génération de migration ignorée (aucune écriture/commit en dry-run)."
fi

# Seul le contenu commité (HEAD) est envoyé : les modifications non commitées sont ignorées, on l'indique seulement.
if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
  echo "ATTENTION : modifications non commitées, NON déployées (seul HEAD est envoyé) :"
  git status --short --untracked-files=no
fi

# --- 2. Différences depuis le dernier tag ------------------------------------
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
  [[ $CREATE_ADMIN -eq 1 && $DRY_RUN -eq 0 ]] && create_admin
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
# Les variables lues par la configuration doivent exister au build (valeurs factices : les vraies sont dans .env.local)
printf 'APP_ENV=prod\nAPP_DEBUG=0\nDEFAULT_URI=http://localhost\nMESSENGER_TRANSPORT_DSN=sync://\n' > "$APP/.env"

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

# FallbackResource attend un chemin depuis la racine du site : on y inscrit le chemin du front controller
URL_PATH="$(printf '%s' "${PUBLIC_URL%/}${PUBLIC_SUBPATH}" | sed -E 's#^[a-zA-Z]+://[^/]+##')"
sed "s#__FRONT_CONTROLLER__#${URL_PATH}/index.php#" "$DEPLOY_DIR/templates/htaccess" > "$APP/public/.htaccess"

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
  MIGRATE_URL="${PUBLIC_URL%/}${PUBLIC_SUBPATH}/$MIGRATE_NAME?token=$TOKEN"
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
DEFAULT_URI=${PUBLIC_URL%/}${PUBLIC_SUBPATH}
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

# Le cache précédent peut empêcher le démarrage du nouveau kernel, même sans migration.
bash "$DEPLOY_DIR/cache-clear.sh" "$TARGET"

# --- 6. Migration web ---------------------------------------------------------
if [[ -n "$MIGRATE_URL" ]]; then
  echo
  info "Ouvrez cette URL pour mettre à jour la base (usage unique, le script se supprime) :"
  echo "    $MIGRATE_URL"
  echo
  read -r -p "Migration terminée avec succès ? [o/N] " ans
  [[ "$ans" =~ ^[oOyY]$ ]] || die "déploiement non finalisé : aucun tag créé. Relancez après correction."
fi

# --- 6b. Compte admin ---------------------------------------------------------
[[ $CREATE_ADMIN -eq 1 ]] && create_admin

# --- 7. Tag -------------------------------------------------------------------
if [[ $NO_TAG -eq 0 ]]; then
  TAG="${TAG_PREFIX}$(date +%Y%m%d-%H%M%S)"
  git tag -a "$TAG" -m "Déploiement $(date '+%F %T')"
  info "Tag créé : $TAG"
  git push origin "$TAG" || echo "Push du tag impossible : à faire manuellement (git push origin $TAG)."
fi
info "Terminé."
