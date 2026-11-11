# Déploiement CN95

Outil local : `deploy/deploy.sh [cible]`. Il génère la migration de base de données, construit le projet (image Docker `cn95-web`), envoie les fichiers par rsync/SSH, applique les migrations sur le serveur par SSH et crée un tag git `deploy/<cible>/AAAAMMJJ-HHMMSS`.

Une **cible** = un hébergement (`deploy/targets/<cible>.conf`, ignoré par git). Chaque cible a ses propres tags, donc son propre historique « déjà envoyé ».

## Menu et commandes

Trois façons de lancer la même action : le **menu interactif**, la **cible make** et le **script bash**. Chaque action existe en make *et* en bash, avec les mêmes arguments.

```bash
make menu            # ou : bash deploy/menu.sh
```

Le menu affiche la commande make équivalente avant chaque exécution. Pour les imports, purges, mails et déploiements, il propose par défaut de simuler d'abord (`--dry-run`). Les confirmations de purge, d'import ou d'envoi restent celles des scripts.

Conventions des variables make : `ENVS="local ionos"` pour plusieurs environnements, `TARGET=ionos` pour une cible unique, `DRY_RUN=1` pour `--dry-run`.

| Action | make | bash |
|---|---|---|
| Docker | `make up` / `down` / `build` / `restart` / `ps` / `logs` / `shell` / `db-shell` / `tools` / `clean` | `bash deploy/docker.sh <action>` |
| Déployer | `make deploy TARGET=ionos DRY_RUN=1 OPTIONS="--skip-db"` | `bash deploy/deploy.sh ionos --dry-run --skip-db` |
| Logs serveur | `make logs-serveur TARGET=ionos MODE=errors N=5` | `bash deploy/logs.sh ionos errors 5` |
| Vider le cache | `make cache-clear TARGET=ionos` | `bash deploy/cache-clear.sh ionos` |
| Clé API Brevo | `make brevo-key ENVS="local ionos" MASQUER=1` | `bash deploy/brevo-key.sh --masquer local ionos` |
| Purger | `make purge NIVEAU=totale DRY_RUN=1 ENVS="local"` | `bash deploy/purge.sh --niveau totale --dry-run local` |
| Import adhérents | `make import-adherents FICHIER=import/x.csv ENVS="local" DRY_RUN=1` | `bash deploy/import-adherents.sh import/x.csv --dry-run local` |
| Import aptitudes | `make import-aptitudes FICHIER=import/x.csv ENVS="local" DRY_RUN=1` | `bash deploy/import-aptitudes.sh import/x.csv --dry-run local` |
| Import lieux | `make import-lieux FICHIER=import/x.csv ENVS="local" DRY_RUN=1` | `bash deploy/import-lieux.sh import/x.csv --dry-run local` |
| Import planning | `make import-planning FICHIER=import/x.csv ENVS="local" DRY_RUN=1` | `bash deploy/import-planning.sh import/x.csv --dry-run local` |
| Corriger PN1 du planning | `make corriger-pn1 FICHIER=import/x.csv ENVS="local" DRY_RUN=1` | `bash deploy/corriger-pn1.sh --dry-run --fichier import/x.csv local` |
| Mail d'activation | `make envoyer-activation EMAIL=moi@example.org ENVS="local"` ou `TOUS=1` | `bash deploy/envoyer-activation.sh --email moi@example.org local` ou `--tous` |

Changement de comportement : `make clean` (et `bash deploy/docker.sh clean`) demande désormais de taper `SUPPRIMER` avant de supprimer les volumes.

## Première utilisation
1. `cp deploy/targets/prod.conf.example deploy/targets/prod.conf`, puis renseigner `PUBLIC_URL` et `DB_NAME`.
2. Accès SSH par clé vers le serveur, `rsync` côté serveur, domaine pointant sur `REMOTE_DIR/public`, PHP 8.4 choisi chez l'hébergeur.
3. `deploy/deploy.sh prod --dry-run` pour simuler.
4. `deploy/deploy.sh prod` : comme `.env.local` n'existe pas encore sur le serveur, l'outil demande le mot de passe DB au terminal (non affiché), génère `APP_SECRET` et écrit `REMOTE_DIR/.env.local` (chmod 600). Le mot de passe n'est stocké ni en local ni dans git.

## Utilisation courante
```
deploy/deploy.sh prod --dry-run   # simulation, ni envoi ni tag
deploy/deploy.sh prod             # déploiement incrémental depuis le dernier tag de la cible
```
Options : `--first-run` (déploiement complet), `--init-server` (réécrit `.env.local`, ex. mot de passe changé), `--skip-db`, `--create-admin` (crée le compte admin, mot de passe généré et affiché une seule fois dans le terminal ; sans effet si le compte existe), `--seed` (rejoue `app:seed`), `--no-tag`.

Les migrations sont appliquées par SSH (`bin/console doctrine:migrations:migrate`, avec le `PHP_CLI` de la cible) : aucun script n'est déposé dans `public/`. En cas d'échec, le déploiement s'arrête et aucun tag n'est créé.

### Suppression du cache

Après chaque synchronisation effective des fichiers, le déploiement supprime
`var/cache` sur le serveur, y compris avec `--skip-db` ou sans nouvelle migration.
Le cache est reconstruit à la prochaine requête. Le déploiement vide
également le cache après les migrations et le seed éventuel.
En `--dry-run` ou lorsqu'il n'y a rien à déployer, le cache reste intact.

Pour supprimer uniquement le cache d'une cible, sans déployer ni modifier la base :

```bash
make cache-clear TARGET=prod    # charge deploy/targets/prod.conf
make cache-clear TARGET=ovh     # charge deploy/targets/ovh.conf
bash deploy/cache-clear.sh prod # équivalent sans Make
```

La cible par défaut est `prod`. Cette commande utilise SSH et supprime uniquement
`var/cache` dans `REMOTE_DIR` ; les logs et les autres données de `var/` sont conservés.
Une erreur de suppression interrompt le déploiement avant la création du tag.

### Génération de la migration

Ne créez plus de migration à la main pendant le développement (`doctrine:migrations:diff`). À chaque exécution de `deploy/deploy.sh` (hors `--dry-run`, `--skip-db`, `--first-run`), le script :
1. supprime les éventuelles migrations déjà présentes dans `app/migrations/` qui n'ont pas encore été déployées (créées manuellement entre deux déploiements) ;
2. rejoue dans une base MariaDB jetable les migrations réellement déployées (celles du dernier tag) ;
3. lance `doctrine:migrations:diff` contre les entités actuelles pour générer une migration unique consolidant tous les changements de mapping depuis le dernier déploiement ;
4. affiche le fichier généré et demande confirmation avant de le committer.

S'il n'y a aucun changement d'entité, aucune migration n'est créée.

## Nouvel hébergement
1. `cp deploy/targets/prod.conf.example deploy/targets/<nom>.conf` et adapter (SSH, chemin, URL, DB).
2. `deploy/deploy.sh <nom>` : aucun tag pour cette cible, donc déploiement complet avec toutes les migrations, puis création de `.env.local` sur le nouveau serveur.

### Clé API Brevo des environnements

La configuration d'envoi de mails (clé API Brevo) est chiffrée en base avec le `APP_SECRET` de chaque environnement. Pour l'afficher en clair :

```bash
bash deploy/brevo-key.sh                    # local (Docker) puis toutes les cibles de deploy/targets/*.conf
bash deploy/brevo-key.sh ionos cn95         # cibles choisies
bash deploy/brevo-key.sh local --masquer    # ne montre que les 4 derniers caractères
make brevo-key ENVS="local ionos" MASQUER=1 # équivalent via Make
```

Le script lance `php bin/console app:mail:afficher-config` sur chaque environnement (Docker en local, SSH pour une cible). La commande doit donc avoir été déployée sur la cible. Si `APP_SECRET` a changé depuis l'enregistrement de la clé, la configuration est illisible : il faut la ressaisir dans **Configuration de l'outil → Configuration des mails**. Le résultat contient un secret : ne pas le copier dans un ticket ou une conversation.


## Purge de la base

La purge supprime les données de chaque environnement (local puis toutes les cibles) en gardant toujours les comptes administrateurs. Deux niveaux :

| Niveau | Conservé | Supprimé |
|---|---|---|
| `totale` | administrateurs, rôles, permissions, configuration des mails | tout le reste, y compris les aptitudes et les lieux d'activité |
| `configuration` | idem, plus « Configuration de l'outil » (aptitudes, lieux d'activité, configuration des mails, permissions) | activités, planning, invitations, fiches de sécurité, communications, demandes RGPD, file d'envoi des mails, utilisateurs non administrateurs |

```bash
bash deploy/purge.sh --niveau totale --dry-run          # simulation sur toutes les cibles, rien n'est supprimé
bash deploy/purge.sh --niveau configuration local ionos # purge de certaines cibles seulement
make purge NIVEAU=totale DRY_RUN=1                      # équivalent via Make
```

- Une seule confirmation est demandée (taper `PURGER`) avant de commencer ; la suppression est irréversible.
- La commande `app:purge` doit être déployée sur chaque cible (`deploy/deploy.sh <cible>`) avant la purge.
- Chaque table de la base doit être classée dans `app/src/Purge/PurgeEnvironnement.php`. Une table inconnue fait échouer la purge sans rien supprimer : l'ajouter à une des listes avant de relancer.
- Au niveau `totale`, les aptitudes sont supprimées et le compte administrateur perd son aptitude : relancer `app:seed` (aptitudes par défaut) puis renseigner l'aptitude de l'administrateur.


## Import des adhérents et mail d'activation

Le fichier CSV doit être nettoyé : `nom;prenom;email;telephone;date_naissance;caci_expire_le;aptitude` (dates `AAAA-MM-JJ`, téléphone en `0XXXXXXXXX`, aptitude = code en base). Il contient des données personnelles : le dossier `import/` est ignoré par git.

```bash
bash deploy/import-adherents.sh import/adherents_2027.csv --dry-run local   # valide et liste les comptes, n'écrit rien
bash deploy/import-adherents.sh import/adherents_2027.csv local ionos       # crée les comptes (confirmation IMPORTER)
make import-adherents FICHIER=import/adherents_2027.csv ENVS="local" DRY_RUN=1
```

### Importer le planning (calendrier de saison)

Le CSV a les colonnes `Date;Jour;PN1;PE40;PN2;PN3;Événement / remarque` (dates `AAAA-MM-JJ`, cellule = activité). Prérequis sur chaque environnement : aptitudes N1, N2 et PE40, activités (BLOC, PMT, APNEE, THEORIE…) et lieux d'activité.

```bash
bash deploy/import-planning.sh import/calendrier.csv --dry-run local   # valide, n'écrit rien
bash deploy/import-planning.sh import/calendrier.csv local ionos       # crée les semaines (confirmation IMPORTER)
make import-planning FICHIER=import/calendrier.csv ENVS="local" DRY_RUN=1
```

Les environnements sont à nommer explicitement. Les événements (FOSSE, TIV…) et les jours autres que lundi/vendredi sont ignorés ; une semaine déjà en base n'est jamais modifiée, donc le script est relançable.

Les environnements sont à nommer explicitement (`local` ou une cible). Chaque compte créé a le rôle Adhérent, un mot de passe inutilisable et une activation en attente ; **aucun mail n'est envoyé**. Identifiant = prénom (sans accent) ; si le prénom est partagé ou déjà pris : `prénom.nom`. Un email déjà en base est ignoré, donc le script est relançable. Une ligne invalide bloque tout l'import.

Mail d'activation (le même que lors de l'ajout d'un adhérent), à envoyer ensuite :

```bash
bash deploy/envoyer-activation.sh --email moi@example.org --dry-run local   # liste le destinataire
bash deploy/envoyer-activation.sh --email moi@example.org ionos             # un seul destinataire
bash deploy/envoyer-activation.sh --tous ionos                              # tous les comptes en attente (confirmation ENVOYER)
make envoyer-activation EMAIL=moi@example.org ENVS="ionos"
```

Chaque envoi génère un nouveau lien valable 7 jours (le précédent est invalidé). Les comptes déjà activés ne reçoivent rien. Les deux commandes doivent avoir été déployées sur la cible (`deploy/deploy.sh <cible>`).
