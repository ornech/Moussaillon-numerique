<?php
require_once 'auth_check.php'; 

try {
    // 1. Liste des élèves avec réussite
    $stmtEleves = $pdo->query("
        SELECT u.id, u.username, 
               COUNT(h.activity_id) as nb_total,
               ROUND(AVG(h.score_max), 0) as score_moyen,
               MAX(h.date_completion) as derniere_activite
        FROM users u
        LEFT JOIN history h ON u.id = h.user_id
        GROUP BY u.id
        ORDER BY derniere_activite DESC
    ");
    $eleves = $stmtEleves->fetchAll();

    // 2. Statistiques par Matière (Tes Docks)
    $stmtMatiere = $pdo->query("
        SELECT matiere, 
               COUNT(DISTINCT theme) as nb_themes, 
               COUNT(id) as nb_islands 
        FROM activities 
        GROUP BY matiere
    ");
    $docks = $stmtMatiere->fetchAll();

    // 3. Indicateur global pour la Ligne 2
    $total_reussite = $pdo->query("SELECT ROUND(AVG(score_max), 1) FROM history")->fetchColumn();
    $activite_24h = $pdo->query("SELECT COUNT(*) FROM history WHERE date_completion > NOW() - INTERVAL 1 DAY")->fetchColumn();

} catch (PDOException $e) { die("Erreur : " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Quartier des Officiers</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
<style>
    /* Reset du layout pour le dashboard enseignant */

    html, body {
        height: auto !important;
        overflow-y: auto !important; /* Force l'apparition du scroll si nécessaire */
        overflow-x: hidden !important; /* Empêche le décalage horizontal */
    }

    body.layout-grid { 
        display: block !important; /* On garde le flux vertical standard */
        padding-top: 100px; 
        background: #f0f4f8;
        margin: 0;
    }

    .dashboard-wrapper { 
        width: 95%; 
        max-width: 1300px; 
        margin: 0 auto; 
        display: flex; 
        flex-direction: column; 
        gap: 30px; 
        padding-bottom: 80px; /* Marge en bas pour ne pas coller au bord */
        height: auto; /* Laisse le contenu définir la hauteur */
    body.layout-grid { 
        display: block !important; 
        padding-top: 100px; 
        background: #f0f4f8; 
    }

    .dashboard-wrapper { 
        width: 95%; 
        max-width: 1300px; 
        margin: 0 auto; 
        display: flex; 
        flex-direction: column; 
        gap: 30px; 
        padding-bottom: 50px;
    }
    
    /* Ligne 1 & 2 : Grilles de cartes */
    .kpi-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
        gap: 20px; 
    }
    
    /* Le lien devient le conteneur principal de la carte */
    .card-kpi-link {
        text-decoration: none;
        color: inherit;
        display: block; /* Important pour que le lien occupe toute la place */
        transition: transform 0.2s ease;
    }

    .card-kpi { 
        background: white; 
        padding: 25px; 
        border-radius: 20px; 
        box-shadow: var(--shadow-relief); 
        border-top: 5px solid var(--secondary);
        height: 100%; /* S'aligne sur la hauteur de la grille */
        display: flex; 
        flex-direction: column; 
        justify-content: center;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    /* Effets de survol */
    .card-kpi-link:hover {
        transform: translateY(-5px);
    }

    .card-kpi-link:hover .card-kpi {
        border-top-color: var(--primary); 
        box-shadow: 0 12px 24px rgba(0,0,0,0.1);
        background-color: #ffffff;
    }

    .card-kpi h3 { margin: 0; color: var(--bois); font-family: 'Fredoka', sans-serif; font-size: 1.4rem; }
    .card-kpi .sub { font-size: 0.9rem; color: #64748b; margin-top: 10px; line-height: 1.4; }
    
    /* Ligne 3 : Registre des élèves */
    .student-list { background: white; border-radius: 25px; padding: 30px; box-shadow: var(--shadow-relief); text-align: left; }
    
    .table-header { 
        display: grid; 
        grid-template-columns: 2fr 1fr 1fr 1fr; 
        padding: 0 20px 10px; 
        font-weight: bold; 
        color: #64748b; 
        font-size: 0.9rem; 
        border-bottom: 1px solid #eee;
        margin-bottom: 15px;
    }
    
    .student-item { 
        display: grid; 
        grid-template-columns: 2fr 1fr 1fr 1fr; 
        align-items: center;
        padding: 15px 20px; 
        background: #f8fafc; 
        border-radius: 15px; 
        margin-bottom: 10px;
        text-decoration: none; 
        color: inherit; 
        transition: 0.2s; 
        border: 2px solid transparent;
    }

    .student-item:hover { 
        border-color: var(--primary); 
        transform: translateX(10px); 
        background: white; 
        box-shadow: var(--shadow-relief);
    }

    .stats-badge {
        background: var(--primary);
        color: white;
        padding: 5px 12px;
        border-radius: 10px;
        font-weight: bold;
        display: inline-block;
    }
</style>
</head>
<body class="layout-grid">

    <div class="status-bar" style="position:fixed; top:0; width:100%; z-index:1000; box-sizing:border-box;">
        <div class="ship-info">
            <img src="../../assets/img/ui/logo.png" style="height:40px;">
            <h2>Tableau de Bord enseignant</h2>
        </div>
        <div class="nav-links">
            <a href="manage_activities.php" class="btn-port" style="background:var(--secondary);">Gestion des activités</a>
            <a href="logout.php" class="btn-port" style="background:var(--danger);">Déconnexion</a>
        </div>
    </div>

    <main class="dashboard-wrapper">
        
        <section>
            <h2 style="margin-bottom:15px;">📦 Matières</h2>
            <div class="kpi-grid">
                <?php foreach ($docks as $d): ?>
                    <a href="manage_activities.php?matiere=<?= urlencode($d['matiere']) ?>" class="card-kpi-link">
                        <div class="card-kpi">
                            <h3><?= s($d['matiere']) ?></h3>
                            <div class="sub">
                                ⚓ <strong><?= $d['nb_themes'] ?></strong> Thèmes<br>
                                🏝️ <strong><?= $d['nb_islands'] ?></strong> Activités
                            </div>
                            <div style="margin-top: 15px; text-align: right; font-size: 0.8rem; color: var(--secondary); font-weight: bold;">
                                Ouvrir ➜
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section>
            <h2 style="margin-bottom:15px;">📊 Performance Globale</h2>
            <div class="kpi-grid">
                <div class="card-kpi" style="border-top-color: var(--primary);">
                    <h3 style="color:var(--primary);">🎯 <?= $total_reussite ?> %</h3>
                    <div class="sub">Taux de réussite moyen de la classe</div>
                </div>
                <div class="card-kpi" style="border-top-color: #10b981;">
                    <h3 style="color:#10b981;">🚢 <?= $activite_24h ?></h3>
                    <div class="sub">Activités terminées ces dernières 24h</div>
                </div>
                <div class="card-kpi" style="border-top-color: var(--bois);">
                    <h3>🗂️ <?= count($eleves) ?></h3>
                    <div class="sub">Nombre d'élèves inscrits</div>
                </div>
            </div>
        </section>

        <section class="student-list">
            <h2 style="margin-top:0;">📝 Liste élève</h2>
            <div class="table-header">
                <span>Elèves</span>
                <span>Réussite</span>
                <span>Progression</span>
                <span>Dernière activité</span>
            </div>
            
            <?php foreach ($eleves as $e): ?>
                <a href="student_details.php?id=<?= $e['id'] ?>" class="student-item">
                    <div style="display:flex; align-items:center; gap:15px;">
                        <span style="font-size:1.2rem;">👤</span>
                        <strong><?= s($e['username']) ?></strong>
                    </div>
                    
                    <div class="stats-badge" style="width:fit-content;"><?= $e['score_moyen'] ?? 0 ?>%</div>
                    
                    <div>
                        <small><?= $e['nb_total'] ?> Activités validées</small>
                        <div class="progress-mini" style="margin: 5px 0 0 0; width:80%;">
                            <div class="progress-fill" style="width: <?= min($e['nb_total']*5, 100) ?>%"></div>
                        </div>
                    </div>

                    <div style="font-size:0.85rem; color:#64748b;">
                        <?= $e['derniere_activite'] ? date('d/m H:i', strtotime($e['derniere_activite'])) : 'Aucune' ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </section>

    </main>
</body>
</html>