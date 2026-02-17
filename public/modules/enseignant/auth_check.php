<?php
// Inclusion de la config (qui lance la session et PDO)
require_once __DIR__ . '/../../../includes/config.php';

// Vérification de sécurité
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../../index.php");
    exit;
}
?>