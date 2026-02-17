<?php
session_start();
require_once __DIR__ . '/config.php';

// 1. Vérification de l'accès
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'eleve') {
    header("Location: /index.php");
    exit;
}

// 2. Chargement automatique des données de l'élève
// On prépare la variable $user qui sera disponible partout
$stmt = $pdo->prepare("
    SELECT u.*, u.points, s.name as ship_name, s.img_url, s.size 
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