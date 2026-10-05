<?php
// Inclusion de la config (qui lance la session et PDO)
require_once '../../includes/config.php';

// Vérification de sécurité : adultes (parents, enseignants) et amirauté
if (!isset($_SESSION['role'], $_SESSION['user_id']) || !in_array($_SESSION['role'], ['teacher', 'admin'], true)) {
    header("Location: ../../index.php");
    exit;
}

$staffId  = (int) $_SESSION['user_id'];
$estAdmin = $_SESSION['role'] === 'admin';
