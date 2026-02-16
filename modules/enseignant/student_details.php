<?php
require_once 'auth_check.php';

$student_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$student_id) { header("Location: dashboard.php"); exit; }

try {
    // 1. Infos de l'élève
    $stmtUser = $pdo->prepare("SELECT u.username, u.points, s.name as ship_name FROM users u LEFT JOIN ships s ON u.current_ship_id = s.id WHERE u.id = ?");
    $stmtUser->execute([$student_id]);
    $student = $stmtUser->fetch();

    // 2. Calcul par matière : On calcule le % de maîtrise réel (Somme points / Somme questions possibles)
    $stmtMatiere = $pdo->prepare("
        SELECT a.matiere, 
               COUNT(DISTINCT a.id) as total_activites,
               COUNT(DISTINCT h.activity_id) as faites,
               SUM(h.score_max) as total_points_obtenus,
               SUM(h.nbr_question) as total_questions_tentees
        FROM activities a
        LEFT JOIN history h ON a.id = h.activity_id AND h.user_id = ?
        GROUP BY a.matiere
    ");
    $stmtMatiere->execute([$student_id]);
    $progressionMatieres = $stmtMatiere->fetchAll();

    // 3. Historique complet
    $stmtHistory = $pdo->prepare("
        SELECT h.score_max, h.nbr_question, h.date_completion, a.title, a.matiere
        FROM history h
        JOIN activities a ON h.activity_id = a.id
        WHERE h.user_id = ?
        ORDER BY h.date_completion DESC
    ");
    $stmtHistory->execute([$student_id]);
    $history = $stmtHistory->fetchAll();

    // Préparation Radar : On calcule le pourcentage de réussite réel par matière
    $labelsRadar = [];
    $dataMaitrise = [];
    foreach($progressionMatieres as $pm) {
        $labelsRadar[] = $pm['matiere'];
        // Calcul du % de réussite : (Points / Questions) * 100
        $maitrise = ($pm['total_questions_tentees'] > 0) 
            ? round(($pm['total_points_obtenus'] / $pm['total_questions_tentees']) * 100) 
            : 0;
        $dataMaitrise[] = $maitrise;
    }

} catch (PDOException $e) { die("Erreur : " . $e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Expertise - <?= s($student['username']) ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* STABILISATION DU LAYOUT */
        body.layout-grid {
            display: block !important;
            height: auto !important;
            overflow-y: auto !important;
            background: #f4f7f6;
            margin: 0; padding: 0;
        }

        .main-content {
            width: 95%;
            max-width: 1100px;
            margin: 100px auto 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .card-stats {
            background: white; padding: 25px; border-radius: 20px;
            box-shadow: var(--shadow-relief);
        }

        .radar-fixed-box {
            height: 400px; /* Emplacement réservé pour stopper la boucle infinie */
            width: 100%;
            position: relative;
        }

        .full-width { grid-column: 1 / -1; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        
        .progress-mini { background: #eee; height: 8px; border-radius: 4px; overflow: hidden; margin-top: 5px; }
        .progress-fill { background: var(--primary); height: 100%; }
    </style>
</head>
<body class="layout-grid">

    <div class="status-bar" style="position: fixed; top:0; width:100%; z-index:1000;">
        <div class="ship-info">
            <a href="dashboard.php" style="text-decoration:none; font-size:1.5rem; margin-right: 15px;">⬅️</a>
            <h2>Analyse : <?= s($student['username']) ?></h2>
        </div>
        <div class="nav-links">
            <span class="btn-port" style="background:var(--secondary);">💰 <?= $student['points'] ?> pts</span>
        </div>
    </div>

    <div class="main-content">
        
        <section class="card-stats">
            <h3 style="color:var(--bois); margin-top:0;">🎯 Niveau de Maîtrise (%)</h3>
            <div class="radar-fixed-box">
                <canvas id="radarChart"></canvas>
            </div>
        </section>

        <section class="card-stats">
            <h3 style="color:var(--bois); margin-top:0;">🏝️ Avancement du Cursus</h3>
            <?php foreach($progressionMatieres as $pm): 
                $avancement = ($pm['total_activites'] > 0) ? ($pm['faites'] / $pm['total_activites'] * 100) : 0;
            ?>
                <div style="margin-bottom: 20px;">
                    <div style="display:flex; justify-content:space-between; font-size: 0.9rem;">
                        <strong><?= s($pm['matiere']) ?></strong>
                        <span><?= $pm['faites'] ?> / <?= $pm['total_activites'] ?> îles</span>
                    </div>
                    <div class="progress-mini">
                        <div class="progress-fill" style="width: <?= $avancement ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="card-stats full-width">
            <h3 style="color:var(--bois)">⚓ Journal des abordages (Valeurs réelles)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th>Activité</th>
                        <th>Score (Bonnes réponses)</th>
                        <th>Pourcentage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($history as $h): 
                        $pct = ($h['nbr_question'] > 0) ? round(($h['score_max'] / $h['nbr_question']) * 100) : 0;
                    ?>
                    <tr>
                        <td><span class="matiere-tag"><?= s($h['matiere']) ?></span></td>
                        <td><strong><?= s($h['title']) ?></strong></td>
                        <td><strong style="color:var(--bois);"><?= $h['score_max'] ?> / <?= $h['nbr_question'] ?></strong></td>
                        <td><span style="color: <?= $pct >= 70 ? 'var(--primary)' : 'var(--danger)' ?>"><?= $pct ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>

    <script>
        const ctx = document.getElementById('radarChart').getContext('2d');
        new Chart(ctx, {
            type: 'radar',
            data: {
                labels: <?= json_encode($labelsRadar) ?>,
                datasets: [{
                    label: 'Maîtrise',
                    data: <?= json_encode($dataMaitrise) ?>,
                    backgroundColor: 'rgba(245, 158, 11, 0.2)',
                    borderColor: '#f59e0b',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { display: false }
                    }
                },
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>