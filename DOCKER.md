# 🐳 PilotEco Backend - Configuration Docker

## Démarrage rapide

### Développement

```bash
# Démarrer en mode développement avec hot-reloading
make start

# Voir les logs
make logs

# Arrêter
make down
```

### Production

```bash
# Construire et démarrer en mode production
docker compose -f compose.yaml -f compose.prod.yaml up -d --build

# Vérifier la santé
curl http://localhost/api

# Arrêter
docker compose -f compose.yaml -f compose.prod.yaml down
```

## Variables d'environnement

### Développement (.env)

```bash
# Database
POSTGRES_DB=piloteco_dev
POSTGRES_USER=piloteco
POSTGRES_PASSWORD=piloteco123
POSTGRES_VERSION=16

# Application
APP_ENV=dev
APP_SECRET=your-secret-key-here
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-passphrase

# Server
SERVER_NAME=localhost
HTTP_PORT=80
HTTPS_PORT=443

# Mercure
CADDY_MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!

# Xdebug
XDEBUG_MODE=off
```

### Production (.env.prod)

```bash
# Database
POSTGRES_DB=piloteco_prod
POSTGRES_USER=piloteco_prod
POSTGRES_PASSWORD=secure-password-here
POSTGRES_VERSION=16

# Application
APP_ENV=prod
APP_SECRET=your-secure-secret-key
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-secure-passphrase

# Server
SERVER_NAME=api.piloteco.com
HTTP_PORT=80
HTTPS_PORT=443

# Security
TRUSTED_PROXIES=127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16
TRUSTED_HOSTS=^api\.piloteco\.com$$

# Mercure
CADDY_MERCURE_JWT_SECRET=your-secure-mercure-secret
```

## Accès

- **API Développement** : http://localhost/api
- **API Documentation** : http://localhost/api/docs
- **Base de données** : localhost:5432
- **Mercure Hub** : http://localhost/.well-known/mercure

## Architecture

```
┌─────────────────────────────────────┐
│            FrankenPHP               │
│        (Symfony + Caddy)            │
│                                     │
│    Port 80 (HTTP) / 443 (HTTPS)    │
└─────────────────────────────────────┘
                 │
                 │ HTTP/HTTPS + Mercure
                 ▼
┌─────────────────────────────────────┐
│          Symfony API                │
│     (Carbon Assessment API)         │
│                                     │
│  • JWT Authentication              │
│  • API Platform                    │
│  • Doctrine ORM                    │
│  • Mercure (Real-time)             │
└─────────────────────────────────────┘
                 │
                 │ PDO
                 ▼
┌─────────────────────────────────────┐
│         PostgreSQL 16               │
│        (Database)                   │
│                                     │
│  Port 5432                          │
└─────────────────────────────────────┘
```

## Commandes Makefile

### Gestion des conteneurs

```bash
# Construire les images
make build

# Démarrer les conteneurs
make up

# Construire et démarrer
make start

# Arrêter les conteneurs
make down

# Voir les logs en temps réel
make logs

# Accéder au shell du conteneur PHP
make sh
```

### Symfony et base de données

```bash
# Installer les dépendances
make composer c=install

# Exécuter une commande Symfony
make sf c="doctrine:migrations:migrate"

# Exemples utiles
make sf c="cache:clear"
make sf c="doctrine:schema:update --dump-sql"
make sf c="doctrine:fixtures:load"
```

### Développement

```bash
# Ajouter un package Composer
make composer c="require symfony/mailer"

# Exécuter les tests
make sf c="test"

# Vider le cache
make sf c="cache:clear"

# Voir les routes
make sf c="debug:router"
```

## Débogage

### Xdebug

Pour activer Xdebug en développement :

```bash
# Modifier .env
XDEBUG_MODE=debug

# Redémarrer les conteneurs
make down
make start
```

### Shell dans le conteneur

```bash
# Accéder au conteneur PHP
make sh

# Une fois dans le conteneur
php bin/console debug:router
php bin/console debug:container
composer show
```

## Commandes utiles

```bash
# Voir l'aide du Makefile
make help

# Accéder à la base de données
docker compose exec database psql -U ${POSTGRES_USER} -d ${POSTGRES_DB}

# Voir les conteneurs actifs
docker compose ps

# Voir les logs d'un service spécifique
docker compose logs -f php
docker compose logs -f database
```

## Structure des services

### php (FrankenPHP)
- **Image** : FrankenPHP avec Symfony
- **Ports** : 80 (HTTP), 443 (HTTPS)
- **Volumes** : Code source, configuration Caddy
- **Features** : HTTP/2, HTTP/3, Worker mode, Mercure

### database (PostgreSQL)
- **Image** : postgres:16-alpine
- **Port** : 5432
- **Données** : Volume persistant
- **Health check** : pg_isready

## Troubleshooting

### Problèmes courants

1. **Port 80 déjà utilisé**
   ```bash
   # Changer le port dans .env
   HTTP_PORT=8080
   make down && make start
   ```

2. **Erreur de permissions**
   ```bash
   # Fixer les permissions
   sudo chown -R $USER:$USER .
   ```

3. **Base de données non accessible**
   ```bash
   # Vérifier le statut
   docker compose ps
   
   # Recréer la base
   make sf c="doctrine:database:drop --force"
   make sf c="doctrine:database:create"
   make sf c="doctrine:migrations:migrate"
   ```

4. **Cache Symfony**
   ```bash
   # Vider complètement le cache
   make sf c="cache:clear --env=dev"
   # Ou depuis le conteneur
   make sh
   rm -rf var/cache/*
   ```

## Workflow de développement

### Configuration initiale

```bash
# 1. Cloner le projet
git clone <repository>
cd piloteco-back

# 2. Créer le fichier .env
cp .env.example .env
# Modifier les variables selon vos besoins

# 3. Démarrer l'environnement
make start

# 4. Installer les dépendances
make composer c=install

# 5. Exécuter les migrations
make sf c="doctrine:migrations:migrate"

# 6. Charger les fixtures (optionnel)
make sf c="doctrine:fixtures:load"
```

### Développement quotidien

```bash
# Démarrer la journée
make start
make logs  # pour voir les logs en temps réel

# Ajouter une nouvelle dépendance
make composer c="require vendor/package"

# Créer une migration
make sf c="make:migration"
make sf c="doctrine:migrations:migrate"

# Exécuter les tests
make sf c="test"

# Fin de journée
make down
```

### Débogage

```bash
# Voir les logs
make logs

# Accéder au shell
make sh

# Vérifier la configuration
make sf c="debug:config"
make sf c="debug:container"
make sf c="debug:router"
```

Consultez le [Makefile](Makefile) pour voir toutes les commandes disponibles avec `make help`.
