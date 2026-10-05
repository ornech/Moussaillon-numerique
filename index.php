<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'includes/config.php';

use Jf\Moussaillons\Application\Auth\LoginThrottle;
use Jf\Moussaillons\Application\Auth\SessionCreator;
use Jf\Moussaillons\Application\Equipage\EquipageService;
use Jf\Moussaillons\Application\ErreurMetier;
use Jf\Moussaillons\Infrastructure\Security\Csrf;

// Déjà connecté : on file directement à bon port
if (isset($_SESSION['role'], $_SESSION['user_id'])) {
    header('Location: ' . SessionCreator::accueil($_SESSION['role']));
    exit;
}

$message = "";
$equipages = new EquipageService($pdo);
$throttle = new LoginThrottle($pdo);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Espace affiché : "eleve" par défaut (zéro clic pour les enfants), "adulte" pour parents/enseignants/amirauté
$espace = ($_POST['espace'] ?? $_GET['espace'] ?? 'eleve') === 'adulte' ? 'adulte' : 'eleve';

// Lien d'équipage partagé par l'adulte : index.php?equipage=K7MQ4P
$codeEquipage = trim($_POST['equipage'] ?? $_GET['equipage'] ?? '');
$equipage = $codeEquipage !== '' ? $equipages->trouverParCode($codeEquipage) : null;
$afficherCode = $codeEquipage !== '';

$username = trim($_POST['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if (!Csrf::isValid($_POST['_csrf'] ?? null)) {
        $message = "La page a expiré, réessaie.";
    } elseif ($username === '' || $password === '') {
        $message = "Identifiant ou code secret manquant.";
    } elseif ($throttle->estBloque($username, $ip)) {
        $message = "Trop d'essais. Attends " . LoginThrottle::minutesAttente() . " minutes avant de réessayer.";
    } else {
        try {
            if ($espace === 'eleve') {
                $stmt = $pdo->prepare("SELECT id, pin_hash FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    if (password_verify($password, $user['pin_hash'])) {
                        $throttle->reussite($username);
                        SessionCreator::create((int)$user['id'], 'eleve', $username);
                    }
                    $throttle->echec($username, $ip);
                    $message = "Pseudo ou code secret incorrect.";
                } elseif ($equipage) {
                    // Première connexion : le moussaillon rejoint l'équipage du lien / du code
                    $id = $equipages->ajouterMoussaillon((int)$equipage['id'], $username, $password);
                    SessionCreator::create($id, 'eleve', EquipageService::validerPseudo($username));
                } elseif ($codeEquipage !== '') {
                    $throttle->echec($username, $ip);
                    $message = "Ce code d'équipage n'existe pas. Vérifie-le avec ton parent ou ton professeur.";
                } else {
                    $throttle->echec($username, $ip);
                    $afficherCode = true;
                    $message = "Je ne connais pas ce pseudo. Si c'est ta première fois, entre le code de ton équipage.";
                }
            } else {
                // Adultes : le rôle (teacher / admin) vient de la base, jamais du formulaire
                $stmt = $pdo->prepare("SELECT id, pin_hash, role FROM staff WHERE username = ?");
                $stmt->execute([$username]);
                $staff = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($staff && password_verify($password, $staff['pin_hash'])) {
                    $throttle->reussite($username);
                    SessionCreator::create((int)$staff['id'], $staff['role'], $username);
                }
                $throttle->echec($username, $ip);
                $message = "Identifiant ou mot de passe incorrect.";
            }
        } catch (ErreurMetier $e) {
            $message = $e->getMessage();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "La capitainerie est injoignable (Erreur BDD).";
        }
    }
}

?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Les Moussaillons Numériques</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Page autonome : n'utilise pas style.css pour éviter les styles globaux de boutons/champs */
        @font-face {
            font-family: 'BelleAllure';
            src: url('assets/fonts/belleallure.otf') format('opentype');
            font-display: swap;
        }

        :root {
            --encre: #1e3a8a;          /* bleu stylo plume */
            --encre-claire: #3b82f6;
            --marge: #f87171;          /* marge rouge du cahier */
            --ligne: #c7d2fe;          /* lignes Seyès */
            --ligne-fine: #e0e7ff;
            --vert: #10b981;
            --vert-fonce: #059669;
            --texte: #1e293b;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: 'Fredoka', 'Comic Sans MS', sans-serif;
            color: var(--texte);
            background: linear-gradient(#7dd3fc 0%, #bae6fd 45%, #e0f2fe 70%);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }
        .hidden { display: none !important; }

        /* ---------- DÉCOR : ciel, soleil, nuages, mouettes, île, vagues ---------- */
        .decor { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
        .soleil {
            position: absolute; top: 40px; right: 8%; width: 110px; height: 110px; border-radius: 50%;
            background: radial-gradient(circle, #fde047 55%, #facc15 100%);
            box-shadow: 0 0 0 18px rgba(253, 224, 71, .35), 0 0 0 40px rgba(253, 224, 71, .15);
            animation: briller 4s ease-in-out infinite;
        }
        .nuage { position: absolute; opacity: .95; animation: deriver linear infinite; }
        .nuage.n1 { top: 8%;  width: 220px; animation-duration: 70s; animation-delay: -10s; }
        .nuage.n2 { top: 22%; width: 150px; animation-duration: 95s; animation-delay: -60s; opacity: .8; }
        .nuage.n3 { top: 4%;  width: 120px; animation-duration: 120s; animation-delay: -30s; opacity: .7; }
        .mouette { position: absolute; width: 45px; animation: voler linear infinite; }
        .mouette.m1 { top: 18%; animation-duration: 28s; }
        .mouette.m2 { top: 30%; width: 32px; animation-duration: 36s; animation-delay: -14s; }
        .vagues { position: absolute; left: 0; bottom: 0; width: 200%; height: 140px; }
        .vagues path { animation: houle linear infinite; }
        .vagues .v1 { animation-duration: 14s; }
        .vagues .v2 { animation-duration: 9s; }
        .vagues .v3 { animation-duration: 6s; }

        @keyframes briller { 50% { transform: scale(1.06); } }
        @keyframes deriver { from { transform: translateX(-260px); } to { transform: translateX(110vw); } }
        @keyframes voler {
            0%   { transform: translate(-60px, 0); }
            25%  { transform: translate(27vw, -18px); }
            50%  { transform: translate(55vw, 6px); }
            75%  { transform: translate(82vw, -12px); }
            100% { transform: translate(110vw, 0); }
        }
        @keyframes houle { to { transform: translateX(-50%); } }
        @keyframes flotter { 50% { transform: translateY(-8px) rotate(-3deg); } }

        /* ---------- LA PAGE DE CAHIER ---------- */
        .scene {
            position: relative; z-index: 1;
            min-height: 100vh;
            display: flex; flex-direction: column; align-items: center;
            padding: 115px 16px 150px;
        }
        .cahier {
            position: relative;
            width: 100%; max-width: 460px;
            padding: 62px 28px 26px 50px;
            border-radius: 6px 6px 18px 18px;
            /* Grands carreaux Seyès + marge rouge */
            background-color: #fffef7;
            background-image:
                linear-gradient(90deg, transparent 32px, var(--marge) 32px, var(--marge) 34px, transparent 34px),
                linear-gradient(var(--ligne) 1px, transparent 1px),
                linear-gradient(var(--ligne-fine) 1px, transparent 1px),
                linear-gradient(90deg, var(--ligne-fine) 1px, transparent 1px);
            background-size: 100% 100%, 100% 32px, 100% 8px, 32px 100%;
            box-shadow: 0 2px 0 #e5e7eb, 0 18px 40px rgba(30, 58, 138, .25);
            transform: rotate(-.6deg);
        }
        /* Morceaux de scotch */
        .cahier::before, .cahier::after {
            content: ''; position: absolute; top: -14px; width: 95px; height: 30px;
            background: rgba(253, 230, 138, .75); box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .cahier::before { left: 18px; transform: rotate(-8deg); }
        .cahier::after  { right: 18px; transform: rotate(7deg); }

        .bateau {
            position: absolute; left: 50%; top: -92px; width: 150px; margin-left: -75px;
            animation: flotter 3.5s ease-in-out infinite;
            filter: drop-shadow(0 8px 6px rgba(30, 58, 138, .2));
        }
        .titre-ecrit {
            font-family: 'BelleAllure', cursive;
            font-weight: normal;
            color: var(--encre);
            font-size: clamp(1.5rem, 6.4vw, 2.1rem);
            line-height: 1.3;
            white-space: nowrap;
            text-align: center;
            margin: 0 0 6px;
        }
        .sous-titre { text-align: center; color: #475569; margin: 0 0 12px; font-size: 1.05rem; }

        /* Bulle du perroquet (erreurs) */
        .bulle {
            position: relative; display: flex; gap: 10px; align-items: flex-start;
            background: #fff7ed; border: 3px solid #fb923c; border-radius: 18px;
            padding: 12px 14px; margin-bottom: 16px; font-weight: 500; color: #9a3412;
            animation: hop .4s ease-out;
        }
        .bulle .perroquet { font-size: 1.8rem; line-height: 1; }
        @keyframes hop { 0% { transform: scale(.8); opacity: 0; } 70% { transform: scale(1.04); } 100% { transform: scale(1); opacity: 1; } }

        /* Bannière "Tu rejoins l'équipage" */
        .parchemin {
            text-align: center; background: #fef3c7; border: 3px dashed #d97706; border-radius: 16px;
            padding: 12px; margin-bottom: 16px; color: #78350f;
        }
        .parchemin strong { display: block; font-size: 1.35rem; color: #92400e; }

        /* Champs */
        .champ { margin-bottom: 12px; }
        .champ label { display: block; font-weight: 600; color: var(--encre); font-size: 1.15rem; margin-bottom: 6px; }
        .champ input[type=text], .champ input[type=password] {
            width: 100%; padding: 11px 16px; margin: 0;
            font: 500 1.4rem 'Fredoka', sans-serif; color: var(--texte);
            background: white; border: 3px solid #93c5fd; border-radius: 18px; outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .champ input:focus { border-color: var(--encre-claire); box-shadow: 0 0 0 5px rgba(59, 130, 246, .2); }
        .champ input::placeholder { color: #94a3b8; font-weight: 400; }

        /* Code secret : 4 cases + vrai champ transparent par-dessus (clavier numérique sur tablette) */
        .pin-zone { position: relative; display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .pin-case {
            height: 56px; border-radius: 16px; background: white; border: 3px solid #93c5fd;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem; color: var(--encre); transition: transform .15s, background .15s;
        }
        .pin-case.pleine { background: #dbeafe; border-color: var(--encre-claire); transform: scale(1.05); }
        .pin-zone input {
            position: absolute; inset: 0; width: 100%; height: 100%;
            opacity: 0; font-size: 16px; caret-color: transparent; cursor: pointer; border: 0;
        }
        .pin-zone:focus-within .pin-case:not(.pleine) { border-color: var(--encre-claire); }

        .btn-embarquer {
            display: block; width: 100%; margin-top: 14px; padding: 14px;
            font: 700 1.5rem 'Fredoka', sans-serif; letter-spacing: 1px; color: white;
            background: var(--vert); border: 0; border-radius: 20px; cursor: pointer;
            box-shadow: 0 7px 0 var(--vert-fonce); transition: transform .08s, box-shadow .08s;
        }
        .btn-embarquer:hover { filter: brightness(1.05); }
        .btn-embarquer:active { transform: translateY(5px); box-shadow: 0 2px 0 var(--vert-fonce); }

        .pastille {
            display: block; margin: 12px auto 0; padding: 8px 16px; border: 2px dashed #93c5fd; border-radius: 999px;
            background: white; color: var(--encre); font: 500 1rem 'Fredoka', sans-serif; cursor: pointer;
        }
        .pastille:hover { border-style: solid; }

        /* Espace adulte : même cahier, ton plus sobre */
        .adulte .titre-ecrit { font-size: 2.2rem; }
        .adulte .champ input { font-size: 1.15rem; }
        .adulte .btn-embarquer { background: var(--encre); box-shadow: 0 7px 0 #172554; font-size: 1.2rem; }

        /* Liens adultes, sous la carte */
        .liens-adultes { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin-top: 22px; }
        .liens-adultes a, .liens-adultes button {
            padding: 10px 16px; border-radius: 999px; border: 0; cursor: pointer; text-decoration: none;
            background: rgba(255,255,255,.85); color: var(--encre); font: 500 .95rem 'Fredoka', sans-serif;
            box-shadow: 0 3px 0 rgba(30, 58, 138, .15);
        }
        .liens-adultes a:hover, .liens-adultes button:hover { background: white; }

        @media (max-width: 520px) {
            .scene { padding-top: 100px; }
            .cahier { padding: 70px 18px 24px 44px; transform: none; }
            .bateau { width: 130px; margin-left: -65px; top: -80px; }
            .soleil { display: none; }
            .pin-case { height: 56px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body>
    <!-- Décor marin (purement visuel) -->
    <div class="decor" aria-hidden="true">
        <div class="soleil"></div>
        <img class="nuage n1" src="assets/img/nuages1.svg" alt="">
        <img class="nuage n2" src="assets/img/nuages1.svg" alt="">
        <img class="nuage n3" src="assets/img/nuages1.svg" alt="">
        <img class="mouette m1" src="assets/img/mouette.png" alt="">
        <img class="mouette m2" src="assets/img/mouette.png" alt="">
        <svg class="vagues" viewBox="0 0 2880 140" preserveAspectRatio="none">
            <path class="v1" fill="#38bdf8" d="M0 60 Q180 20 360 60 T720 60 T1080 60 T1440 60 T1800 60 T2160 60 T2520 60 T2880 60 V140 H0Z"/>
            <path class="v2" fill="#0ea5e9" opacity=".9" d="M0 85 Q180 50 360 85 T720 85 T1080 85 T1440 85 T1800 85 T2160 85 T2520 85 T2880 85 V140 H0Z"/>
            <path class="v3" fill="#0284c7" d="M0 110 Q180 85 360 110 T720 110 T1080 110 T1440 110 T1800 110 T2160 110 T2520 110 T2880 110 V140 H0Z"/>
        </svg>
    </div>

    <div class="scene">

        <!-- ESPACE ENFANT (affiché d'emblée) -->
        <main id="carte-eleve" class="cahier <?= $espace === 'eleve' ? '' : 'hidden' ?>">
            <img class="bateau" src="assets/img/ui/logo.png" alt="">
            <h1 class="titre-ecrit">Bienvenue à bord !</h1>

            <?php if ($espace === 'eleve' && $message): ?>
                <div class="bulle" role="alert"><span class="perroquet">🦜</span><span><?= s($message) ?></span></div>
            <?php endif; ?>

            <form id="form-eleve" method="POST">
                <?php if ($equipage): ?>
                    <div class="parchemin">
                        🗺️ Tu rejoins l'équipage
                        <strong><?= s($equipage['name']) ?></strong>
                        <small>Invente ton pseudo et ton code secret.<br>Déjà inscrit ? Connecte-toi comme d'habitude.</small>
                    </div>
                    <input type="hidden" name="equipage" value="<?= s($equipage['code']) ?>">
                <?php else: ?>
                    <p class="sous-titre">Qui monte sur le bateau aujourd'hui ?</p>
                <?php endif; ?>

                <input type="hidden" name="_csrf" value="<?= s(Csrf::token()) ?>">
                <input type="hidden" name="espace" value="eleve">

                <div class="champ">
                    <label for="pseudo">🧒 Mon pseudo</label>
                    <input type="text" id="pseudo" name="username" value="<?= $espace === 'eleve' ? s($username) : '' ?>"
                           placeholder="ex. Capitaine Lou" required autocomplete="username" autocapitalize="off" spellcheck="false">
                </div>

                <div class="champ">
                    <label for="pin">🔒 Mon code secret</label>
                    <div class="pin-zone">
                        <div class="pin-case"></div><div class="pin-case"></div><div class="pin-case"></div><div class="pin-case"></div>
                        <input type="password" id="pin" name="password" inputmode="numeric" pattern="\d{4}" maxlength="4"
                               required autocomplete="current-password" aria-label="Code secret à 4 chiffres">
                    </div>
                </div>

                <?php if (!$equipage): ?>
                    <div class="champ <?= $afficherCode ? '' : 'hidden' ?>" id="champ-equipage">
                        <label for="code-equipage">🗺️ Le code de mon équipage</label>
                        <input type="text" id="code-equipage" name="equipage" value="<?= s($codeEquipage) ?>"
                               placeholder="ex. K7MQ4P" autocapitalize="characters" autocomplete="off" spellcheck="false">
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn-embarquer">EMBARQUER ⛵</button>

                <?php if (!$equipage && !$afficherCode): ?>
                    <button type="button" class="pastille" id="btn-premiere-fois">✨ C'est ma première fois</button>
                <?php endif; ?>
            </form>
        </main>

        <!-- ESPACE ADULTE : parents, enseignants et amirauté (le rôle est lu en base) -->
        <main id="carte-adulte" class="cahier adulte <?= $espace === 'adulte' ? '' : 'hidden' ?>">
            <img class="bateau" src="assets/img/ui/logo.png" alt="">
            <h1 class="titre-ecrit">Espace adulte</h1>
            <p class="sous-titre">Parents, enseignants</p>

            <?php if ($espace === 'adulte' && $message): ?>
                <div class="bulle" role="alert"><span class="perroquet">🦜</span><span><?= s($message) ?></span></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="_csrf" value="<?= s(Csrf::token()) ?>">
                <input type="hidden" name="espace" value="adulte">
                <div class="champ">
                    <label for="identifiant">Identifiant</label>
                    <input type="text" id="identifiant" name="username" value="<?= $espace === 'adulte' ? s($username) : '' ?>" required autocomplete="username">
                </div>
                <div class="champ">
                    <label for="mdp">Mot de passe</label>
                    <input type="password" id="mdp" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-embarquer">CONNEXION</button>
            </form>
        </main>

        <nav class="liens-adultes">
            <button type="button" id="btn-basculer"><?= $espace === 'adulte' ? '🧒 Espace enfant' : '👩‍🏫 Parents et enseignants' ?></button>
            <a href="aide.php">❓ Comment ça marche ?</a>
        </nav>
    </div>

    <script>
        // --- Code secret : 4 cases, envoi automatique au 4e chiffre ---
        const formEleve = document.getElementById('form-eleve');
        const pseudo = document.getElementById('pseudo');
        const pin = document.getElementById('pin');
        const cases = document.querySelectorAll('.pin-case');

        function majPin() {
            pin.value = pin.value.replace(/\D/g, '').slice(0, 4);
            cases.forEach((c, i) => {
                const pleine = i < pin.value.length;
                c.classList.toggle('pleine', pleine);
                c.textContent = pleine ? '⭐' : '';
            });
            if (pin.value.length === 4 && pseudo.value.trim() !== '') {
                setTimeout(() => formEleve.requestSubmit(), 300);
            }
        }
        pin.addEventListener('input', majPin);

        // Pseudo validé avec Entrée : on passe directement au code secret
        pseudo.addEventListener('keydown', e => {
            if (e.key === 'Enter' && pin.value.length < 4) { e.preventDefault(); pin.focus(); }
        });

        // --- Première fois : affiche le champ du code d'équipage ---
        const btnPremiereFois = document.getElementById('btn-premiere-fois');
        if (btnPremiereFois) {
            btnPremiereFois.addEventListener('click', () => {
                btnPremiereFois.remove();
                const champ = document.getElementById('champ-equipage');
                champ.classList.remove('hidden');
                champ.querySelector('input').focus();
            });
        }

        // --- Bascule enfant / adulte sans recharger ---
        const btnBasculer = document.getElementById('btn-basculer');
        btnBasculer.addEventListener('click', () => {
            const versAdulte = !document.getElementById('carte-eleve').classList.contains('hidden');
            document.getElementById('carte-eleve').classList.toggle('hidden', versAdulte);
            document.getElementById('carte-adulte').classList.toggle('hidden', !versAdulte);
            btnBasculer.textContent = versAdulte ? '🧒 Espace enfant' : '👩‍🏫 Parents et enseignants';
            (versAdulte ? document.getElementById('identifiant') : pseudo).focus();
        });

        // Focus de départ : le pseudo, ou le code s'il est déjà rempli (retour après erreur)
        if (!document.getElementById('carte-eleve').classList.contains('hidden')) {
            (pseudo.value ? pin : pseudo).focus();
        }
    </script>
</body>
</html>
