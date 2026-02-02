<?php
session_start();
require_once '../../includes/config.php';

// 1. VERROU DE SÉCURITÉ : Seul le rôle 'admin' peut entrer ici
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Si on n'est pas admin, retour à la case départ
    header("Location: ../../index.php");
    exit;
}

// 2. RÉCUPÉRATION DES STATISTIQUES (Point 6.1 du CDC)
try {
    $stats_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats_activ = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn(); // Exemple
} catch (PDOException $e) {
    $error = "Erreur de lecture des données.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Amirauté - Contrôle Global</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="admin-container">
        <h1>Bienvenue à l'Amirauté, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Moussaillons à bord</h3>
                <p><?php echo $stats_users; ?></p>
            </div>
            <div class="stat-card">
                <h3>Officiers actifs</h3>
                <p><?php echo $stats_activ; ?></p>
            </div>
        </div>

        <a href="../../index.php" class="btn-exit">Quitter l'Amirauté</a>
    </div>
</body>
</html>