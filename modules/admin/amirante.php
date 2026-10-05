<?php
session_start();
require_once '../../includes/config.php';

use Jf\Moussaillons\Infrastructure\Security\Csrf;

// 1. VERROU DE SÉCURITÉ : Seul le rôle 'admin' peut entrer ici
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Si on n'est pas admin, retour à la case départ
    header("Location: ../../index.php");
    exit;
}

// 2. STATISTIQUES ET REGISTRE DES ADULTES
try {
    $stats_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats_staff = $pdo->query("SELECT COUNT(*) FROM staff WHERE role = 'teacher'")->fetchColumn();
    $stats_crews = $pdo->query("SELECT COUNT(*) FROM crews")->fetchColumn();

    $adultes = $pdo->query("
        SELECT s.id, s.username, s.role, s.profil, s.email, s.created_at,
               COUNT(DISTINCT c.id) AS nb_equipages, COUNT(u.id) AS nb_moussaillons
        FROM staff s
        LEFT JOIN crews c ON c.owner_id = s.id
        LEFT JOIN users u ON u.crew_id = c.id
        GROUP BY s.id
        ORDER BY s.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Erreur de lecture des données.");
}

$libelleProfil = fn (array $a) => $a['role'] === 'admin' ? '🔒 Amirauté' : ($a['profil'] === 'parent' ? '👨‍👩‍👧 Parent' : '🧑‍🏫 Enseignant');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= Csrf::metaTag() ?>
    <title>Amirauté - Contrôle Global</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        html, body { height: auto !important; overflow-y: auto !important; }
        body { background: #f0f4f8; font-family: sans-serif; margin: 0; }
        .amiraute { max-width: 1000px; margin: 0 auto; padding: 30px 16px 60px; display: flex; flex-direction: column; gap: 25px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .topbar h1 { margin: 0; font-size: 1.6rem; }
        .topbar nav { display: flex; gap: 8px; flex-wrap: wrap; }
        .topbar a { padding: 10px 14px; border-radius: 10px; color: white; text-decoration: none; font-weight: bold; background: var(--secondary); }
        .panel { background: white; border-radius: 20px; padding: 25px; box-shadow: var(--shadow-relief); text-align: left; }
        .panel h2 { margin-top: 0; font-size: 1.2rem; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; }
        .stat { background: white; border-radius: 15px; padding: 18px; box-shadow: var(--shadow-relief); }
        .stat strong { display: block; font-size: 2rem; color: var(--primary); }
        .btn { padding: 14px 20px; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; background: #10b981; color: white; font-size: 1rem; }
        .lien-invitation { display: flex; gap: 8px; margin-top: 15px; flex-wrap: wrap; }
        .lien-invitation input { flex: 1; min-width: 0; padding: 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.9rem; }
        .muted { color: #64748b; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        th, td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; text-align: left; }
        th { color: #64748b; font-size: 0.85rem; }
        .table-scroll { overflow-x: auto; }
    </style>
</head>
<body>
    <div class="amiraute">
        <div class="topbar">
            <h1>⚓ Amirauté</h1>
            <nav>
                <a href="../enseignant/dashboard.php">Équipages &amp; moussaillons</a>
                <a href="../enseignant/manage_activities.php">Activités</a>
                <a href="../../aide.php" style="background:#64748b;">❓ Aide</a>
                <a href="../../api/logout.php" style="background:var(--danger);">Déconnexion</a>
            </nav>
        </div>

        <div class="stats">
            <div class="stat"><strong><?= (int)$stats_users ?></strong>Moussaillons à bord</div>
            <div class="stat"><strong><?= (int)$stats_staff ?></strong>Parents &amp; enseignants</div>
            <div class="stat"><strong><?= (int)$stats_crews ?></strong>Équipages</div>
        </div>

        <section class="panel">
            <h2>✉️ Inviter un parent ou un enseignant</h2>
            <p class="muted">
                Générez un lien et envoyez-le (mail, SMS…). La personne crée elle-même son compte et son premier équipage.
                Chaque lien ne sert qu'une fois.
            </p>
            <button type="button" class="btn" id="btn-inviter" onclick="inviter()">Générer un lien d'invitation</button>
            <div id="resultat-invitation" hidden>
                <div class="lien-invitation">
                    <input type="text" id="lien" readonly onclick="this.select()">
                    <button type="button" class="btn" onclick="copier(this)">📋 Copier</button>
                </div>
                <p class="muted" id="validite"></p>
            </div>
        </section>

        <section class="panel">
            <h2>👥 Parents &amp; enseignants</h2>
            <div class="table-scroll">
                <table>
                    <tr><th>Identifiant</th><th>Profil</th><th>Équipages</th><th>Moussaillons</th><th>Inscrit le</th></tr>
                    <?php foreach ($adultes as $a): ?>
                        <tr>
                            <td><strong><?= s($a['username']) ?></strong><?php if ($a['email']): ?><br><small class="muted"><?= s($a['email']) ?></small><?php endif; ?></td>
                            <td><?= $libelleProfil($a) ?></td>
                            <td><?= (int)$a['nb_equipages'] ?></td>
                            <td><?= (int)$a['nb_moussaillons'] ?></td>
                            <td class="muted"><?= $a['created_at'] ? date('d/m/Y', strtotime($a['created_at'])) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </section>
    </div>

    <script>
        async function inviter() {
            const btn = document.getElementById('btn-inviter');
            btn.disabled = true;
            try {
                const response = await fetch('../../api/index.php/invitations', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Erreur de liaison');
                document.getElementById('lien').value = result.lien;
                document.getElementById('validite').textContent = `Valable ${result.validite_jours} jours, pour une seule personne. Besoin d'inviter quelqu'un d'autre ? Générez un nouveau lien.`;
                document.getElementById('resultat-invitation').hidden = false;
                btn.textContent = 'Générer un autre lien';
            } catch (err) {
                alert(err.message);
            } finally {
                btn.disabled = false;
            }
        }

        async function copier(btn) {
            const champ = document.getElementById('lien');
            try {
                await navigator.clipboard.writeText(champ.value);
            } catch {
                champ.select();
                document.execCommand('copy');
            }
            btn.textContent = '✅ Copié !';
            setTimeout(() => btn.textContent = '📋 Copier', 2000);
        }
    </script>
</body>
</html>
