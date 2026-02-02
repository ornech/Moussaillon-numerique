<?php
/**
 * =============================================================================
 * CORE CONFIG - LES MOUSSAILLONS NUMÉRIQUES
 * =============================================================================
 * Ce fichier centralise la sécurité, la connexion PDO et les règles métier.
 */

// 1. PARAMÈTRES DE CONNEXION
define('DB_HOST', 'localhost');
define('DB_NAME', 'moussaillons');
define('DB_USER', 'admin');
define('DB_PASS', 'admin');

// 2. RÉGLAGES SYSTÈME
define('POINTS_PAR_QUIZ', 10); // Gain standard pour un 100%
define('MAINTENANCE_MODE', false); // Si true, bloque l'accès aux élèves

// 3. INITIALISATION PDO (Sécurité renforcée)
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // En production, ne jamais afficher $e->getMessage() pour éviter les fuites d'infos
    error_log($e->getMessage());
    die("Erreur critique : Impossible de joindre le port de plaisance.");
}

// 4. GESTION DES SESSIONS (idempotent)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * MIDDLEWARE DE MAINTENANCE
 * Bloque les élèves si le mode maintenance est actif.
 */
if (MAINTENANCE_MODE && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    die("🔧 Le port est actuellement en travaux. Revenez plus tard, matelots !");
}

// 5. FONCTIONS MÉTIER (LOGIQUE DE PROGRESSION)

/**
 * ALGORITHME : PORTÉE NAVALE
 * Vérifie si le navire actuel de l'élève permet d'accoster sur une île.
 */
function verifierPortee($distanceActivite) {
    // La portée est stockée en session lors de la connexion pour éviter les requêtes SQL inutiles
    if (!isset($_SESSION['user_range'])) return false;
    return $_SESSION['user_range'] >= $distanceActivite;
}

/**
 * RÈGLE DU CRÉDIT UNIQUE (ANTI-TRICHE)
 * Vérifie si l'élève est encore éligible au gain de points pour cette activité.
 */
function estEligibleAuxPoints($userId, $activityId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT score_max FROM history WHERE user_id = ? AND activity_id = ?");
    $stmt->execute([$userId, $activityId]);
    $historique = $stmt->fetch();

    // Éligible si : pas d'historique OU score précédent strictement inférieur à 100%
    return (!$historique || $historique['score_max'] < 100);
}

/**
 * SÉCURISATION DES ENTRÉES
 * Helper pour nettoyer les données affichées (Protection XSS)
 */
function s($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}