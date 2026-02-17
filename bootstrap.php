<?php

declare(strict_types=1);

/**
 * FICHIER D'AMORÇAGE PSR-4 — LES MOUSSAILLONS NUMÉRIQUES
 * Charge uniquement l'autoload Composer. La configuration passe par Config.
 */
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    die("Erreur : exécutez 'composer install' à la racine du projet.");
}
require_once $autoload;
