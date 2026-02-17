# Analyse des scripts JavaScript — Les Moussaillons Numériques

## 1. Inventaire

### Fichiers `.js` dédiés (assets/js/)

| Fichier | Contenu actuel | Chargé dans les pages ? |
|---------|----------------|--------------------------|
| **quiz_engine.js** | Uniquement un bloc de commentaires (documentation du moteur de quiz). Aucun code exécutable. | Non (jamais référencé dans les PHP/HTML) |
| **ambiance.js** | Deux stubs : `spawnMouette()`, `initParallax()` avec commentaires. Aucune implémentation. | Non |

Conclusion : ces deux fichiers sont des **coquilles vides** ou de la doc. Toute la logique réelle est en **script inline** dans les pages PHP.

---

### Scripts inline (dans les pages PHP)

| Page | Rôle | Taille approximative | Dépendances |
|------|------|----------------------|-------------|
| **index.php** | Bascule entre formulaires (élève / enseignant / admin) : `openForm(id)` | ~5 lignes | Aucune |
| **exercices.php** | Moteur de quiz : affichage questions, vérification, envoi score en POST, célébrations (confetti) | ~110 lignes | canvas-confetti (CDN) |
| **port.php** | Mouettes animées : `genererMouette()` (taille/vitesse via `r = sqrt(random)`), `setInterval` | ~35 lignes | Aucune |
| **transition.php** | Animation « marée + bateau » puis redirection : `lancerTransition()` (exposé sur `window`) | ~30 lignes | Aucune |
| **themes.php** | Carte SVG des îles : `createPath`, `generateWaves`, `generatePalmTree`, `renderWorld`, `handleClick` ; données injectées en PHP (`mapData`, `CONFIG`) | ~230 lignes | Aucune (vanilla) |
| **admin/login.php** | Connexion admin en AJAX : `authAdmin()`, fetch vers `api/login.php` | ~45 lignes | Aucune |
| **enseignant/student_details.php** | Graphique radar (Chart.js) avec données PHP | ~30 lignes | Chart.js (CDN) |
| **enseignant/edit_activity.php** | Summernote + synchronisation options/réponse correcte (jQuery) | ~35 lignes | jQuery, Summernote (CDN) |

---

## 2. Points forts

- **Fonctionnel** : le quiz, les mouettes, la transition, la carte des thèmes et les formulaires marchent.
- **Pas de surcharge** : pas de framework lourd ; mélange raisonnable de vanilla JS et de librairies ciblées (Chart.js, Summernote, confetti).
- **Données côté serveur** : les données sensibles ou dynamiques (quiz, carte, stats) viennent du PHP (`json_encode`, etc.), ce qui est cohérent avec ton architecture.

---

## 3. Problèmes et risques

### 3.1 Fichiers JS inutilisés

- **quiz_engine.js** et **ambiance.js** ne sont jamais chargés. Soit tu y déplaces la logique (quiz dans un fichier, mouettes/parallax dans l’autre), soit tu les supprimes ou les transformes en vraie doc (ex. README dans `assets/js/`).

### 3.2 Duplication de code

- Dans **themes.php**, la fonction **`generatePalmTree`** est définie **deux fois** (blocs quasi identiques vers les lignes 284 et 305). À garder une seule fois.

### 3.3 Tout en inline

- La logique métier (quiz, carte, mouettes, transition) est dans les PHP. Conséquences :
  - Difficile à tester (pas de fichiers JS à lancer sous Node ou en tests unitaires).
  - Pas de cache navigateur dédié au JS (les gros blocs sont re-téléchargés à chaque page).
  - Réutilisation ou partage du code entre pages compliqué.

### 3.4 Chemins et contexte

- Dans **port.php**, le script inline contient `../../assets/img/mouette.png`. Si la page est servie depuis une autre profondeur (ex. `public/modules/eleve/port.php`), le chemin peut être faux. Mieux vaut un chemin absolu ou généré par PHP (ex. `<?php echo $baseUrl; ?>assets/img/mouette.png`) injecté en variable JS.

### 3.5 Styles de code mélangés

- **exercices.php** / **admin/login.php** : `async/await` + `fetch`.
- **edit_activity.php** : jQuery + `$(document).ready()`.
- **themes.php** / **port.php** / **transition.php** : vanilla JS.

Ce n’est pas bloquant, mais une convention (par ex. vanilla partout sauf où une lib l’impose) simplifierait la lecture et la maintenance.

### 3.6 Exposition globale

- **transition.php** fait `window.lancerTransition = function ...`. **themes.php** s’appuie dessus. Ça marche tant que transition est chargée avant la carte (ordre des includes). Si un jour tu découpes en modules, il faudra un point d’entrée clair (un seul script ou un petit loader) pour éviter les dépendances implicites.

---

## 4. Recommandations de structure

### Option A — Minimal (sans build)

1. **Supprimer ou documenter** `quiz_engine.js` et `ambiance.js` (s’ils ne portent pas de logique).
2. **Corriger la duplication** : une seule définition de `generatePalmTree` dans themes.php.
3. **Centraliser les chemins** : variable JS pour la base des assets (ex. `window.ASSETS_BASE = '<?php echo $baseUrl; ?>';`) et l’utiliser pour les images dans port.php (et ailleurs si besoin).
4. **Garder** les scripts inline pour l’instant, en les commentant clairement (ex. « Moteur de quiz », « Carte des thèmes ») pour faciliter les prochaines extractions.

### Option B — Extraire dans des fichiers JS (toujours sans build)

1. Créer **assets/js/quiz.js** : toute la logique quiz d’exercices.php (poserQuestion, verifier, terminerMission, lancerAnimations), en recevant les données et options en paramètres ou via `data-*` / un objet global initialisé par la page.
2. Créer **assets/js/port-ambiance.js** : logique des mouettes (genererMouette, interval), avec chemin d’image injecté par la page ou par un `data-asset-base` sur un élément.
3. Créer **assets/js/transition.js** : `lancerTransition` (exposé sur `window` ou un namespace du type `Moussaillons.transition`).
4. Créer **assets/js/themes-map.js** : createPath, generateWaves, generatePalmTree, renderWorld, handleClick ; la page fournit `mapData` et `CONFIG` (déjà en PHP) via une variable globale ou un `window.Moussaillons.mapData` / similaire.
5. Dans les PHP : remplacer les blocs inline par `<script src=".../assets/js/quiz.js">` (etc.) + un petit bloc qui initialise les données (ex. `window.quizData = <?php echo json_encode(...); ?>;`).
6. Garder **inline** uniquement les tout petits scripts (index openForm, admin authAdmin, student_details Chart, edit_activity Summernote) ou les extraire aussi dans de courts fichiers dédiés si tu veux tout en .js.

### Option C — Structure plus « app » (avec build optionnel)

- Un dossier **assets/js/sources/** (ou **src/js/**) avec des modules par feature : `quiz.js`, `ambiance.js`, `transition.js`, `themes-map.js`, `admin-login.js`, etc.
- Si tu introduis un bundler (Vite, Webpack, etc.) : entry point unique, build vers `assets/js/app.js` (ou découpé en chunks). Sinon, charger les fichiers un par un avec des `<script src="...">` dans l’ordre.
- Définir un petit namespace global (ex. `window.Moussaillons = { quiz: {}, map: {}, transition: {} }`) pour éviter de multiplier les variables globales.

---

## 5. Synthèse

| Critère | État actuel | Avis |
|--------|-------------|------|
| **Fonctionnalité** | OK | Quiz, carte, mouettes, transition, formulaires opérationnels. |
| **Structure** | Faible | Fichiers quiz_engine / ambiance inutilisés ; logique en inline ; duplication dans themes.php. |
| **Maintenabilité** | Moyenne | Gros blocs inline difficiles à tester et à réutiliser ; chemins en dur. |
| **Performance** | Correcte | Peu de JS total ; pas de cache dédié pour les gros blocs. |
| **Cohérence** | Mélangée | Vanilla + jQuery + async/await ; pas de convention claire. |

En l’état, la structure JS est **pragmatique et suffisante** pour faire tourner l’app, mais **peu scalable**. Pour finaliser proprement :

1. Soit tu **nettoyes** (supprimer/expliquer les 2 .js, corriger la duplication, sécuriser les chemins) et tu restes en inline.
2. Soit tu **extrais** la logique dans des fichiers JS dédiés (Option B) pour pouvoir tester, cacher et réutiliser sans toucher tout de suite à un outil de build.

Tu peux considérer ce document comme une base pour décider jusqu’où aller (minimal vs extraction vs future base pour un build).
