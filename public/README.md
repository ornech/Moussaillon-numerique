# Racine web — Les Moussaillons Numériques

Ce dossier est la **racine document recommandée** pour le serveur web.

- **Apache** : définir `DocumentRoot` sur ce dossier (`/chemin/vers/moussaillons/public`).
- **Nginx** : définir `root` sur ce dossier.
- **PHP built-in** : `php -S localhost:8000 -t public`

Les dossiers `includes/`, `src/`, `vendor/`, `bootstrap.php` restent à la racine du projet (hors web). Ne pas les exposer en HTTP.
