# Déploiement CN95

Outil local : `deploy/deploy.sh [cible]`. Il construit le projet (image Docker `cn95-web`), envoie les fichiers par rsync/SSH, génère un script web de migration à usage unique et crée un tag git `deploy/<cible>/AAAAMMJJ-HHMMSS`.

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

## Nouvel hébergement
1. `cp deploy/targets/prod.conf.example deploy/targets/<nom>.conf` et adapter (SSH, chemin, URL, DB).
2. `deploy/deploy.sh <nom>` : aucun tag pour cette cible, donc déploiement complet avec toutes les migrations, puis création de `.env.local` sur le nouveau serveur.
