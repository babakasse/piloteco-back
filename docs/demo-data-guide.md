# Données de Démonstration - Guide d'utilisation

## Vue d'ensemble

Ce guide explique comment utiliser les données de démonstration sécurisées pour les environnements de production de PilotEco.

## Différence entre les fixtures

### AppFixtures (Développement)
- **Fichier** : `src/DataFixtures/AppFixtures.php`
- **Usage** : Environnement de développement uniquement
- **Commande** : `make db-fixtures`
- **Contenu** : Données de test génériques avec des comptes simples

### DemoFixtures (Production)
- **Fichier** : `src/DataFixtures/DemoFixtures.php`
- **Usage** : Démonstrations en production
- **Commande** : `make db-demo`
- **Contenu** : Données réalistes avec des comptes sécurisés

## Utilisation en production

### 1. Charger les données de démonstration

```bash
# Chargement standard (avec confirmations de sécurité)
make db-demo

# Forcer le chargement (sans confirmations)
make db-demo-force

# Réinitialisation complète avec données de démo
make db-reset
```

### 2. Comptes de démonstration disponibles

| Email | Mot de passe | Rôle | Entreprise |
|-------|--------------|------|------------|
| demo.admin@piloteco.fr | DemoPassword2024! | ADMIN | EcoTech Solutions |
| demo.manager@ecotech.fr | DemoPassword2024! | USER | EcoTech Solutions |
| demo.analyst@greenmanuf.fr | DemoPassword2024! | USER | Green Manufacturing Co. |
| demo.consultant@sustainable.fr | DemoPassword2024! | USER | Sustainable Logistics |
| demo.expert@cleanenergy.fr | DemoPassword2024! | USER | CleanEnergy Corp |

### 3. Données incluses

#### Entreprises (5)
- **EcoTech Solutions** (Technology) - Paris
- **Green Manufacturing Co.** (Industry) - Lyon  
- **Sustainable Logistics** (Shipping) - Marseille
- **CleanEnergy Corp** (Industry) - Nantes
- **BioCare Health** (Healthcare) - Toulouse

#### Évaluations carbone (4)
- 2 évaluations publiées (avec données complètes)
- 2 évaluations en brouillon (en cours)
- Données réalistes par secteur d'activité

#### Émissions par scope
- **Scope 1** : Émissions directes (chauffage, véhicules, etc.)
- **Scope 2** : Émissions indirectes liées à l'énergie
- **Scope 3** : Autres émissions indirectes (transport, matières premières, etc.)

## Sécurité

### Mots de passe
- Tous les comptes utilisent le même mot de passe sécurisé : `DemoPassword2024!`
- Les mots de passe sont hashés avec l'algorithme Symfony par défaut
- **Important** : Changez ces mots de passe après la démonstration

### Confirmations de sécurité
- La commande `app:load-demo-data` demande confirmation en production
- Utilisez `--force` uniquement si vous êtes certain de votre action
- Vérification des données existantes avant chargement

## Scénarios de démonstration

### Scénario 1 : Admin général
- **Compte** : demo.admin@piloteco.fr
- **Capacités** : Accès à toutes les entreprises et évaluations
- **Usage** : Démonstration des fonctionnalités administratives

### Scénario 2 : Utilisateur entreprise
- **Comptes** : demo.manager@ecotech.fr, demo.analyst@greenmanuf.fr, etc.
- **Capacités** : Accès limité aux données de leur entreprise
- **Usage** : Démonstration du workflow utilisateur standard

### Scénario 3 : Analyse comparative
- **Données** : Différents secteurs d'activité représentés
- **Usage** : Comparaison des émissions entre secteurs

## Nettoyage après démonstration

### Suppression des données de démo
```bash
# Option 1 : Supprimer tous les utilisateurs avec email "demo.*"
make sf c="app:cleanup-demo-users"

# Option 2 : Réinitialisation complète de la base
make sf c="doctrine:database:drop --force"
make sf c="doctrine:database:create"
make db-migrate
```

### Changement des mots de passe
Si vous conservez les comptes de démo, changez immédiatement les mots de passe :

```bash
make sf c="app:change-password demo.admin@piloteco.fr"
```

## Commandes disponibles

| Commande | Description |
|----------|-------------|
| `make db-demo` | Charge les données de démonstration avec confirmations |
| `make db-demo-force` | Force le chargement sans confirmations |
| `make db-reset` | Réinitialise complètement la DB avec données de démo |
| `make db-fixtures` | Charge les fixtures de développement (AppFixtures) |
| `make db-migrate` | Execute les migrations de base de données |

## Bonnes pratiques

1. **Avant une démonstration** :
   - Sauvegardez votre base de données de production
   - Chargez les données de démo dans un environnement dédié
   - Testez les scénarios de démonstration

2. **Pendant la démonstration** :
   - Utilisez les comptes spécifiques aux cas d'usage
   - Montrez les différents niveaux d'accès (admin vs user)
   - Exploitez la diversité des secteurs représentés

3. **Après la démonstration** :
   - Nettoyez ou changez les mots de passe
   - Restaurez la sauvegarde si nécessaire
   - Documentez les retours clients

## Support

Pour toute question sur l'utilisation des données de démonstration, consultez :
- Ce guide
- La documentation technique dans `/docs`
- Les logs d'application en cas de problème
