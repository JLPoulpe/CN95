#!/usr/bin/env bash
# Menu interactif CN95 : regroupe imports, purges, mails, déploiements et outils de deploy/.
# Usage : bash deploy/menu.sh        (ou : make menu)
#
# Chaque entrée lance le script bash de deploy/ avec les mêmes arguments que la cible make
# correspondante, et affiche cette commande make avant de l'exécuter. Les deux voies sont donc équivalentes.
# Les confirmations de suppression ou d'envoi restent celles des scripts (taper le mot demandé).
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEPLOY="$ROOT/deploy"
cd "$ROOT" || exit 1

# Variables de retour des fonctions (pas de sous-shell : la saisie de fin de fichier doit quitter le menu)
REP=""
CHOIX=0
MENU_RETOUR="Retour"
CIBLE_CHOISIE=""
FICHIER_CHOISI=""
ENVS_CHOISIS=()
CIBLES=()
DRY_OPT=()
DRY_MAKE=""

titre()  { printf '\n\033[1m== %s ==\033[0m\n' "$*"; }
erreur() { printf 'ERREUR : %s\n' "$*" >&2; }

# Lit une réponse dans REP ; $2 = valeur par défaut. Fin de saisie (Ctrl+D) : sortie du menu.
demander() {
  if ! read -r -p "$1" REP; then
    printf '\nFin de saisie.\n'
    exit 0
  fi
  REP="${REP:-${2:-}}"
}

# Question oui/non ; $1 = question, $2 = défaut (O ou N). Retour 0 si oui.
oui_non() {
  local hint="[O/n]"
  [[ "$2" == N ]] && hint="[o/N]"
  while true; do
    demander "$1 $hint : " "$2"
    case "${REP^^}" in
      O|OUI|Y|YES) return 0 ;;
      N|NON) return 1 ;;
    esac
    erreur "répondez O ou N"
  done
}

# Liste numérotée ; $1 = titre, les suivants = libellés. Le choix est lu dans CHOIX (0 = retour).
menu_liste() {
  local titre_menu="$1" i=1 item
  shift
  titre "$titre_menu"
  for item in "$@"; do
    printf '  %2d) %s\n' "$i" "$item"
    i=$((i + 1))
  done
  printf '   0) %s\n' "$MENU_RETOUR"
  while true; do
    demander "Votre choix : "
    if [[ "$REP" =~ ^[0-9]+$ ]] && (( 10#$REP <= $# )); then
      CHOIX=$((10#$REP))
      return 0
    fi
    erreur "choix invalide : $REP"
  done
}

# Remplit CIBLES avec les fichiers deploy/targets/*.conf (sans « local »).
charger_cibles() {
  local conf
  CIBLES=()
  for conf in "$DEPLOY"/targets/*.conf; do
    [[ -e "$conf" ]] && CIBLES+=("$(basename "$conf" .conf)")
  done
  return 0
}

# Choix d'environnements dans ENVS_CHOISIS. $1 = 1 si « toutes les cibles » est proposé (tableau vide = toutes).
choisir_envs() {
  local toutes="$1" n=1 e tok ok
  local -a toks
  charger_cibles
  local dispo=(local "${CIBLES[@]}")
  titre "Environnements"
  for e in "${dispo[@]}"; do
    printf '  %2d) %s\n' "$n" "$e"
    n=$((n + 1))
  done
  (( toutes == 1 )) && printf '   a) Toutes les cibles (local puis deploy/targets/*.conf)\n'
  printf '   Saisir les numéros séparés par des espaces (ex. : 1 2)\n'
  while true; do
    demander "Environnements : "
    if (( toutes == 1 )) && [[ "${REP,,}" == a ]]; then
      ENVS_CHOISIS=()
      return 0
    fi
    ENVS_CHOISIS=()
    ok=1
    read -r -a toks <<< "$REP"
    for tok in "${toks[@]}"; do
      if [[ "$tok" =~ ^[0-9]+$ ]] && (( 10#$tok >= 1 && 10#$tok <= ${#dispo[@]} )); then
        ENVS_CHOISIS+=("${dispo[10#$tok - 1]}")
      else
        ok=0
        break
      fi
    done
    if (( ok == 1 && ${#ENVS_CHOISIS[@]} > 0 )); then
      return 0
    fi
    erreur "sélection invalide"
  done
}

# Choix d'une seule cible (hors local) dans CIBLE_CHOISIE. Retour 1 si annulé.
choisir_cible() {
  charger_cibles
  if (( ${#CIBLES[@]} == 0 )); then
    erreur "aucune cible dans deploy/targets/ (copier deploy/targets/prod.conf.example)"
    return 1
  fi
  menu_liste "Cible" "${CIBLES[@]}"
  (( CHOIX == 0 )) && return 1
  CIBLE_CHOISIE="${CIBLES[CHOIX - 1]}"
}

# Choix d'un CSV dans import/ (ou d'un autre chemin) dans FICHIER_CHOISI. Retour 1 si annulé.
choisir_fichier() {
  local -a labels=()
  local f
  while IFS= read -r f; do
    labels+=("import/$f")
  done < <(find "$ROOT/import" -maxdepth 1 -type f -name '*.csv' -printf '%f\n' 2>/dev/null | sort)
  labels+=("Autre chemin…")
  menu_liste "Fichier CSV" "${labels[@]}"
  (( CHOIX == 0 )) && return 1
  if (( CHOIX == ${#labels[@]} )); then
    demander "Chemin du fichier CSV : "
    FICHIER_CHOISI="$REP"
  else
    FICHIER_CHOISI="${labels[CHOIX - 1]}"
  fi
  if [[ ! -f "$FICHIER_CHOISI" ]]; then
    erreur "fichier introuvable : $FICHIER_CHOISI"
    return 1
  fi
}

# Simulation ou non : remplit DRY_OPT (arguments bash) et DRY_MAKE (variable make).
choisir_dry() {
  if oui_non "Simuler d'abord (rien n'est écrit ni envoyé) ?" O; then
    DRY_OPT=(--dry-run)
    DRY_MAKE=" DRY_RUN=1"
  else
    DRY_OPT=()
    DRY_MAKE=""
  fi
}

# Affiche ENVS="..." pour la commande make équivalente (rien si aucun environnement choisi).
env_make() {
  if (( ${#ENVS_CHOISIS[@]} > 0 )); then
    printf ' ENVS="%s"' "${ENVS_CHOISIS[*]}"
  fi
  return 0
}

# Affiche la commande, son équivalent make, lance la commande, puis attend Entrée.
# $1 = équivalent make ; le reste = commande bash (exécutée depuis la racine du projet).
executer() {
  local equiv="$1"
  shift
  printf '\n\033[2mCommande      : %s\033[0m\n' "$*"
  printf '\033[2mÉquivalent make : %s\033[0m\n\n' "$equiv"
  # Ctrl+C interrompt la commande lancée (logs, shell…) sans fermer le menu
  trap ':' INT
  "$@"
  local rc=$?
  trap - INT
  printf '\n'
  if (( rc == 0 )); then
    printf 'Terminé (code 0).\n'
  else
    erreur "code de sortie $rc"
  fi
  demander "Entrée pour revenir au menu… "
}

importer() {
  menu_liste "Importer" \
    "Adhérents (comptes créés, sans mail)" \
    "Aptitudes (référentiel)" \
    "Lieux d'activité" \
    "Planning (calendrier de saison)" \
    "Correctif planning : PN1 sur les créneaux existants"
  (( CHOIX == 0 )) && return 0
  local -a scripts=(import-adherents import-aptitudes import-lieux import-planning corriger-pn1)
  local cible="${scripts[CHOIX - 1]}"

  choisir_fichier || return 0
  choisir_envs 0
  choisir_dry

  # corriger-pn1 prend le fichier en option --fichier ; les autres imports le prennent en premier argument
  local -a arg_fichier=("$FICHIER_CHOISI")
  [[ "$cible" == corriger-pn1 ]] && arg_fichier=(--fichier "$FICHIER_CHOISI")

  executer "make $cible FICHIER=\"$FICHIER_CHOISI\"$(env_make)$DRY_MAKE" \
    bash "deploy/$cible.sh" "${arg_fichier[@]}" "${DRY_OPT[@]}" "${ENVS_CHOISIS[@]}"
}

purger() {
  menu_liste "Purger" \
    "Totale : tout sauf administrateurs, rôles, permissions et config mails (aptitudes et lieux inclus)" \
    "Configuration : idem, mais garde aptitudes, lieux d'activité, config mails et permissions" \
    "Planning : uniquement les semaines et créneaux du planning"
  (( CHOIX == 0 )) && return 0
  local -a niveaux=(totale configuration planning)
  local niveau="${niveaux[CHOIX - 1]}"

  choisir_envs 1
  choisir_dry

  executer "make purge NIVEAU=$niveau$(env_make)$DRY_MAKE" \
    bash deploy/purge.sh --niveau "$niveau" "${DRY_OPT[@]}" "${ENVS_CHOISIS[@]}"
}

mail_activation() {
  menu_liste "Mail d'activation" \
    "Un seul destinataire (test)" \
    "Tous les comptes dont l'activation est en attente"
  local mode=$CHOIX
  (( mode == 0 )) && return 0

  local -a args
  local adresse="" equiv_cible=""
  if (( mode == 1 )); then
    demander "Adresse email du destinataire : "
    adresse="$REP"
    if [[ ! "$adresse" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]]; then
      erreur "adresse invalide : $adresse"
      return 0
    fi
    args=(--email "$adresse")
    equiv_cible="EMAIL=$adresse"
  else
    args=(--tous)
    equiv_cible="TOUS=1"
  fi

  choisir_envs 0
  choisir_dry

  if (( ${#DRY_OPT[@]} == 0 )); then
    printf '\033[1mATTENTION\033[0m : envoi réel. La base locale peut contenir la vraie configuration Brevo et de vraies adresses.\n'
    if (( mode == 1 )) && ! oui_non "Envoyer réellement le mail à $adresse ?" N; then
      echo "Envoi annulé."
      return 0
    fi
  fi

  executer "make envoyer-activation $equiv_cible$(env_make)$DRY_MAKE" \
    bash deploy/envoyer-activation.sh "${args[@]}" "${DRY_OPT[@]}" "${ENVS_CHOISIS[@]}"
}

deployer() {
  choisir_cible || return 0
  local cible="$CIBLE_CHOISIE" options_make=""
  choisir_dry
  demander "Options supplémentaires (ex. --skip-db --no-tag), vide = aucune : "
  local -a extra=()
  read -r -a extra <<< "$REP"
  if (( ${#extra[@]} > 0 )); then
    options_make=" OPTIONS=\"${extra[*]}\""
  fi

  executer "make deploy TARGET=$cible$DRY_MAKE$options_make" \
    bash deploy/deploy.sh "$cible" "${DRY_OPT[@]}" "${extra[@]}"
}

cle_brevo() {
  choisir_envs 1
  local -a masque=()
  local masque_make=""
  if oui_non "Masquer les secrets (4 derniers caractères seulement) ?" O; then
    masque=(--masquer)
    masque_make=" MASQUER=1"
  fi

  executer "make brevo-key$(env_make)$masque_make" \
    bash deploy/brevo-key.sh "${masque[@]}" "${ENVS_CHOISIS[@]}"
}

vider_cache() {
  choisir_cible || return 0
  executer "make cache-clear TARGET=$CIBLE_CHOISIE" \
    bash deploy/cache-clear.sh "$CIBLE_CHOISIE"
}

logs_serveur() {
  choisir_cible || return 0
  local cible="$CIBLE_CHOISIE" mode n=""
  menu_liste "Mode de lecture des logs" \
    "Dernières erreurs (défaut)" \
    "Suivi en direct (Ctrl+C pour quitter)" \
    "Dernières lignes brutes"
  (( CHOIX == 0 )) && return 0
  local -a modes=(errors tail all)
  mode="${modes[CHOIX - 1]}"

  local -a n_arg=()
  local n_make=""
  if [[ "$mode" != tail ]]; then
    demander "Nombre de lignes (vide = défaut du script) : "
    n="$REP"
    if [[ -n "$n" && ! "$n" =~ ^[0-9]+$ ]]; then
      erreur "nombre invalide : $n"
      return 0
    fi
    if [[ -n "$n" ]]; then
      n_arg=("$n")
      n_make=" N=$n"
    fi
  fi

  executer "make logs-serveur TARGET=$cible MODE=$mode$n_make" \
    bash deploy/logs.sh "$cible" "$mode" "${n_arg[@]}"
}

docker_local() {
  menu_liste "Docker local" \
    "Démarrer (web + db)" \
    "Arrêter (données conservées)" \
    "Reconstruire et démarrer" \
    "Redémarrer" \
    "État des conteneurs" \
    "Suivre les logs" \
    "Démarrer aussi Adminer (http://localhost:8081)" \
    "Shell dans le conteneur web" \
    "Client MariaDB" \
    "Arrêter et SUPPRIMER les volumes (perte de la base locale)"
  (( CHOIX == 0 )) && return 0
  local -a actions=(up down build restart ps logs tools shell db-shell clean)
  local action="${actions[CHOIX - 1]}"

  executer "make $action" bash deploy/docker.sh "$action"
}

aide() {
  executer "make help" make help
}

principal() {
  while true; do
    MENU_RETOUR="Quitter"
    menu_liste "CN95 : menu d'administration" \
      "Importer (adhérents, aptitudes, lieux, planning)…" \
      "Purger une base…" \
      "Mail d'activation…" \
      "Déployer une cible…" \
      "Clé API Brevo" \
      "Vider le cache d'une cible" \
      "Logs Symfony d'une cible…" \
      "Docker local…" \
      "Aide (liste des commandes make)"
    MENU_RETOUR="Retour"
    case "$CHOIX" in
      0) printf 'Au revoir.\n'; exit 0 ;;
      1) importer ;;
      2) purger ;;
      3) mail_activation ;;
      4) deployer ;;
      5) cle_brevo ;;
      6) vider_cache ;;
      7) logs_serveur ;;
      8) docker_local ;;
      9) aide ;;
    esac
  done
}

principal
