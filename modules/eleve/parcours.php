<?php
// modules/eleve/parcours.php
// INCLUSION DU FICHIER DE CONFIGURATION (BDD + SESSIONS)
require_once '../../includes/check_session.php'; 


// REQUÊTE SPÉCIFIQUE À LA PAGE : Récupération des activités validées, groupées par matière
$stmt = $pdo->prepare("SELECT MIN(id) as id, matiere, MIN(distance) as distance 
    FROM activities 
    WHERE is_validated = 1 
    GROUP BY matiere 
    ORDER BY distance ASC");
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
            $is_locked = ($user['range_level'] < $act['distance']);
            
            // On boucle sur les images de 1 à 6
            $image_name = "carte" . $card_index . ".png";
            $card_index = ($card_index >= 6) ? 1 : $card_index + 1;
        ?>
            <a href="<?php echo $is_locked ? '#' : 'themes.php?matiere=' . urlencode($act['matiere']); ?>" 
            class="card-link <?php echo $is_locked ? 'locked' : ''; ?>"
            onclick="if(<?php echo $is_locked ? 'true' : 'false'; ?>) { alert('Votre navire est trop faible !'); return false; } else { return lancerTransition(event, this); }">
                
                <?php if($is_locked): ?>
                    <div class="lock-badge">🔒</div>
                <?php endif; ?>

                <div class="universe-card">
                    <img src="../../assets/img/ui/<?php echo $image_name; ?>" class="card-img" alt="Carte Univers">
                    
                    <div class="card-label">
                        <span class="matiere-tag">Univers</span><br>
                        <strong style="font-size: 1.2rem;"><?php echo htmlspecialchars($act['matiere']); ?></strong><br>
                        <small>Accessible à : <?php echo $act['distance']; ?> milles</small>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?PHP include './transition.php'; ?>
</body>
</html>