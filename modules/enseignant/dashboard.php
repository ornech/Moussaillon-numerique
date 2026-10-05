<?php
require_once 'auth_check.php';

use Jf\Moussaillons\Application\Equipage\EquipageService;
use Jf\Moussaillons\Infrastructure\Config;
use Jf\Moussaillons\Infrastructure\Security\Csrf;

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

try {
    // 0. Équipages de l'adulte (tous pour l'amirauté) et vocabulaire selon le profil
    $equipages = (new EquipageService($pdo))->listerPour($staffId, $estAdmin);

    $stmtProfil = $pdo->prepare("SELECT profil FROM staff WHERE id = ?");
    $stmtProfil->execute([$staffId]);
    $profil = $stmtProfil->fetchColumn() ?: 'enseignant';
    $motEnfants = $estAdmin ? 'moussaillons' : ($profil === 'parent' ? 'enfants' : 'élèves');
    $titre = $estAdmin ? "Amirauté — Équipages" : ($profil === 'parent' ? "Espace parent" : "Espace enseignant");

    // Périmètre : un adulte ne voit que les moussaillons de ses équipages
    $perimetre = $estAdmin ? "1 = 1" : "u.crew_id IN (SELECT id FROM crews WHERE owner_id = :staff)";
    $params = $estAdmin ? [] : ['staff' => $staffId];

    // 1. Liste des élèves avec réussite (en % de bonnes réponses)
    $stmtEleves = $pdo->prepare("
        SELECT u.id, u.username, c.name AS crew_name,
               COUNT(h.activity_id) as nb_total,
               ROUND(AVG(h.score_max * 100 / NULLIF(h.nbr_question, 0)), 0) as score_moyen,
               MAX(h.date_completion) as derniere_activite
        FROM users u
        LEFT JOIN crews c ON c.id = u.crew_id
        LEFT JOIN history h ON u.id = h.user_id
        WHERE $perimetre
        GROUP BY u.id
        ORDER BY derniere_activite DESC
    ");
    $stmtEleves->execute($params);
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

    // 3. Indicateurs globaux (limités au périmètre de l'adulte)
    $stmtReussite = $pdo->prepare("
        SELECT ROUND(AVG(h.score_max * 100 / NULLIF(h.nbr_question, 0)), 1)
        FROM history h JOIN users u ON u.id = h.user_id WHERE $perimetre
    ");
    $stmtReussite->execute($params);
    $total_reussite = $stmtReussite->fetchColumn() ?? 0;

    $stmt24h = $pdo->prepare("
        SELECT COUNT(*) FROM history h JOIN users u ON u.id = h.user_id
        WHERE h.date_completion > NOW() - INTERVAL 1 DAY AND $perimetre
    ");
    $stmt24h->execute($params);
    $activite_24h = $stmt24h->fetchColumn();

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Erreur de lecture des données.");
}

$lienRejoindre = fn (string $code) => Config::appUrl() . '/index.php?equipage=' . $code;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= Csrf::metaTag() ?>
    <title><?= s($titre) ?> - Les Moussaillons</title>
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
        padding: 0 70px 10px 20px;
        font-weight: bold;
        color: #64748b;
        font-size: 0.9rem;
        border-bottom: 1px solid #eee;
        margin-bottom: 15px;
    }

    .student-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }

    .student-item {
        flex: 1;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        align-items: center;
        padding: 15px 20px;
        background: #f8fafc;
        border-radius: 15px;
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

    /* Équipages */
    .crew-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
    .crew-card { background: white; padding: 22px; border-radius: 20px; box-shadow: var(--shadow-relief); border-top: 5px solid #10b981; text-align: left; }
    .crew-card h3 { margin: 0 0 4px; font-family: 'Fredoka', sans-serif; color: var(--bois); }
    .crew-code { font-family: monospace; font-size: 1.6rem; font-weight: bold; letter-spacing: 3px; color: #065f46; }
    .crew-card .sub { font-size: 0.85rem; color: #64748b; }
    .inline-form { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
    .inline-form input { flex: 1; min-width: 0; padding: 10px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.95rem; }
    .inline-form input.pin { flex: 0 0 90px; }
    .btn-small { padding: 10px 14px; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; background: var(--secondary); color: white; white-space: nowrap; }
    .btn-small.green { background: #10b981; }
    .btn-icon { width: 46px; height: 46px; border: none; border-radius: 12px; background: #f1f5f9; cursor: pointer; font-size: 1.2rem; flex-shrink: 0; }
    .btn-icon:hover { background: #e2e8f0; }
    .form-msg { font-size: 0.85rem; margin-top: 6px; min-height: 1em; color: #dc2626; }
    .flash { padding: 15px 20px; border-radius: 15px; font-weight: bold; }
    .flash.success { background: #ecfdf5; color: #065f46; border: 2px solid #10b981; }
    .welcome { background: #fffbeb; border: 2px solid #f59e0b; padding: 20px 25px; border-radius: 20px; text-align: left; line-height: 1.6; }
    .welcome ol { margin: 10px 0; padding-left: 20px; }

    @media (max-width: 700px) {
        .table-header { display: none; }
        .student-item { grid-template-columns: 1fr 1fr; gap: 8px; }
        .nav-links .btn-port { padding: 8px 10px; font-size: 0.85rem; }
        .status-bar h2 { font-size: 1rem; }
    }
</style>
</head>
<body class="layout-grid">

    <div class="status-bar" style="position:fixed; top:0; width:100%; z-index:1000; box-sizing:border-box;">
        <div class="ship-info">
            <img src="../../assets/img/ui/logo.png" style="height:40px;">
            <h2><?= s($titre) ?></h2>
        </div>
        <div class="nav-links">
            <?php if ($estAdmin): ?>
                <a href="../admin/amirante.php" class="btn-port" style="background:var(--bois);">🔒 Amirauté</a>
            <?php endif; ?>
            <a href="manage_activities.php" class="btn-port" style="background:var(--secondary);">Activités</a>
            <a href="../../aide.php" class="btn-port" style="background:#64748b;">❓ Aide</a>
            <a href="../../api/logout.php" class="btn-port" style="background:var(--danger);">Déconnexion</a>
        </div>
    </div>

    <main class="dashboard-wrapper">

        <?php if ($flash): ?>
            <div class="flash <?= s($flash['type']) ?>">✅ <?= s($flash['message']) ?></div>
        <?php endif; ?>

        <?php if (count($eleves) === 0): ?>
            <div class="welcome">
                <strong>👋 Bienvenue à bord !</strong> Pour embarquer vos <?= $motEnfants ?>, deux possibilités :
                <ol>
                    <li><strong>Partagez le lien de votre équipage</strong> (bouton « Copier le lien » ci-dessous) : l'enfant l'ouvre, choisit son pseudo et son code secret, et il est à bord.</li>
                    <li><strong>Ou créez son compte vous-même</strong> avec « Ajouter » : choisissez un pseudo et un code secret de 4 chiffres, puis donnez-les-lui.</li>
                </ol>
                Ensuite, l'enfant se connecte sur la page d'accueil avec son pseudo et son code secret. Vous suivez ses progrès ici.
                <a href="../../aide.php">Tout comprendre en 2 minutes →</a>
            </div>
        <?php endif; ?>

        <section id="equipages">
            <h2 style="margin-bottom:15px;">⚓ <?= $estAdmin ? 'Tous les équipages' : 'Mes équipages' ?></h2>
            <div class="crew-grid">
                <?php foreach ($equipages as $c): ?>
                    <div class="crew-card">
                        <h3><?= s($c['name']) ?></h3>
                        <div class="sub">
                            <?= (int)$c['nb_moussaillons'] ?> moussaillon<?= $c['nb_moussaillons'] > 1 ? 's' : '' ?>
                            <?php if ($estAdmin && (int)$c['owner_id'] !== $staffId): ?> · capitaine : <?= s($c['owner_name']) ?><?php endif; ?>
                        </div>
                        <div style="margin-top:12px;">
                            <span class="sub">Code d'équipage</span><br>
                            <span class="crew-code"><?= s($c['code']) ?></span>
                        </div>
                        <div class="inline-form">
                            <input type="text" readonly value="<?= s($lienRejoindre($c['code'])) ?>" onclick="this.select()" aria-label="Lien pour rejoindre l'équipage">
                            <button type="button" class="btn-small green" onclick="copierLien(this)">📋 Copier le lien</button>
                        </div>
                        <form class="inline-form" onsubmit="ajouterMoussaillon(event, <?= (int)$c['id'] ?>)">
                            <input type="text" name="pseudo" placeholder="Pseudo de l'enfant" required minlength="3" maxlength="20">
                            <input type="text" name="pin" class="pin" placeholder="Code" inputmode="numeric" pattern="\d{4}" maxlength="4" required title="4 chiffres">
                            <button type="submit" class="btn-small">+ Ajouter</button>
                        </form>
                        <div class="form-msg"></div>
                    </div>
                <?php endforeach; ?>

                <div class="crew-card" style="border-top-color: var(--secondary);">
                    <h3>➕ Nouvel équipage</h3>
                    <div class="sub">Une classe, une fratrie, un groupe d'amis… Chaque équipage a son propre code.</div>
                    <form class="inline-form" onsubmit="creerEquipage(event)">
                        <input type="text" name="nom" placeholder="Nom de l'équipage" required minlength="2" maxlength="60">
                        <button type="submit" class="btn-small">Créer</button>
                    </form>
                    <div class="form-msg"></div>
                </div>
            </div>
        </section>

        <section class="student-list">
            <h2 style="margin-top:0;">📝 Mes <?= $motEnfants ?></h2>
            <?php if (count($eleves) === 0): ?>
                <p style="color:#64748b;">Personne à bord pour l'instant. Partagez le lien de votre équipage ou ajoutez un moussaillon ci-dessus.</p>
            <?php else: ?>
            <div class="table-header">
                <span>Moussaillon</span>
                <span>Réussite</span>
                <span>Progression</span>
                <span>Dernière activité</span>
            </div>

            <?php foreach ($eleves as $e): ?>
                <div class="student-row">
                    <a href="student_details.php?id=<?= $e['id'] ?>" class="student-item">
                        <div style="display:flex; align-items:center; gap:15px;">
                            <span style="font-size:1.2rem;">👤</span>
                            <div>
                                <strong><?= s($e['username']) ?></strong>
                                <?php if (count($equipages) > 1 && $e['crew_name']): ?><br><small style="color:#64748b;"><?= s($e['crew_name']) ?></small><?php endif; ?>
                            </div>
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
                    <button type="button" class="btn-icon" title="Code secret oublié ? En donner un nouveau"
                            onclick="changerPin(<?= (int)$e['id'] ?>, <?= s(json_encode($e['username'])) ?>)">🔑</button>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section>
            <h2 style="margin-bottom:15px;">📊 Performance Globale</h2>
            <div class="kpi-grid">
                <div class="card-kpi" style="border-top-color: var(--primary);">
                    <h3 style="color:var(--primary);">🎯 <?= $total_reussite ?> %</h3>
                    <div class="sub">Taux de réussite moyen de vos <?= $motEnfants ?></div>
                </div>
                <div class="card-kpi" style="border-top-color: #10b981;">
                    <h3 style="color:#10b981;">🚢 <?= $activite_24h ?></h3>
                    <div class="sub">Activités terminées ces dernières 24h</div>
                </div>
                <div class="card-kpi" style="border-top-color: var(--bois);">
                    <h3>🗂️ <?= count($eleves) ?></h3>
                    <div class="sub">Nombre de <?= $motEnfants ?> inscrits</div>
                </div>
            </div>
        </section>

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

    </main>

    <script>
        const API = '../../api/index.php';
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

        // Appel API ; en cas de succès la page se recharge et affiche le message en haut
        async function appelApi(chemin, donnees, zoneMessage) {
            if (zoneMessage) zoneMessage.textContent = '';
            try {
                const response = await fetch(API + chemin, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                    body: JSON.stringify(donnees)
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Erreur de liaison');
                window.scrollTo(0, 0);
                window.location.reload();
            } catch (err) {
                if (zoneMessage) zoneMessage.textContent = '⚠️ ' + err.message;
                else alert(err.message);
            }
        }

        function ajouterMoussaillon(e, equipageId) {
            e.preventDefault();
            const f = e.target;
            appelApi(`/equipages/${equipageId}/moussaillons`, { pseudo: f.pseudo.value, pin: f.pin.value }, f.nextElementSibling);
        }

        function creerEquipage(e) {
            e.preventDefault();
            appelApi('/equipages', { nom: e.target.nom.value }, e.target.nextElementSibling);
        }

        function changerPin(id, pseudo) {
            const pin = prompt(`Nouveau code secret pour ${pseudo} (4 chiffres) :`);
            if (pin === null) return;
            appelApi(`/moussaillons/${id}/pin`, { pin: pin.trim() });
        }

        async function copierLien(btn) {
            const champ = btn.previousElementSibling;
            try {
                await navigator.clipboard.writeText(champ.value);
            } catch {
                champ.select();
                document.execCommand('copy');
            }
            const texte = btn.textContent;
            btn.textContent = '✅ Copié !';
            setTimeout(() => btn.textContent = texte, 2000);
        }
    </script>
</body>
</html>
