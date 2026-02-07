# Guide de mise à jour Symfony 7.2 vers 7.4

Ce document détaille la procédure complète pour mettre à jour votre projet Symfony de la version 7.2 vers 7.4.

## 📋 Prérequis

- PHP >= 8.4.3 (déjà configuré dans votre projet)
- Composer installé
- Accès Git au dépôt
- Sauvegarde complète de la base de données
- Environnement de développement fonctionnel

## 🔍 Vérifications préalables

### 1. Vérifier la version actuelle de Symfony

```bash
php bin/console --version
```

### 2. Vérifier les dépendances tierces

Les packages tiers suivants ont été vérifiés pour leur compatibilité avec Symfony 7.4 :

- ✅ **API Platform** (^4.0.18) - Compatible Symfony 7.4
- ✅ **Doctrine ORM** (^3.3.2) - Compatible Symfony 7.4
- ✅ **Doctrine DBAL** (^3.9.4) - Compatible Symfony 7.4
- ✅ **Lexik JWT Authentication** (^3.1.1) - Compatible Symfony 7.4
- ✅ **Nelmio CORS** (^2.5) - Compatible Symfony 7.4
- ✅ **Symfony Maker Bundle** (^1.62.1) - Compatible Symfony 7.4
- ✅ **PHPUnit** (^12.0) - Compatible Symfony 7.4

## 🚀 Procédure de mise à jour

### Étape 1 : Créer une branche de mise à jour

```bash
git checkout -b upgrade/symfony-7.4
```

### Étape 2 : Sauvegarder l'état actuel

```bash
# Créer un backup du composer.lock
cp composer.lock composer.lock.backup-7.2

# Créer une sauvegarde de la base de données (si applicable)
# Pour Docker:
make db-backup  # Si la commande existe
# OU
docker compose exec php php bin/console doctrine:schema:update --dump-sql > backup-schema.sql
```

### Étape 3 : Mettre à jour composer.json

Le fichier `composer.json` a déjà été mis à jour avec les versions Symfony 7.4.*

Vérifiez les changements :
```bash
git diff composer.json
```

### Étape 4 : Vider le cache Composer

```bash
composer clear-cache
```

### Étape 5 : Mettre à jour les dépendances

#### Option A : Mise à jour progressive (recommandée)

```bash
# Mettre à jour uniquement Symfony d'abord
composer update "symfony/*" --with-all-dependencies

# Puis mettre à jour les autres dépendances
composer update
```

#### Option B : Mise à jour complète (plus rapide mais plus risquée)

```bash
composer update
```

### Étape 6 : Mettre à jour les recettes Symfony

```bash
composer sync-recipes --force
```

**Important** : Cette commande peut proposer des modifications de fichiers de configuration. Examinez attentivement chaque changement proposé avant de l'accepter.

### Étape 7 : Vider le cache Symfony

```bash
# En développement
php bin/console cache:clear

# Pour tous les environnements
php bin/console cache:clear --env=prod
php bin/console cache:clear --env=dev
php bin/console cache:clear --env=test
```

#### Avec Docker (recommandé)

```bash
make cc
docker compose exec php php bin/console cache:clear --env=test
```

## ✅ Vérifications post-mise à jour

### 1. Vérifier les dépréciations

```bash
# En développement
php bin/console debug:container --deprecations

# Via Docker
docker compose exec php php bin/console debug:container --deprecations
```

**Actions à entreprendre** :
- Noter toutes les dépréciations listées
- Planifier leur correction avant Symfony 8.0
- Les dépréciations sont généralement accompagnées de messages indiquant comment les corriger

### 2. Vérifier la configuration

```bash
php bin/console debug:config

# Vérifier des configurations spécifiques
php bin/console debug:config framework
php bin/console debug:config security
php bin/console debug:config api_platform
```

### 3. Vérifier les services

```bash
php bin/console debug:autowiring
php bin/console lint:container
```

### 4. Vérifier les routes

```bash
php bin/console debug:router
```

### 5. Lancer les tests

```bash
# Initialiser la base de données de test
make init-test-db
make init-test-fixtures

# Lancer tous les tests
make test

# Ou lancer des tests spécifiques
make test-unit
make test-integration
```

**En cas d'échec** :
- Examiner les messages d'erreur
- Vérifier si des changements d'API sont mentionnés dans les logs
- Consulter le CHANGELOG de Symfony 7.4

### 6. Vérifier les migrations de base de données

```bash
# Vérifier si de nouvelles migrations sont nécessaires
php bin/console doctrine:migrations:status

# Générer une migration si nécessaire
php bin/console doctrine:migrations:diff

# Examiner la migration générée avant de l'exécuter
cat migrations/VersionXXXXXXXXXXXXXX.php
```

### 7. Tester l'application en environnement de développement

```bash
# Démarrer le serveur de développement
make up

# Vérifier les logs
make logs

# Tester les endpoints API principaux
curl -X GET http://localhost/api
```

## 🔧 Changements potentiels et adaptations

### Changements dans Symfony 7.4

Symfony 7.4 est une version mineure qui maintient la compatibilité ascendante. Les principaux changements incluent :

1. **Nouvelles fonctionnalités** :
   - Améliorations de performance
   - Nouveaux composants et fonctionnalités
   - Corrections de bugs

2. **Dépréciations** :
   - Certaines méthodes peuvent être marquées comme dépréciées
   - Ces dépréciations seront supprimées dans Symfony 8.0
   - Utilisez `debug:container --deprecations` pour les identifier

3. **Pas de changements cassants (Breaking Changes)** :
   - Symfony suit la versioning sémantique
   - Les versions mineures (7.x) ne contiennent pas de breaking changes
   - Votre code existant devrait fonctionner sans modification

### Vérifications spécifiques pour les packages tiers

#### API Platform

```bash
# Vérifier la configuration
php bin/console debug:config api_platform

# Tester les endpoints
curl -X GET http://localhost/api/docs.json
```

#### Lexik JWT

```bash
# Vérifier la configuration JWT
php bin/console debug:config lexik_jwt_authentication

# Tester l'authentification (adapter selon vos routes)
# curl -X POST http://localhost/api/login -d '{"username":"test","password":"test"}'
```

#### Doctrine

```bash
# Vérifier la configuration
php bin/console debug:config doctrine

# Valider le schéma
php bin/console doctrine:schema:validate
```

## 📊 Surveillance et monitoring

### Logs à surveiller après le déploiement

1. **Logs d'application** :
```bash
tail -f var/log/dev.log
tail -f var/log/prod.log
```

2. **Logs PHP** :
```bash
# Via Docker
docker compose logs php
```

3. **Métriques à surveiller** :
   - Temps de réponse des API
   - Erreurs 500
   - Utilisation mémoire
   - Requêtes base de données

## 🔄 Procédure de rollback

Si vous rencontrez des problèmes critiques après la mise à jour, voici la procédure de rollback :

### 1. Restaurer composer.lock

```bash
# Restaurer l'ancien composer.lock
cp composer.lock.backup-7.2 composer.lock

# Réinstaller les anciennes dépendances
composer install
```

### 2. Restaurer composer.json (si modifié)

```bash
# Revenir à la version précédente
git checkout HEAD~1 composer.json

# Réinstaller les dépendances
composer install
```

### 3. Vider les caches

```bash
php bin/console cache:clear
php bin/console cache:clear --env=prod
```

### 4. Restaurer la base de données (si nécessaire)

```bash
# Si des migrations ont été exécutées, revenir en arrière
php bin/console doctrine:migrations:migrate prev --no-interaction

# Ou restaurer depuis un backup
# mysql < backup.sql
# pg_restore < backup.sql
```

### 5. Redémarrer les services

```bash
# Avec Docker
make down
make up

# Vérifier les logs
make logs
```

### 6. Vérifier le rollback

```bash
# Vérifier la version Symfony
php bin/console --version

# Lancer les tests
make test

# Vérifier l'application
curl http://localhost/api
```

## 📝 Checklist finale

Avant de considérer la mise à jour comme terminée :

- [ ] `composer.json` est mis à jour vers 7.4.*
- [ ] `composer update` s'est exécuté sans erreur
- [ ] Les caches ont été vidés
- [ ] `php bin/console debug:container --deprecations` ne montre pas d'erreurs critiques
- [ ] Tous les tests passent (`make test`)
- [ ] L'application démarre sans erreur (`make up`)
- [ ] Les endpoints API principaux fonctionnent
- [ ] La documentation a été mise à jour
- [ ] Les changements sont commités sur Git
- [ ] Un backup de la base de données existe
- [ ] L'équipe a été informée de la mise à jour

## 📚 Ressources

- [Symfony 7.4 Release Notes](https://symfony.com/releases/7.4)
- [Symfony Upgrade Guide](https://github.com/symfony/symfony/blob/7.4/UPGRADE-7.4.md)
- [Symfony Documentation](https://symfony.com/doc/7.4/index.html)
- [API Platform Documentation](https://api-platform.com/docs/)
- [Doctrine Documentation](https://www.doctrine-project.org/)

## 🆘 Support

En cas de problème :

1. Consultez les logs d'erreur détaillés
2. Vérifiez les issues GitHub de Symfony 7.4
3. Consultez la documentation officielle
4. Utilisez la procédure de rollback en cas de blocage critique

## 🎯 Prochaines étapes après la mise à jour

1. **Corriger les dépréciations** :
   - Planifier la correction des dépréciations détectées
   - Créer des tickets pour chaque dépréciation

2. **Améliorer les tests** :
   - Ajouter des tests pour les nouvelles fonctionnalités
   - Améliorer la couverture de code

3. **Documenter les changements** :
   - Mettre à jour la documentation technique
   - Informer l'équipe des nouveautés

4. **Surveiller la production** :
   - Monitorer les performances
   - Vérifier les logs d'erreur
   - Recueillir les retours utilisateurs

5. **Planifier Symfony 8.0** :
   - Commencer à corriger les dépréciations
   - Suivre l'évolution de Symfony 8.0
   - Planifier la prochaine mise à jour majeure
