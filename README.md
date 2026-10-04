# CN95 - Planning Management System

Un système de gestion de plannings et de créneaux avec gestion des utilisateurs, des rôles et des aptitudes.

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
```

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
│   │   │   └── PlanningCreneau.php    # Créneaux de planning
│   │   ├── Controller/          # Contrôleurs (routes & logique HTTP)
│   │   ├── Form/                # Types de formulaires Symfony
│   │   ├── Repository/          # Requêtes personnalisées (Doctrine)
│   │   ├── Service/             # Services métier
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
```

L'URL de connexion à la base de données est automatiquement définie pour Docker Compose.

## 🏗️ Architecture

### Entités principales

- **User** - Utilisateurs du système (email, password, etc.)
- **Role** - Rôles d'autorisation
- **Aptitude** - Compétences/qualifications des utilisateurs
- **PlanningSemaine** - Planning hebdomadaire contenant les créneaux
- **PlanningCreneau** - Créneaux individuels du planning

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

### Base de données

Adminer est disponible via `make tools` sur `http://127.0.0.1:8081`
- Serveur : `db`
- Utilisateur : voir `DB_USER` dans `.env`
- Mot de passe : voir `DB_PASSWORD` dans `.env`
- Base : voir `DB_NAME` dans `.env`

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

**Dernière mise à jour** : 2026-10-04  
**Créé avec** ❤️ par Claude Code
