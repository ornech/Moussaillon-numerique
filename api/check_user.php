<?php
/**
 * API DE SONDE - LES MOUSSAILLONS NUMÉRIQUES
 * Vérifie l'existence d'un pseudonyme pour orienter l'interface de login.
 */

require_once '../includes/config.php';

// Définition du type de réponse en JSON
header('Content-Type: application/json');

// Récupération et nettoyage du paramètre username
$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if (empty($username)) {
    echo json_encode(['error' => 'Pseudo manquant']);
    exit;
}

try {
    // Requête préparée pour vérifier l'existence dans la table users
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Réponse structurée pour le fetch JavaScript de index.php
    echo json_encode([
        'exists' => (bool)$user,
        'username' => s($username) // Retourne le pseudo nettoyé pour confirmation
    ]);

} catch (PDOException $e) {
    // Erreur silencieuse côté client, loggée côté serveur
    error_log("Erreur API CheckUser : " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne de la capitainerie']);
}