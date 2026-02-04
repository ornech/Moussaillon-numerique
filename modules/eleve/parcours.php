<?php
// modules/eleve/parcours.php
require_once '../../includes/check_session.php'; 

// On récupère les matières disponibles. 
// La notion de distance est conservée uniquement pour l'affichage si besoin, 
// mais elle ne bloque plus l'accès.
$stmt = $pdo->prepare("SELECT MIN(id) as id, matiere, MIN(distance) as distance 
    FROM activities 
    WHERE is_validated = 1 
    GROUP BY matiere 
    ORDER BY matiere ASC"); // Tri par nom de matière pour plus de clarté
$stmt->execute();
$activities = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Univers d'Apprentissage - Moussaillons</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="layout-grid">

 <?php include './nav.php'; ?>

    <div class="universe-container">
        <?php 
        $card_index = 1;
        foreach ($activities as $act): 
            // LE VERROU EST SUPPRIMÉ ICI
            $image_name = "carte" . $card_index . ".png";
            $card_index = ($card_index >= 6) ? 1 : $card_index + 1;
        ?>
            <a href="themes.php?matiere=<?php echo urlencode($act['matiere']); ?>" 
               class="card-link">
                
                <div class="universe-card">
                    <img src="../../assets/img/ui/<?php echo $image_name; ?>" class="card-img" alt="Carte Univers">
                    
                    <div class="card-label">
                        <span class="matiere-tag">Univers</span><br>
                        <strong style="font-size: 1.2rem;"><?php echo htmlspecialchars($act['matiere']); ?></strong><br>
                        <small>Exploration libre</small>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</body>
</html>