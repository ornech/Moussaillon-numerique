<?php
/**
 * CORE CONFIG - LES MOUSSAILLONS NUMÉRIQUES
 * Bootstrap PSR-4 + connexion Database + session + wrappers. Configuration via Config.
 */
require_once __DIR__ . '/../bootstrap.php';

// Connexion PDO (Singleton, utilise Jf\Moussaillons\Infrastructure\Config)
try {
    $pdo = \Jf\Moussaillons\Infrastructure\Database::getInstance()->getConnection();
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Erreur critique : Impossible de joindre le port de plaisance.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Middleware de maintenance (utilise Config)
if (\Jf\Moussaillons\Infrastructure\Config::maintenanceMode()
    && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    die("🔧 Le port est actuellement en travaux. Revenez plus tard, matelots !");
}

// Wrappers rétrocompatibles (délèguent aux classes PSR-4)
function verifierPortee($distanceActivite): bool
{
    return \Jf\Moussaillons\Application\Session\PorteeChecker::verifier((int) $distanceActivite);
}

function estEligibleAuxPoints($userId, $activityId): bool
{
    $pdo = \Jf\Moussaillons\Infrastructure\Database::getInstance()->getConnection();
    $service = new \Jf\Moussaillons\Application\Progression\EligibilitePoints($pdo);
    return $service->estEligible((int) $userId, (int) $activityId);
}

function s($data): string
{
    return \Jf\Moussaillons\Infrastructure\Security\Xss::escape($data === null ? '' : (string) $data);
}
