<?php
require_once 'auth_check.php';

if (!function_exists('s')) {
    function s($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$filter_matiere = filter_input(INPUT_GET, 'matiere', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'Toutes';

try {
    $stmtM = $pdo->query("SELECT DISTINCT matiere FROM activities WHERE matiere IS NOT NULL ORDER BY matiere ASC");
    $matieres = $stmtM->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT * FROM activities";
    $params = [];
    if ($filter_matiere !== 'Toutes') {
        $sql .= " WHERE matiere = ?";
        $params[] = $filter_matiere;
    }
    $sql .= " ORDER BY theme ASC, title ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $raw_activities = $stmt->fetchAll();

    $grouped_activities = [];
    foreach ($raw_activities as $act) {
        $t = !empty($act['theme']) ? $act['theme'] : 'Général';
        $grouped_activities[$t][] = $act;
    }
} catch (PDOException $e) { die("Erreur : " . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Arsenal - Gestion des Îles</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
<style>
    /* 1. RESET & FONDATION */
    body.layout-grid { 
        display: block !important; 
        height: auto !important; 
        overflow-y: auto !important; 
        padding-top: 100px; 
        background: #f0f4f8 !important; /* Couleur de fond plus douce */
        /* On impose une police propre partout */
        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important;
    }

    .admin-container { 
        width: 95%; 
        max-width: 1000px; 
        margin: 0 auto; 
        padding-bottom: 80px; 
    }

    /* 2. TYPOGRAPHIE (Écrase BelleAllure si nécessaire) */
    h1, h2, h3, .theme-label, .island-name {
        font-family: 'Fredoka', 'Segoe UI', sans-serif !important;
        letter-spacing: 0.5px;
        margin: 0;
    }

    /* 3. EN-TÊTE DE PAGE (SECTION DOCK) */
    .filter-header {
        background: white;
        padding: 25px 35px;
        border-radius: 20px;
        box-shadow: var(--shadow-relief);
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        border: 2px solid #e2e8f0;
    }

    .matiere-select {
        padding: 10px 15px;
        border-radius: 12px;
        border: 2px solid var(--secondary);
        font-weight: bold;
        background: #fff;
        cursor: pointer;
        font-family: inherit;
    }

    /* 4. CARTES DE THÈMES (ACCORDÉONS) */
    .theme-card {
        background: white;
        margin-bottom: 12px;
        border-radius: 15px;
        box-shadow: var(--shadow-relief);
        border: 2px solid transparent;
        transition: all 0.3s ease;
        overflow: hidden;
    }

    /* Changement d'aspect quand le tiroir est ouvert */
    .theme-card[open] {
        border-color: var(--secondary);
        margin-bottom: 25px;
    }

    .theme-summary {
        padding: 15px 25px;
        list-style: none;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        outline: none;
        background: #fff;
    }

    .theme-summary::-webkit-details-marker { display: none; }

    .theme-label {
        font-size: 1.3rem;
        color: var(--bois);
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* 5. LISTE DES ÎLES (À L'INTÉRIEUR DU TIROIR) */
    .island-list {
        padding: 0 25px 20px 25px; /* Pas de padding-top pour coller au titre */
        background: #fff;
        border-top: 1px solid #f1f5f9;
        text-align: left;
    }

    .island-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .island-item:last-child { border-bottom: none; }

    .island-name {
        font-size: 1.15rem;
        color: var(--text);
        font-weight: 600;
        margin-bottom: 2px;
    }

    .island-meta {
        font-size: 0.85rem;
        color: #64748b;
        display: flex;
        gap: 15px;
        align-items: center;
    }

    .q-count {
        color: var(--primary);
        font-weight: bold;
        background: #f0fdf4;
        padding: 2px 8px;
        border-radius: 6px;
        border: 1px solid #dcfce7;
    }

    /* 6. BOUTON MODIFIER (Style Relief) */
    .btn-edit {
        background: var(--secondary) !important;
        color: white !important;
        padding: 10px 20px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: bold;
        text-decoration: none;
        box-shadow: 0 4px 0 #b45309; /* Effet 3D */
        transition: transform 0.1s, box-shadow 0.1s;
        display: inline-block;
    }

    .btn-edit:active {
        transform: translateY(2px);
        box-shadow: 0 2px 0 #b45309;
    }

    /* 7. INDICATEUR D'OUVERTURE (CHEVRON) */
    .theme-summary::after {
        content: '▼';
        font-size: 0.8rem;
        color: var(--secondary);
        transition: transform 0.3s;
    }

    .theme-card[open] .theme-summary::after {
        transform: rotate(180deg);
    }
</style>
</head>
<body class="layout-grid">

    <div class="status-bar" style="position: fixed; top:0; width:100%; z-index:1000; box-sizing: border-box;">
        <div class="ship-info">
            <a href="dashboard.php" style="text-decoration:none; font-size: 1.5rem; margin-right: 15px;">⬅️</a>
            <h2 style="margin:0;">Arsenal de l'Officier</h2>
        </div>
    </div>

    <main class="admin-container">
        
        <div class="filter-header">
            <div style="text-align: left;">
                <h3 style="margin:0; color:var(--bois); font-size: 1.5rem;">⚓ Dock : <?= s($filter_matiere) ?></h3>
                <p style="margin:5px 0 0 0; color: #718096; font-size: 0.9rem;">Sélectionnez une cargaison à vérifier</p>
            </div>
            <form method="GET">
                <select name="matiere" onchange="this.form.submit()" class="matiere-select">
                    <option value="Toutes">Toutes les matières</option>
                    <?php foreach($matieres as $m): ?>
                        <option value="<?= s($m) ?>" <?= $filter_matiere == $m ? 'selected' : '' ?>><?= s($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php foreach ($grouped_activities as $theme_name => $acts): ?>
            <details class="theme-card" <?= count($grouped_activities) === 1 ? 'open' : '' ?>>
                <summary class="theme-summary">
                    <div class="theme-label">
                        <span style="font-size: 1.2rem;">📂</span> <?= s($theme_name) ?>
                    </div>
                    <span class="matiere-tag" style="background: #edf2f7; color: #4a5568; border:none; margin:0; font-weight: 700;">
                        <?= count($acts) ?> <?= count($acts) > 1 ? 'îles' : 'île' ?>
                    </span>
                </summary>
                
                <div class="island-list">
                    <?php foreach ($acts as $a): ?>
                        <div class="island-item">
                            <div>
                                <div class="island-name"><?= s($a['title']) ?></div>
                                <div class="island-meta">
                                    <span>📍 Distance : <strong><?= s($a['distance'] ?? '0') ?></strong> m.</span>
                                    <span>📝 <span class="q-count"><?= isset($a['nbr_question']) ? $a['nbr_question'] : '?' ?></span> Questions</span>
                                </div>
                            </div>
                            <div>
                                <a href="edit_activity.php?id=<?= $a['id'] ?>" class="btn-edit">
                                    MODIFIER
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>

        <?php if(empty($grouped_activities)): ?>
            <div class="card" style="text-align: center; padding: 50px;">
                <p style="font-size: 1.2rem; color: #718096;">Aucune île n'a été trouvée dans ce dock.</p>
                <a href="manage_activities.php" class="btn-choice" style="display: inline-block; margin-top: 20px;">Voir tout l'arsenal</a>
            </div>
        <?php endif; ?>

    </main>

</body>
</html>