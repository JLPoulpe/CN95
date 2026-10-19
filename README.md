# CN95 - Planning Management System

Un système de gestion de plannings et de créneaux avec gestion des utilisateurs, des rôles et des aptitudes, ainsi que l'organisation des activités externes (fosse, sorties) par invitation et l'envoi de mails configurables.

## 📋 Stack Technique

### Backend
- **Symfony 7.4** - Framework web PHP moderne
- **PHP 8.4** - Langage de programmation (via Docker)
- **Doctrine ORM 3.7** - Mapping objet-relationnel et migrations de base de données
- **MariaDB 11** - Base de données relationnelle

### Frontend
- **Twig 3.x** - Moteur de templates
- **Asset Mapper** - Gestion moderne des assets (CSS, JS)
- **Stimulus 3.x** - Framework JavaScript lightweight
- **Turbo 3.x** - Navigation optimisée sans rechargement complet

### Infrastructure & Outils
- **Docker & Docker Compose** - Conteneurisation et orchestration
- **Apache 2.4** - Serveur web
- **Composer 2** - Gestionnaire de dépendances PHP
- **PHPUnit 13** - Framework de testing
- **PHPStan** - Analyse statique du code
- **Adminer** - Interface web pour gérer la BDD (optionnel)

## 🚀 Démarrage Rapide

### Prérequis
- Docker & Docker Compose
- (Optionnel) PHP 8.4+ et Composer pour le développement local sans Docker

### Installation

1. **Cloner le repository**
```bash
git clone https://github.com/JLPoulpe/CN95.git
cd CN95
```

2. **Configurer l'environnement**
```bash
cp .env.example .env
```
Adapter les variables d'environnement si nécessaire (voir section [Variables d'environnement](#variables-denvironnement))

3. **Démarrer l'application**
```bash
make up
```

L'application sera accessible à `http://127.0.0.1:8080`

### Commandes Utiles (Makefile)

```bash
make help         # Affiche l'aide des commandes disponibles
make up           # Démarre les containers (web + db)
make down         # Arrête les containers
make build        # Reconstruit les images et démarre
make restart      # Redémarre complètement
make logs         # Suit les logs en temps réel
make ps           # Liste l'état des containers
make shell        # Accès au shell du container web
make db-shell     # Accès à la CLI MariaDB
make tools        # Démarre aussi Adminer (http://127.0.0.1:8081)
make clean        # Arrête et supprime les volumes (données perdues!)
make cache-clear TARGET=prod # Supprime le cache distant de deploy/targets/prod.conf
```

## 👥 Rôles et droits

Quatre rôles (table `role`, créés par `app:seed`). ADMIN hérite de tous les autres (`role_hierarchy` dans `security.yaml`).

| Rôle | Description |
|---|---|
| `ADHERENT` | Membre du club. Aucun accès à l'espace administrateur. |
| `MONITEUR` | Moniteur. Peut être désigné responsable d'une invitation. |
| `BUREAU` | Membre du bureau. Gère les invitations, la communication et la configuration des activités. |
| `ADMIN` | Administrateur. Accès à tout, y compris la configuration de l'outil. |

Les droits fonctionnels sont enregistrés en base (table `role_permission`) et se modifient dans **Configuration de l'outil → Permissions** (ADMIN) : une matrice de cases à cocher, une ligne par fonctionnalité et une colonne par rôle (un rôle ajouté dans la table `role` apparaît automatiquement). Un ADMIN a toujours toutes les permissions et n'apparaît pas dans la matrice ; l'écran Permissions lui-même reste réservé aux ADMIN, quels que soient les réglages. `app/src/Security/Permission.php` liste les permissions et leur configuration par défaut (appliquée par la migration et par `app:seed` sur une base vierge).

| Élément de la page `/membre` | Permission | Rôles par défaut (en plus d'ADMIN) |
|---|---|---|
| Invitations aux activités externes (une entrée par activité externe) | `PERM_INVITATIONS` | BUREAU |
| Communication → Activités externes | `PERM_ACTIVITES_EXTERNES` | BUREAU, MONITEUR |
| Communication → Communication générale, Réseaux sociaux | `PERM_COMMUNICATION` | BUREAU |
| Communication → Gestion du planning | `PERM_CONFIG_ACTIVITES` | BUREAU |
| Configuration des activités (aptitudes, activités, activités externes, lignes d'eau, planning) | `PERM_CONFIG_ACTIVITES` | BUREAU |
| Gestion des utilisateurs (liste, ajout) | `PERM_UTILISATEURS` | aucun (ADMIN seul) |
| Configuration de l'outil (configuration des mails, permissions) | `PERM_CONFIG_OUTIL` | aucun (ADMIN seul) |

L'accès à `/admin/invitations` est ouvert par `PERM_INVITATIONS` **ou** `PERM_ACTIVITES_EXTERNES` (`security.yaml`) : avec la configuration par défaut, un MONITEUR y accède donc via « Activités externes ».

Les sections vides pour un utilisateur ne sont pas affichées ; la première section visible est ouverte, les autres se replient.

### Invitations

- Une invitation est liée à **un ou plusieurs rôles** qui la voient et la gèrent. Un membre (hors ADMIN) ne voit et ne gère que les invitations partageant au moins un de ses rôles.
- Les créateurs (BUREAU, MONITEUR) ont leurs rôles pré-cochés à la création ; ils doivent en garder au moins un, sinon l'invitation disparaîtrait de leur liste. Les rôles ADHERENT et ADMIN ne sont pas proposés (l'administrateur voit toujours toutes les invitations).
- Le commentaire est **pré-rempli avec le texte par défaut de l'activité externe** (rendez-vous, consignes, adresse…), modifiable dans **Configuration des activités → Activités externes** et à la création de l'invitation.
- Le bouton « Envoyer un mail test » envoie une copie du mail à une adresse de son choix, avec le commentaire et la date saisis, sans rien enregistrer.
- Toutes les actions (fiche, inscrits, réponses, export, relance, annulation, suppression) vérifient le droit de gestion (`InvitationVoter`).

### Mails

Chaque mail est un fichier Twig de `app/templates/mail/`, enveloppé par la mise en page commune `templates/mail/_base.html.twig` (« Bonjour à toutes et à tous, », contenu, signature, logos, liens CN95 et pied de page RGPD). Les liens propres à un mail sont fournis par son mailer ; il n'y a plus de modèles en base. `MailRenderer` rend le gabarit puis l'enveloppe dans la base.

| Mail | Mailer | Gabarit | Contenu | Déclenché par |
|---|---|---|---|---|
| Création de compte | `CompteMailer` | `compte/activation.html.twig` | Base + information RGPD + types de mails reçus + lien d'activation (valable 7 jours) | Création d'un utilisateur, renvoi du lien depuis sa fiche |
| Invitation / relance | `FosseMailer` | `invitation.html.twig` | Base + phrase d'introduction + commentaire de l'invitation + 3 liens (je m'inscris, je ne participe pas, je ne peux plus participer) | Création d'une invitation ; bouton « Relancer » (objet « Rappel : ») |
| Annulation | `FosseMailer` | `annulation.html.twig` | Base + activité et date | Annulation d'une invitation, aux membres ayant répondu |
| Désistement | `FosseMailer` | `desistement.html.twig` | Base + membre, activité et date | Un inscrit se désiste via son lien, envoyé au responsable (DP) |
| Communication officielle | `CommunicationMailer` | `communication.html.twig` | Base + contenu saisi dans l'éditeur ; objet « CN95 Plongée – titre » | Envoi d'une communication |
| Information RGPD | `RgpdMailer` | `rgpd/information*.html.twig` | Base + information + lien d'accusé de lecture | Commande `app:rgpd:envoyer-information` |
| Rappel de purge | `RgpdMailer` | `rgpd/rappel_purge.html.twig` | Base + éléments qui seront supprimés | Commande `app:rgpd:rappel-purge` (J-60, J-30, J-7, J-1) |

Le commentaire d'invitation et le contenu d'une communication sont du HTML saisi avec l'éditeur riche (Quill), nettoyé avant enregistrement et à l'envoi (balises de mise en forme, liens web et mail, styles inline ; scripts et images retirés).

**Création de compte et RGPD** : le mail d'activation contient l'information RGPD. La page d'activation la réaffiche, et valider le mot de passe vaut prise de connaissance : une demande `Information` répondue est alors enregistrée avec la version du texte (`RgpdMailer::VERSION_TEXTE`). Un simple clic sur le lien n'enregistre rien (un antivirus peut ouvrir les liens). La commande d'information RGPD ne concerne que les membres existants : elle ignore ceux dont l'activation est en attente et ceux déjà informés pour la version.

**Communications officielles** (menu Communication → Communication générale, permission `PERM_COMMUNICATION`) : on rédige un titre et un contenu (brouillon modifiable, aperçu, mail de test à soi-même), puis on envoie à tous les membres actifs (compte activé, adresse mail renseignée), éventuellement restreints par rôles et/ou aptitude principale. Il n'y a pas de choix d'abonnement : ces envois sont des informations officielles du club. Une communication envoyée est conservée comme historique et ne peut plus être modifiée.

Les logos sont publiés dans `app/public/images/mail/` et référencés en URL absolue : ils ne s'affichent dans les mails que si le site est joignable publiquement.

Le mode d'envoi (Brevo, etc.) se configure dans **Configuration de l'outil → Configuration des mails**. Les mails sont mis en file (Messenger) puis envoyés en fin de requête web ou en fin de commande console (`EnvoiFileSubscriber`) ; un mail en échec est repris à la prochaine requête ou commande qui en ajoute un.

## 📁 Structure du Projet

```
CN95/
├── app/                          # Racine de l'application Symfony
│   ├── src/
│   │   ├── Entity/              # Entités Doctrine (modèles de données)
│   │   │   ├── User.php         # Utilisateurs
│   │   │   ├── Role.php         # Rôles utilisateur
│   │   │   ├── Aptitude.php     # Compétences/aptitudes
│   │   │   ├── PlanningSemaine.php    # Planning hebdomadaire
│   │   │   ├── PlanningCreneau.php    # Créneaux de planning
│   │   │   ├── InvitationFosse.php    # Invitations aux activités externes
│   │   │   ├── ReponseFosse.php       # Réponses des membres aux invitations
│   │   │   ├── Communication.php      # Communications officielles par mail
│   │   │   └── RolePermission.php     # Permissions accordées à chaque rôle
│   │   ├── Controller/          # Contrôleurs (routes & logique HTTP)
│   │   │   └── Admin/           # Espace administrateur (/admin/...)
│   │   ├── Form/                # Types de formulaires Symfony
│   │   ├── Mail/                # Envoi et rendu des mails (MailSender, MailRenderer, FosseMailer, CompteMailer, CommunicationMailer, RgpdMailer, plugins)
│   │   ├── Security/            # Permission.php (liste des droits), PermissionProvider.php (droits en base) et Voter/ (PermissionVoter, InvitationVoter)
│   │   ├── Repository/          # Requêtes personnalisées (Doctrine)
│   │   ├── Command/             # Commandes console (app:seed)
│   │   └── Kernel.php           # Noyau Symfony
│   ├── config/                  # Configuration Symfony
│   ├── templates/               # Templates Twig
│   ├── public/                  # Fichiers statiques (index.php, assets)
│   ├── tests/                   # Tests PHPUnit
│   ├── migrations/              # Migrations Doctrine
│   ├── translations/            # Fichiers de traduction
│   ├── var/                     # Cache, logs, uploads (gitignored)
│   ├── vendor/                  # Dépendances Composer (gitignored)
│   ├── composer.json            # Dépendances & scripts PHP
│   └── phpunit.dist.xml         # Configuration PHPUnit
├── docker/
│   ├── apache/vhost.conf        # Configuration VirtualHost Apache
│   ├── php/php.ini              # Configuration PHP
│   └── db/init.sql              # Script d'initialisation MariaDB
├── docker-compose.yml           # Orchestration des containers
├── Dockerfile                   # Image Docker PHP/Apache
├── .env.example                 # Exemple de variables d'environnement
├── .gitignore                   # Fichiers à ignorer en versioning
├── Makefile                     # Commandes utiles
└── CN95.code-workspace          # Workspace VS Code
```

## ⚙️ Variables d'environnement

Créer un fichier `.env` à la racine (copie de `.env.example`) :

```env
# Docker - UID/GID (adapter aux vôtres si nécessaire)
UID=1000
GID=1000

# Base de données
DB_NAME=cn95
DB_USER=cn95
DB_PASSWORD=change_me
DB_ROOT_PASSWORD=change_me_root

# URL publique du site : indispensable pour les liens des mails envoyés depuis la console (cron, commandes RGPD)
DEFAULT_URI=http://127.0.0.1:8080
```

L'URL de connexion à la base de données est automatiquement définie pour Docker Compose.

## 🏗️ Architecture

### Entités principales

- **User** - Utilisateurs du système (email, password, etc.)
- **Role** - Rôles d'autorisation
- **Aptitude** - Compétences/qualifications des utilisateurs
- **PlanningSemaine** - Planning hebdomadaire contenant les créneaux
- **PlanningCreneau** - Créneaux individuels du planning
- **ActiviteExterne** - Activité externe pouvant faire l'objet d'invitations (ex. fosse), avec un texte par défaut pré-rempli dans le commentaire des invitations
- **InvitationFosse** - Invitation à une date pour une activité externe : aptitudes conviées, rôles qui la gèrent, responsable (DP) éventuel, commentaire
- **ReponseFosse** - Réponse d'un membre à une invitation (en attente, inscrit, refus, désistement), avec un lien personnel
- **Communication** - Communication officielle (titre, contenu HTML, auteur, état d'envoi)
- **RolePermission** - Permission accordée à un rôle (modifiable depuis l'administration)
- **MailConfiguration** - Configuration du plugin d'envoi (chiffrée)

### Flux typique

1. Les utilisateurs se connectent au système
2. Un administrateur crée/gère des plannings hebdomadaires
3. Des créneaux sont attribués aux utilisateurs selon leurs aptitudes
4. Interface interactive avec Stimulus/Turbo pour UX fluide

## 🔧 Développement

### Accès au shell du container

```bash
make shell
```

### Commandes Symfony utiles

```bash
# Créer les aptitudes, les rôles et, sur une base vierge, les permissions par défaut
php bin/console app:seed [--admin-username=... --admin-password=...]

# Vérifier le Twig et le conteneur
php bin/console lint:twig templates
php bin/console lint:container
```

```bash
# Créer une migration après modification des entités
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Créer une entité
php bin/console make:entity

# Créer un contrôleur
php bin/console make:controller

# Lancer les tests
php bin/phpunit
```

### Éditeur de mails et JavaScript

Les dépendances JavaScript (dont Quill, pour l'éditeur des commentaires d'invitation, des textes par défaut et des communications) sont gérées par l'Asset Mapper : `importmap.php` et `assets/vendor/`. Pas de build Node. Les contrôleurs Stimulus de l'espace administrateur sont `assets/controllers/wysiwyg_controller.js` (éditeur riche) et `mail_test_controller.js` (mail test d'invitation).

### Base de données

Adminer est disponible via `make tools` sur `http://127.0.0.1:8081`
- Serveur : `db`
- Utilisateur : voir `DB_USER` dans `.env`
- Mot de passe : voir `DB_PASSWORD` dans `.env`
- Base : voir `DB_NAME` dans `.env`

## 🔒 RGPD : information, purge et rappels

Le site traite des données personnelles de membres (nom, prénom, e-mail, téléphone, aptitudes, réponses aux invitations). Les démarches RGPD sont documentées dans [`docs/rgpd/`](docs/rgpd/) :

| Fichier | Contenu |
|---|---|
| `00-plan-d-action.md` | Plan d'action : interne, communication externe, démarches auprès de l'État |
| `01-mail-information-membres.md` | Mail d'information aux membres (version majeurs et représentants légaux) |
| `02-mention-mails-inscription.md` | Mention à ajouter aux mails du site |
| `03-politique-confidentialite.md` | Mentions légales et politique de confidentialité |
| `04-registre-traitements.md` | Registre des traitements |
| `05-procedures.md` | Droits des personnes, violation de données, demandes de tiers, exploitation (purge, cron) |

### Pages publiques

- `/confidentialite` : politique de confidentialité
- `/mentions-legales` : mentions légales
- Lien dans le pied de page du site et dans le pied de tous les mails

Le texte des pages est dans `app/templates/rgpd/`. Les champs entre crochets (président, siège, n° RNA, hébergeur, durée des journaux) sont à compléter.

### Règles de conservation

- **Purge annuelle le 31 août** : suppression des comptes utilisateurs et des invitations créés jusqu'à cette date, avec leurs réponses et demandes RGPD. Les comptes **administrateurs sont conservés**.
- La date de création (`created_at`) de chaque compte et invitation sert de référence. Les données existantes à la migration (octobre 2026) seront donc purgées le 31/08/2027.

### Commandes

| Commande | Rôle |
|---|---|
| `app:rgpd:purger` | Purge du 31/08. Refuse de tourner hors du 31/08 sans `--force`. `--dry-run` affiche les volumes, `--date=AAAA-MM-JJ` teste une autre date. |
| `app:rgpd:rappel-purge` | Rappel au bureau et aux administrateurs : envoyé à J-60, J-30, J-7 et J-1, avec le détail de ce qui sera supprimé. `--dry-run` liste les destinataires sans envoyer, `--date` teste une autre date. |
| `app:rgpd:envoyer-information` | Mail d'information aux membres, une fois par version du texte. `--representants=a@x.fr,b@y.fr` adresse la variante « représentant légal » aux mineurs. `--dry-run` disponible. |

```bash
# Dans le conteneur web (make shell), ou depuis l'hôte avec docker compose exec web
php bin/console app:rgpd:rappel-purge --date=2026-08-30 --dry-run
php bin/console app:rgpd:purger --date=2026-08-31 --dry-run
php bin/console app:rgpd:envoyer-information --dry-run
```

### Planification (cron sur le serveur)

À adapter au chemin de déploiement (`[CHEMIN_DU_PROJET]`) :

```cron
# Rappels bureau : chaque jour à 08h, n'envoie un mail que J-60, J-30, J-7 et J-1
0 8 * * * cd [CHEMIN_DU_PROJET] && docker compose exec -T web php bin/console app:rgpd:rappel-purge --no-interaction >> /var/log/cn95-rgpd.log 2>&1

# Purge : le 31 août à 06h
0 6 31 8 * cd [CHEMIN_DU_PROJET] && docker compose exec -T web php bin/console app:rgpd:purger --no-interaction >> /var/log/cn95-rgpd.log 2>&1
```

### Mise en production (première fois)

```bash
php bin/console doctrine:migrations:migrate                       # Version20261015100000 (demande_rgpd, created_at)
php bin/console app:rgpd:envoyer-information --dry-run            # vérifier le nombre de destinataires
php bin/console app:rgpd:envoyer-information                      # ajouter --representants=... pour les mineurs
```

Vérifier que `DEFAULT_URI` est défini dans l'environnement de production (`deploy/deploy.sh` le fait).

### Tests

`app/tests/Unit/Entity/DemandeRgpdTest.php` couvre les jetons et les réponses. La purge, le parcours de consentement et le rappel de purge ont été vérifiés par un script temporaire, exécuté dans une transaction annulée et supprimé ensuite.

---

## 📝 Notes Importantes

- **Versioning Git** : Seuls les fichiers custom sont trackés (`vendor/`, `var/`, `.env` sont ignorés)
- **Container web** : Tourne sous l'utilisateur `app` (UID 1000 par défaut)
- **Apache** : Utilise mod_rewrite pour les URLs amies de Symfony
- **Sécurité** : En production, adapter les configurations de sécurité Apache
- **Assets** : Utilise AssetMapper (pas de webpack) - plus simple et intégré

## 🔐 Sécurité en Production

À faire avant déploiement :
- [ ] Modifier tous les mots de passe par défaut
- [ ] Définir `APP_ENV=prod` et `APP_DEBUG=false`
- [ ] Générer une clé `APP_SECRET` unique
- [ ] Configurer HTTPS
- [ ] Adapter la configuration de sécurité Apache
- [ ] Utiliser un gestionnaire de secrets pour les variables sensibles

## 📚 Ressources Utiles

- [Documentation Symfony 7.4](https://symfony.com/doc/7.4/index.html)
- [Doctrine ORM](https://www.doctrine-project.org/)
- [Twig Documentation](https://twig.symfony.com/)
- [Stimulus JS](https://stimulus.hotwired.dev/)
- [Turbo](https://turbo.hotwired.dev/)

## 📞 Support & Contributions

Pour toute question ou amélioration, ouvrir une issue ou une pull request sur [le repository GitHub](https://github.com/JLPoulpe/CN95).

---

**Dernière mise à jour** : 2026-10-14  
**Créé avec** ❤️ par Claude Code
