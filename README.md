# 🌱 PilotEco Backend API

API REST pour l'évaluation et la gestion de l'empreinte carbone des entreprises, développée avec Symfony et FrankenPHP.

## 🚀 Démarrage rapide

### Prérequis
- [Docker](https://www.docker.com/) et Docker Compose (v2.10+)
- Git

### Installation

```bash
# 1. Cloner le repository
git clone <repository-url>
cd piloteco-back

# 2. Démarrer l'environnement Docker
make start

# 3. Installer les dépendances
make composer c="install"

# 4. Initialiser la base de données
make sf c="doctrine:migrations:migrate"
make sf c="doctrine:fixtures:load"
```

### Accès
- **API** : http://localhost/api
- **Documentation** : http://localhost/api/docs
- **Interface Swagger** : http://localhost/api/docs.json

## 🛠️ Commandes de développement

### Gestion des conteneurs

```bash
# Démarrer l'application
make start              # Construire et démarrer

# Gestion des conteneurs
make up                 # Démarrer les conteneurs
make down               # Arrêter les conteneurs
make logs               # Voir les logs en temps réel

# Shell et débogage
make sh                 # Accéder au conteneur PHP
```

### Symfony et base de données

```bash
# Composer
make composer c="install"                    # Installer les dépendances
make composer c="require vendor/package"     # Ajouter un package

# Commandes Symfony
make sf c="cache:clear"                      # Vider le cache
make sf c="debug:router"                     # Voir les routes
make sf c="doctrine:migrations:migrate"      # Exécuter les migrations
make sf c="doctrine:fixtures:load"           # Charger les fixtures
```

## 🧪 Validation Docker

### Script de validation d'environnement

Le projet inclut un script `test-docker.sh` pour valider que l'environnement Docker fonctionne correctement :

```bash
# Validation complète de l'environnement
./test-docker.sh
```

### Tests automatisés

Le script effectue 4 vérifications essentielles :

```bash
✅ Conteneurs Docker démarrés
✅ PostgreSQL accessible  
✅ API HTTP répond
✅ Port 80 accessible
```

### Informations fournies

Le script affiche un résumé complet :
- **État des conteneurs** avec statut et ports
- **Points d'accès** (API, documentation, base)
- **Services validés** (FrankenPHP, Symfony, PostgreSQL)
- **Commandes utiles** pour la suite

### Tests PHPUnit

Pour les tests unitaires/intégration Symfony :

```bash
# Tests via Makefile
make sf c="test"                             # Tous les tests
make sf c="test tests/Api/UserTest.php"      # Test spécifique
make sf c="test --coverage-html var/coverage" # Avec couverture
```

### Workflow de validation recommandé

```bash
# 1. Démarrer l'environnement
make start

# 2. Valider la configuration Docker
./test-docker.sh

# 3. Exécuter les tests applicatifs
make sf c="test"

# 4. Vérifier avant commit
./test-docker.sh && make sf c="test"
```

## 🏗️ Architecture du projet

### Architecture technique

```
┌─────────────────────────────────────────────────────────────┐
│                     Frontend (React)                        │
│                    piloteco-frontend                        │
│                     Port 3000                              │
└─────────────────────┬───────────────────────────────────────┘
                      │ HTTP/HTTPS Requests
                      │ (JWT Authentication)
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                  FrankenPHP + Caddy                        │
│               Reverse Proxy & HTTPS                        │
│                Port 80 (HTTP) / 443 (HTTPS)               │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                   Symfony 7.x API                          │
│                 piloteco-backend                            │
│                                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │ Controllers │  │   Services  │  │    API Platform     │ │
│  │             │  │             │  │                     │ │
│  │ • Auth      │  │ • Carbon    │  │ • Auto Doc          │ │
│  │ • Assessment│  │ • Company   │  │ • Validation        │ │
│  │ • User      │  │ • Report    │  │ • Serialization     │ │
│  │ • Company   │  │ • Export    │  │ • Pagination        │ │
│  └─────────────┘  └─────────────┘  └─────────────────────┘ │
│                                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │  Entities   │  │ Repositories│  │    Security         │ │
│  │             │  │             │  │                     │ │
│  │ • User      │  │ • Doctrine  │  │ • JWT Auth          │ │
│  │ • Company   │  │ • Custom    │  │ • Role-based        │ │
│  │ • Assessment│  │ • Queries   │  │ • CORS              │ │
│  │ • Emission  │  │             │  │ • Validation        │ │
│  └─────────────┘  └─────────────┘  └─────────────────────┘ │
└─────────────────────┬───────────────────────────────────────┘
                      │ Doctrine ORM
                      │ (PDO PostgreSQL)
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                 PostgreSQL 16                               │
│                                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │   Tables    │  │   Indexes   │  │    Constraints      │ │
│  │             │  │             │  │                     │ │
│  │ • users     │  │ • Primary   │  │ • Foreign Keys      │ │
│  │ • companies │  │ • Foreign   │  │ • Unique Keys       │ │
│  │ • assessments│ │ • Composite │  │ • Check Rules       │ │
│  │ • emissions │  │ • Text      │  │ • Not Null          │ │
│  └─────────────┘  └─────────────┘  └─────────────────────┘ │
│                        Port 5432                           │
└─────────────────────────────────────────────────────────────┘
```

### Structure des dossiers détaillée

```
piloteco-back/
├── 🐳 Docker Configuration
│   ├── compose.yaml              # Configuration Docker principale
│   ├── compose.override.yaml     # Override pour développement
│   ├── compose.prod.yaml         # Configuration production
│   ├── Dockerfile               # Image FrankenPHP personnalisée
│   └── frankenphp/              # Configuration serveur
│       ├── Caddyfile            # Configuration Caddy
│       └── conf.d/              # Configuration PHP
│
├── 🔧 Configuration Symfony
│   ├── config/
│   │   ├── packages/            # Configuration des bundles
│   │   │   ├── api_platform.yaml
│   │   │   ├── doctrine.yaml
│   │   │   ├── lexik_jwt_authentication.yaml
│   │   │   └── security.yaml
│   │   ├── routes/              # Configuration des routes
│   │   └── jwt/                 # Clés de chiffrement JWT
│   │
│   ├── 📊 Application Logic
│   ├── src/
│   │   ├── Entity/              # Modèles de données
│   │   │   ├── User.php
│   │   │   ├── Company.php
│   │   │   ├── CarbonAssessment.php
│   │   │   └── Emission.php
│   │   │
│   │   ├── Controller/          # Points d'entrée API
│   │   │   ├── AuthController.php
│   │   │   ├── AssessmentController.php
│   │   │   ├── UserController.php
│   │   │   └── CompanyController.php
│   │   │
│   │   ├── Service/             # Logique métier
│   │   │   ├── CarbonCalculatorService.php
│   │   │   ├── CompanyService.php
│   │   │   ├── ReportService.php
│   │   │   └── ExportService.php
│   │   │
│   │   ├── Repository/          # Accès aux données
│   │   │   ├── UserRepository.php
│   │   │   ├── CompanyRepository.php
│   │   │   └── AssessmentRepository.php
│   │   │
│   │   ├── Dto/                 # Data Transfer Objects
│   │   │   ├── UserRegistrationRequest.php
│   │   │   ├── AuthenticationResponse.php
│   │   │   └── UserResponse.php
│   │   │
│   │   └── Exception/           # Exceptions personnalisées
│   │       ├── AppException.php
│   │       ├── ConflictException.php
│   │       └── ValidationException.php
│   │
│   ├── 🗄️ Base de données
│   ├── migrations/              # Migrations Doctrine
│   │   ├── Version20250220232635.php
│   │   ├── Version20250223175553.php
│   │   └── ...
│   │
│   ├── src/DataFixtures/        # Données de test
│   │   └── AppFixtures.php
│   │
│   ├── 🧪 Tests
│   ├── tests/
│   │   ├── Api/                 # Tests d'endpoints
│   │   ├── Controller/          # Tests de contrôleurs
│   │   ├── Integration/         # Tests d'intégration
│   │   └── Unit/               # Tests unitaires
│   │
│   ├── 📚 Documentation
│   ├── docs/
│   │   ├── build.md
│   │   ├── production.md
│   │   ├── troubleshooting.md
│   │   └── xdebug.md
│   │
│   └── 🛠️ Outils de développement
│       ├── Makefile             # Commandes de développement
│       ├── phpunit.xml.dist     # Configuration des tests
│       ├── composer.json        # Dépendances PHP
│       └── symfony.lock         # Versions des recipes
```

### Flux de données

#### 1. Authentification JWT
```
Client → POST /api/login → AuthController → User Entity → JWT Token → Client
```

#### 2. Évaluation carbone
```
Client → POST /api/assessments → AssessmentController → CarbonCalculatorService → 
Assessment Entity → Database → Response with calculated emissions
```

#### 3. Gestion des entreprises
```
Client → GET /api/companies → CompanyController → CompanyRepository → 
Company Entity → Serialization → JSON Response
```

### Technologies utilisées

| Composant | Technologie | Version | Rôle |
|-----------|-------------|---------|------|
| **Framework** | Symfony | 7.x | Framework PHP moderne |
| **Serveur** | FrankenPHP | Latest | Serveur PHP haute performance |
| **Proxy** | Caddy | 2.x | Reverse proxy + HTTPS automatique |
| **Base de données** | PostgreSQL | 16 | Base de données relationnelle |
| **API** | API Platform | 3.x | Framework API REST/GraphQL |
| **ORM** | Doctrine | 3.x | Object-Relational Mapping |
| **Auth** | Lexik JWT | 3.x | Authentification JWT |
| **Tests** | PHPUnit | 10.x | Framework de tests |
| **Conteneurs** | Docker | 24.x | Containerisation |

### Patterns architecturaux

#### 🎯 **Domain-Driven Design (DDD)**
- **Entities** : Modèles métier avec logique
- **Services** : Logique métier complexe
- **Repositories** : Abstraction d'accès aux données
- **DTOs** : Transfert de données entre couches

#### 🔄 **API-First Design**
- **API Platform** : Documentation automatique
- **OpenAPI/Swagger** : Spécification standardisée
- **Validation** : Contraintes automatiques
- **Sérialisation** : Transformation automatique

#### 🛡️ **Security by Design**
- **JWT Authentication** : Stateless et sécurisé
- **CORS** : Protection cross-origin
- **Rate Limiting** : Protection contre les abus
- **Input Validation** : Validation stricte des données

#### 📊 **Performance-Oriented**
- **FrankenPHP Worker** : Mode worker pour performance
- **HTTP/2 & HTTP/3** : Protocoles modernes
- **OpCache** : Cache de bytecode PHP
- **Database Indexing** : Optimisation des requêtes

### Environnements

| Environnement | Configuration | Base de données | Serveur | Debug |
|---------------|---------------|-----------------|---------|-------|
| **Développement** | `.env` | PostgreSQL local | FrankenPHP:80 | ✅ |
| **Test** | `.env.test` | PostgreSQL test | Memory | ✅ |
| **Production** | `.env.prod` | PostgreSQL prod | FrankenPHP:443 | ❌ |

## 🔐 Authentification

### JWT Authentication

```bash
# Endpoints d'authentification
POST /api/register      # Inscription
POST /api/login         # Connexion
GET  /api/me           # Profil utilisateur
```

### Utilisation

```bash
# 1. S'inscrire ou se connecter
curl -X POST http://localhost/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'

# 2. Utiliser le token retourné
curl -X GET http://localhost/api/assessments \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## 🗄️ Base de données

### Gestion des migrations

```bash
# Créer une migration
make sf c="make:migration"

# Exécuter les migrations
make sf c="doctrine:migrations:migrate"

# Voir le statut des migrations
make sf c="doctrine:migrations:status"

# Rollback
make sf c="doctrine:migrations:execute --down VERSION"
```

### Fixtures

```bash
# Charger les données de test
make sf c="doctrine:fixtures:load"

# Charger sans confirmation
make sf c="doctrine:fixtures:load --no-interaction"
```

## 📋 API Endpoints

### Assessments (Évaluations carbone)

```bash
GET    /api/assessments           # Liste des évaluations
POST   /api/assessments           # Créer une évaluation
GET    /api/assessments/{id}      # Détail d'une évaluation
PUT    /api/assessments/{id}      # Modifier une évaluation
DELETE /api/assessments/{id}      # Supprimer une évaluation
```

### Users (Utilisateurs)

```bash
GET    /api/users                # Liste des utilisateurs (admin)
GET    /api/users/{id}           # Profil utilisateur
PUT    /api/users/{id}           # Modifier un utilisateur
```

### Companies (Entreprises)

```bash
GET    /api/companies            # Liste des entreprises
POST   /api/companies            # Créer une entreprise
GET    /api/companies/{id}       # Détail d'une entreprise
```

## 🐳 Docker

### Services

- **php** : FrankenPHP + Symfony (port 80/443)
- **database** : PostgreSQL 16 (port 5432)

### Configuration

Voir [DOCKER.md](DOCKER.md) pour la documentation complète Docker.

## 🔧 Développement

### Débogage

```bash
# Activer Xdebug
echo "XDEBUG_MODE=debug" >> .env
make down && make start

# Voir les logs
make logs

# Profiler Symfony
make sf c="debug:config"
make sf c="debug:container"
```

### Code Style

- Suivre les standards PSR-12
- Utiliser les type hints
- Documenter les méthodes publiques avec PHPDoc
- Garder les contrôleurs légers

### Git Workflow

```bash
# Après un pull
make composer c="install"
make sf c="doctrine:migrations:migrate"

# Avant un push
make sf c="test"
make sf c="cache:clear"
```

## 📚 Documentation

- [Configuration Docker](DOCKER.md)
- [Makefile Commands](docs/makefile.md)
- [Production Deployment](docs/production.md)
- [Troubleshooting](docs/troubleshooting.md)

## 🎯 Features

- ✅ Production, développement et CI ready
- ✅ Performance optimale avec FrankenPHP Worker mode
- ✅ HTTPS automatique (dev et prod)
- ✅ Support HTTP/3 et Early Hints
- ✅ Messagerie temps réel avec Mercure
- ✅ Intégration native XDebug
- ✅ Documentation API automatique
- ✅ Tests automatisés complets

---

*Consultez `make help` pour voir toutes les commandes disponibles.*
