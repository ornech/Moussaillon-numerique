<?php
require_once '../../includes/check_session.php'; 

$matiere = $_GET['matiere'] ?? '';
$theme_selectionne = $_GET['theme'] ?? '';

if ($theme_selectionne) {
    // ÉTAPE 2 : Si un thème est sélectionné, on récupère les exercices
    $stmt = $pdo->prepare("SELECT id, title, theme, distance FROM activities WHERE theme = ? AND is_validated = 1 ORDER BY id ASC");
    $stmt->execute([$theme_selectionne]);
    $items = $stmt->fetchAll();
    $affichage = 'exercices';
} else {
    // ÉTAPE 1 : Sinon, on récupère les thèmes de la matière
    $stmt = $pdo->prepare("SELECT theme, MIN(id) as first_id, MIN(distance) as distance FROM activities WHERE matiere = ? AND is_validated = 1 GROUP BY theme ORDER BY distance ASC");
    $stmt->execute([$matiere]);
    $items = $stmt->fetchAll();
    $affichage = 'themes';
}
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
    <?php if (empty($items)): ?>
        <p>Aucune mission trouvée ici, moussaillon !</p>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <?php 
                // Si on affiche les thèmes, le lien renvoie vers soi-même avec ?theme=
                // Si on affiche les exercices, le lien renvoie vers exercices.php?id=
                $lien = ($affichage === 'themes') 
                    ? "themes.php?theme=" . urlencode($item['theme']) 
                    : "exercices.php?id=" . $item['id'];
                
                $titre = ($affichage === 'themes') ? $item['theme'] : $item['title'];
            ?>
            <a href="<?php echo $lien; ?>" class="card-link">
                <div class="theme-card">
                    <img src="" class="card-img" alt="">
                    <div class="card-label">
                        <span class="matiere-tag"><?php echo htmlspecialchars($affichage === 'themes' ? $matiere : $item['theme']); ?></span><br>
                        <strong class="card-titre"><?php echo htmlspecialchars($titre); ?></strong>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>