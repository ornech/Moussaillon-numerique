<?php
require_once 'auth_check.php';

// 1. Récupération et validation de l'ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    exit("Identifiant invalide.");
}

// 2. Requête préparée
$stmt = $pdo->prepare("SELECT * FROM activities WHERE id = ?");
$stmt->execute([$id]);
$activity = $stmt->fetch();

// 3. Vérification que l'activité existe réellement en base
if (!$activity) {
    exit("Activité introuvable dans la base de données.");
}

// 4. Décodage du JSON avec gestion d'erreur intégrée
$quiz = json_decode($activity['quiz_json'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Aperçu : <?= s($activity['title']) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body { background: var(--bg-app); padding: 50px 20px; display: block !important; }
        .preview-box { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 20px; box-shadow: var(--shadow-relief); }
        .q-preview { border-left: 5px solid var(--primary); padding-left: 20px; margin: 30px 0; }
        .opt-btn { display: block; width: 100%; padding: 15px; margin: 10px 0; border: 2px solid #eee; border-radius: 10px; text-align: left; background: #fff; cursor: pointer; }
        .opt-btn.correct { border-color: #10b981; background: #ecfdf5; }
    </style>
</head>
<body>
    <div class="preview-box">
        <span class="matiere-tag">MODE APERÇU OFFICIER</span>
        <h1><?= s($activity['title']) ?></h1>
        <hr>
        
        <div class="content">
            <?= $activity['content_html'] ?>
        </div>

        <h2 style="margin-top:50px;">🎯 Rendu du Quiz</h2>
        
        <?php if (empty($quiz)): ?>
            <p>Aucun quiz disponible pour cette activité.</p>
        <?php else: ?>
            <?php foreach ($quiz as $i => $q): ?>
                <div class="q-preview">
                    <h3>Question <?= $i + 1 ?> : <?= s($q['question'] ?? 'Sans titre') ?></h3>
                    
                    <?php if (!empty($q['options'])): ?>
                        <?php foreach ($q['options'] as $opt): ?>
                            <?php 
                                // On définit si c'est la bonne réponse une seule fois
                                $isCorrect = ($opt === ($q['answer'] ?? null)); 
                            ?>
                            <div class="opt-btn <?= $isCorrect ? 'correct' : '' ?>">
                                <?= s($opt) ?> 
                                <?= $isCorrect ? ' <strong>(Réponse correcte)</strong>' : '' ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <button onclick="window.close()" class="btn-choice" style="width:100%; margin-top:30px;">FERMER L'APERÇU</button>
    </div>
</body>
</html>