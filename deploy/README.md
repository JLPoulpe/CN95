# Déploiement CN95

Outil local : `deploy/deploy.sh [cible]`. Il génère la migration de base de données, construit le projet (image Docker `cn95-web`), envoie les fichiers par rsync/SSH, génère un script web de migration à usage unique et crée un tag git `deploy/<cible>/AAAAMMJJ-HHMMSS`.

Une **cible** = un hébergement (`deploy/targets/<cible>.conf`, ignoré par git). Chaque cible a ses propres tags, donc son propre historique « déjà envoyé ».

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

L'outil affiche une URL `_migrate_<id>.php?token=...` : l'ouvrir dans le navigateur applique les migrations puis supprime le script. Le tag n'est créé qu'après votre confirmation.

### Suppression du cache

Après chaque synchronisation effective des fichiers, le déploiement supprime
`var/cache` sur le serveur, y compris avec `--skip-db` ou sans nouvelle migration.
Le cache est reconstruit à la prochaine requête. Le script web de migration vide
également le cache après les migrations et le seed éventuel, puis le préchauffe.
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

