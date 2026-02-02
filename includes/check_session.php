<?php
session_start();
require_once 'config.php'; // On s'assure que la connexion DB est là

// 1. Vérification de l'accès
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'eleve') {
    header("Location: /index.php");
    exit;
}

// 2. Chargement automatique des données de l'élève
// On prépare la variable $user qui sera disponible partout
$stmt = $pdo->prepare("
    SELECT u.id, u.points, s.range_level, s.name as ship_name, s.img_url 
    FROM users u 
    JOIN ships s ON u.current_ship_id = s.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Sécurité supplémentaire : si l'utilisateur n'existe plus en DB
if (!$user) {
    session_destroy();
    header("Location: /index.php");
    exit;
}