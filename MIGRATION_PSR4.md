# Guide de migration vers PSR-4 — Les Moussaillons Numériques

Ce document décrit les étapes pour migrer le projet progressivement vers PSR-4 (autoload, namespaces, structure moderne) sans tout casser.

---

## 1. Ce qui est déjà en place

- **Composer** : `composer.json` avec autoload PSR-4 `Jf\Moussaillons\` → `src/`
- **Première classe** : `src/Infrastructure/Database.php` (Singleton PDO, namespace `Jf\Moussaillons\Infrastructure`)

---

## 2. Plan de migration en 5 étapes

### Étape 1 — Bootstrap et autoload (à faire en premier)

**Objectif** : Charger l’autoload Composer et exposer `$pdo` via la classe `Database` pour garder le code actuel fonctionnel.

1. **Créer un fichier d’amorçage** (ex. `bootstrap.php` à la racine) qui :
   - charge `vendor/autoload.php`
   - définit les constantes (DB_HOST, DB_NAME, etc.) si tu ne veux pas les laisser dans `config.php`
   - (optionnel) crée `$pdo` pour rétrocompatibilité

2. **Modifier `includes/config.php`** pour :
   - inclure `bootstrap.php` (ou `vendor/autoload.php` uniquement) en tout premier
   - remplacer la création manuelle de PDO par :
     ```php
     $pdo = \Jf\Moussaillons\Infrastructure\Database::getInstance()->getConnection();
     ```
   - garder le reste (session, constantes, fonctions métier) tel quel

3. Lancer **`composer dump-autoload`** après toute modification de l’autoload.

**Résultat** : Tous les `require_once 'config.php'` existants continuent de fournir `$pdo` et les fonctions, en s’appuyant sur la classe PSR-4.

---

### Étape 2 — Centraliser les constantes (optionnel mais recommandé)

Créer une classe ou un fichier de configuration dans `src/` pour ne plus dépendre de `define()` dispersés.

**Option A — Classe simple**  
`src/Infrastructure/Config.php` :

```php
namespace Jf\Moussaillons\Infrastructure;

final class Config
{
    public static function dbHost(): string { return getenv('DB_HOST') ?: 'localhost'; }
    public static function dbName(): string { return getenv('DB_NAME') ?: 'moussaillons'; }
    public static function dbUser(): string { return getenv('DB_USER') ?: 'admin'; }
    public static function dbPass(): string { return getenv('DB_PASS') ?: 'admin'; }
    public static function pointsParQuiz(): int { return (int) (getenv('POINTS_PAR_QUIZ') ?: 10); }
    public static function maintenanceMode(): bool { return filter_var(getenv('MAINTENANCE_MODE'), FILTER_VALIDATE_BOOLEAN); }
}
```

Ensuite adapter `Database::createFromConstants()` pour utiliser `Config` si tu adoptes cette option.

**Option B**  
Garder les `define()` dans `config.php` et ne rien changer pour l’instant.

---

### Étape 3 — Remplacer les fonctions globales par des classes

Les fonctions dans `config.php` sont utilisées partout. Pour rester PSR-4 et testables, les déplacer dans des classes.

| Fonction actuelle                            | Destination proposée                                                           | Rôle                              |
| -------------------------------------------- | ------------------------------------------------------------------------------ | --------------------------------- |
| `verifierPortee($distance)`                  | `Jf\Moussaillons\Application\Session\PorteeChecker` ou service dédié           | Portée navale (session)           |
| `estEligibleAuxPoints($userId, $activityId)` | `Jf\Moussaillons\Application\Progression\EligibilitePoints` (avec PDO injecté) | Règle anti-triche                 |
| `s($data)`                                   | `Jf\Moussaillons\Infrastructure\Security\Xss::escape($data)`                   | Échappement XSS                   |
| `creerSession(...)` (dans index.php)         | `Jf\Moussaillons\Application\Auth\SessionCreator` ou équivalent                | Création de session + redirection |

**Stratégie** :  
- Créer les nouvelles classes dans `src/Application/` ou `src/Infrastructure/`.  
- Dans `config.php`, garder des **fonctions wrapper** qui appellent les classes, tant que tout le code utilise encore `verifierPortee()`, `s()`, etc.  
- Puis, au fil du temps, remplacer les appels dans les scripts par des `use Jf\...` et des appels aux classes.

---

### Étape 4 — Migrer les scripts d’entrée (optionnel)

Aujourd’hui : `index.php`, `api/*.php`, `modules/**/*.php` sont à la racine ou dans des dossiers non namespacés.

- **Option simple** : Ne pas déplacer les fichiers. Ils restent des “points d’entrée” qui font `require_once 'includes/config.php'` (ou bootstrap) et utilisent `$pdo` et les fonctions (puis les classes une fois migrées).
- **Option plus propre** :  
  - Créer un dossier `public/` avec `public/index.php`, `public/api/`, et déplacer `assets/` sous `public/`.  
  - Configurer le serveur web (Apache/Nginx) pour que la racine document soit `public/`.  
  - Les `require` dans les PHP devront alors remonter d’un niveau (ex. `require_once __DIR__ . '/../includes/config.php'`).

Tu peux faire l’étape 4 plus tard, une fois les étapes 1–3 en place.

---

### Étape 5 — Repositories et couche métier (avancé)

Pour aller vers une architecture plus claire :

- **Repositories** : classes dans `src/Infrastructure/Persistence/` ou `src/Repository/` qui encapsulent les requêtes SQL (ex. `UserRepository`, `ActivityRepository`, `HistoryRepository`). Les scripts ou contrôleurs reçoivent ces repositories (ou un conteneur) au lieu d’utiliser `$pdo` directement.
- **Services** : logique métier (connexion, enregistrement score, vérification portée) dans `src/Application/` qui utilisent les repositories et les services de session.

C’est une refonte plus large ; à faire après avoir stabilisé les étapes 1–3.

---

## 3. Structure de dossiers recommandée après migration

```
moussaillons/
├── composer.json
├── bootstrap.php          # charge vendor/autoload + constantes éventuelles
├── includes/
│   ├── config.php        # session, $pdo via Database, wrappers des anciennes fonctions
│   └── check_session.php
├── public/                # (optionnel) racine web
│   ├── index.php
│   ├── api/
│   └── assets/
├── src/
│   ├── Infrastructure/
│   │   ├── Database.php
│   │   ├── Config.php     # (optionnel)
│   │   └── Security/
│   │       └── Xss.php
│   └── Application/
│       ├── Auth/
│       └── Progression/
├── modules/               # peut rester tel quel, utilise config + classes
└── vendor/
```

Les fichiers sous `modules/`, `api/`, `index.php` restent des scripts “plats” qui incluent la config et appellent le code namespacé.

---

## 4. Ordre d’exécution recommandé

1. **Faire l’étape 1** (bootstrap + config qui utilise `Database::getInstance()`).  
2. Tester que tout fonctionne (connexion, login, parcours élève, espace enseignant).  
3. Introduire une première classe métier (ex. `Xss::escape` remplaçant `s()`) et un wrapper dans `config.php`.  
4. Ensuite enchaîner étapes 2, 3, puis 4 et 5 selon tes priorités.

---

## 5. Checklist rapide

- [x] `composer dump-autoload` exécuté (vendor/ présent)
- [x] `bootstrap.php` créé et chargé par `config.php`
- [x] `config.php` utilise `Database::getInstance()->getConnection()` pour `$pdo`
- [x] Aucun `require` vers des classes dans `src/` (uniquement autoload)
- [x] (Optionnel) Constantes centralisées dans `Config` ; Database utilise Config
- [x] (Optionnel) Fonctions globales remplacées par des classes et wrappers
- [x] (Optionnel) Racine web = `public/` et chemins mis à jour

Une fois l’étape 1 en place, le projet est déjà “PSR-4 ready” pour le nouveau code dans `src/`, tout en gardant l’existant opérationnel.

---

## 6. État actuel de la migration (vérification)

| Élément | Statut | Détail |
|--------|--------|--------|
| **Étape 1 — Bootstrap & autoload** | ✅ Fait | `bootstrap.php` charge `vendor/autoload.php`, définit les constantes (if !defined). `config.php` inclut le bootstrap en premier et expose `$pdo` via `Database::getInstance()->getConnection()`. |
| **Étape 2 — Config** | ✅ Fait | `src/Infrastructure/Config.php` existe. `Database::createFromConstants()` utilise `Config::dbHost()`, `Config::dbName()`, `Config::dbUser()`, `Config::dbPass()`. Les `define()` restent en parallèle (bootstrap + config avec `if (!defined)`), donc pas de conflit. |
| **Étape 3 — Fonctions globales** | ✅ Fait | Classes : `PorteeChecker`, `EligibilitePoints`, `Xss`, `SessionCreator`. Wrappers dans `config.php` pour `verifierPortee()`, `estEligibleAuxPoints()`, `s()`. `index.php` appelle `SessionCreator::create()`. |
| **Étape 4 — Scripts / public/** | ✅ Fait | Dossier `public/` créé avec `index.php`, `api/`, `modules/`, `assets/`. Chemins en `__DIR__ . '/../includes/config.php'` etc. Racine web = `public/` recommandée. |
| **Étape 5 — Repositories** | ✅ Fait | `UserRepository`, `ActivityRepository`, `HistoryRepository`, `ShipRepository`, `StaffRepository`, `TeacherRepository` dans `src/Infrastructure/Persistence/`. Utilisables en `new XxxRepository($pdo)`. |

**Fichiers PSR-4 en place :**

- `src/Infrastructure/Database.php` — Singleton PDO, utilise Config
- `src/Infrastructure/Config.php` — Configuration (getenv + défauts)
- `src/Infrastructure/Security/Xss.php` — Échappement XSS (`escape()`)
- `src/Application/Session/PorteeChecker.php` — Portée navale (`verifier()`)
- `src/Application/Progression/EligibilitePoints.php` — Éligibilité points (`estEligible()`)
- `src/Application/Auth/SessionCreator.php` — Session + redirection (`create()`)
- `src/Infrastructure/Persistence/` — UserRepository, ActivityRepository, HistoryRepository, ShipRepository, StaffRepository, TeacherRepository

**Configuration :**

- La connexion BDD lit désormais les paramètres via **Config** (variables d’environnement). Si tu ne définis pas `DB_HOST`, etc. dans l’env, les valeurs par défaut de Config s’appliquent.
- (Obsolète) Les constantes ne sont plus utilisées (middleware de maintenance). Pour tout faire passer par Config, il faudrait remplacer `MAINTENANCE_MODE` par `Config::maintenanceMode()` dans `config.php`.
- Prochaine étape logique : créer les classes de l’étape 3 (ex. `Xss::escape` + wrapper `s()` dans config) puis migrer progressivement les appels.
