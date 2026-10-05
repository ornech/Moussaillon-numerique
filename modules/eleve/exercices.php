<?php
// modules/eleve/exercices.php
require_once '../../includes/check_session.php';    

// 1. RÉCUPÉRATION DES PARAMÈTRES
$activite_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($activite_id <= 0) {
    die("Mission introuvable.");
}

// 2. RÉCUPÉRATION DE L'ACTIVITÉ
// Le quiz (questions, correction, points) est géré par l'API : api/index.php/quiz/{id}/...
$stmt = $pdo->prepare("SELECT id, title, content_html FROM activities WHERE id = ? AND is_validated = 1");
$stmt->execute([$activite_id]);
$activite = $stmt->fetch();

if (!$activite) {
    die("Mission introuvable ou non validée.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <?php echo \Jf\Moussaillons\Infrastructure\Security\Csrf::metaTag(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($activite['title']); ?> - Mission</title>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        /* Styles extraits de jeu.html pour garantir la cohérence visuelle */
        :root {
            --primary: #10b981; --secondary: #f59e0b; --danger: #ef4444;
            --bg-app: #fefce8; --text: #451a03; --card-bg: #ffffff;
        }

        @font-face {
            font-family: 'BelleAllure';
            /* On remonte de modules/eleve/ vers la racine pour aller dans assets/fonts/ */
            src: url('../../assets/fonts/belleallure.otf') format('opentype');
            font-weight: normal;
            font-style: normal;
        }

        body {
            background: url('../../assets/img/ui/bg-ile01.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Fredoka', sans-serif;
            margin: 0;
            padding-top: 120px;
        }

        .header-aventure {
            position: fixed; top: 0; width: 100%; height: 90px;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px; box-sizing: border-box; z-index: 100;
        }

        .score-badge {
            background: white; padding: 10px 20px; border-radius: 20px;
            border-bottom: 4px solid var(--secondary); text-align: center;
        }

        .game-wrapper {
            max-width: 900px; margin: 0 auto 40px; width: 90%;
            display: flex; flex-direction: column; align-items: center;
        }

        .quiz-card {
            background: var(--card-bg); padding: 40px; border-radius: 40px;
            border-bottom: 8px solid #d1d5db; text-align: center; width: 100%;
            box-sizing: border-box; box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }

        .progress-container {
            background: #e5e7eb; height: 14px; border-radius: 50px;
            margin-bottom: 10px; overflow: hidden; border: 3px solid white; width: 100%;
            visibility: hidden;
        }

        #progress-bar { height: 100%; background: var(--primary); width: 0%; transition: width 0.4s ease-out; }

        .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; width: 100%; }

        .btn-reponse {
            background: #f3f4f6; color: var(--text); border: none; padding: 20px;
            border-radius: 20px; font-size: 1.2rem; font-weight: 900; cursor: pointer;
            box-shadow: 0 6px 0 0 #d1d5db; transition: transform 0.1s;
        }

        .btn-reponse:active { transform: translateY(4px); box-shadow: none; }
        .btn-reponse.correct { background: var(--primary); color: white; box-shadow: 0 6px 0 0 #059669; }
        .btn-reponse.wrong { background: var(--danger); color: white; box-shadow: 0 6px 0 0 #991b1b; }

        .hidden { display: none; }
        
        /* Appliquer à la question du quiz */
        #question-texte {
            font-family: 'BelleAllure', cursive !important;
            font-size: 2rem; /* Augmenté car Belle Allure est souvent fine */
            margin-bottom: 20px;
        }

        /* Appliquer aux boutons de réponse */
        .btn-reponse {
            background: #f3f4f6; 
            color: var(--text); 
            border: none; 
            padding: 10px;
            border-radius: 20px; 
            
            /* MODIFICATION ICI */
            font-family: 'BelleAllure', cursive !important;
            font-size: 2rem; 
            font-weight: normal; /* La police cursive n'a souvent pas de gras */
            
            cursor: pointer;
            box-shadow: 0 6px 0 0 #d1d5db; 
            transition: transform 0.1s;
        }

        button#btn-start { background: var(--secondary); color: white; border: none; border-radius: 20px; font-weight: bold; cursor: pointer; }
    </style>
</head>

<body>
        <div class="header-aventure">
        <div style="display: flex; align-items: center; gap: 15px; background: rgba(255,255,255,0.8); padding: 10px 20px; border-radius: 50px;">
            <div style="font-size: 30px; cursor:pointer;" onclick="window.location.href='parcours.php'">🏠</div>
            <div>
                <button onclick="history.back()" style="width: auto; padding: 15px 40px; background: var(--primary); color:white; border:none; border-radius:20px; font-weight:bold; cursor:pointer; box-shadow: 0 6px 0 0 #059669;">RETOUR AU PARCOURS 🏠</button>
            </div>
        </div>
        <div class="score-badge">
            <span id="points-eleve" style="font-size: 1.8rem; font-weight: 900;"><?php echo $user['points']; ?></span><br>
            <small>ÉTOILES ⭐</small>
        </div>
    </div>

    <div class="game-wrapper">
        <div id="p-container" class="progress-container"><div id="progress-bar"></div></div>
        
        <div id="ecran-cours" class="quiz-card">
            <div id="contenu-activite" style="text-align: left; margin-bottom: 30px;">
                <?php echo $activite['content_html']; ?>
            </div>
            <button id="btn-start" style="width: auto; padding: 15px 40px; font-size:1.2rem;">C'EST PARTI ! 🚀</button>
        </div>

        <div id="ecran-quiz" class="hidden" style="width: 100%;">
            <div class="quiz-card">
                <h2 id="question-texte" style="margin-bottom: 10px;">---</h2>
                <div id="options-grid" class="options-grid"></div>
                <div id="feedback" style="font-size: 1.5rem; margin-top: 20px; font-weight: bold; min-height: 1.5em;"></div>
            </div>
        </div>

        <div id="ecran-victoire" class="hidden" style="width: 100%;">
            <div class="quiz-card">
                <div style="font-size: 80px; margin-bottom: 20px;">🏆</div>
                <h1 id="titre-victoire" style="color: var(--primary); margin: 0;">BIEN JOUÉ !</h1>
                <p style="font-size: 1.2rem; margin: 20px 0;">Score final : <span id="etoiles-finales" style="color: var(--secondary); font-size: 2rem; font-weight: 900;">0</span> étoiles</p>
                <button onclick="history.back()" style="width: auto; padding: 15px 40px; background: var(--primary); color:white; border:none; border-radius:20px; font-weight:bold; cursor:pointer; box-shadow: 0 6px 0 0 #059669;">RETOUR AU PARCOURS 🏠</button>            </div>
        </div>
    </div>

    <script>
        // Le serveur tire les questions, corrige chaque réponse et calcule les points.
        const API_QUIZ = '../../api/index.php/quiz/<?php echo (int)$activite['id']; ?>';
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

        let quizData = [];
        let indexQ = 0;

        async function appelApi(action, donnees = {}) {
            const response = await fetch(`${API_QUIZ}/${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify(donnees)
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Erreur de liaison');
            return result;
        }

        function afficherErreur(err) {
            document.getElementById('feedback').innerText = "⚠️ " + err.message;
        }

        document.getElementById('btn-start').onclick = async () => {
            try {
                quizData = (await appelApi('start')).questions;
            } catch (err) {
                alert(err.message);
                return;
            }
            indexQ = 0;
            document.getElementById('ecran-cours').classList.add('hidden');
            document.getElementById('ecran-quiz').classList.remove('hidden');
            document.getElementById('p-container').style.visibility = 'visible';
            poserQuestion();
        };

        function poserQuestion() {
            const q = quizData[indexQ];
            document.getElementById('progress-bar').style.width = ((indexQ / quizData.length) * 100) + "%";
            document.getElementById('question-texte').innerText = q.question;
            document.getElementById('feedback').innerText = "";

            const grid = document.getElementById('options-grid');
            grid.innerHTML = '';

            // Les options arrivent déjà mélangées par le serveur
            q.options.forEach(opt => {
                const btn = document.createElement('button');
                btn.className = 'btn-reponse';
                btn.innerText = opt;
                btn.onclick = () => verifier(btn, opt);
                grid.appendChild(btn);
            });
        }

        async function verifier(btn, choix) {
            document.querySelectorAll('.btn-reponse').forEach(b => b.style.pointerEvents = 'none');

            let result;
            try {
                result = await appelApi('answer', { choix });
            } catch (err) {
                afficherErreur(err);
                return;
            }

            if (result.correct) {
                btn.classList.add('correct');
                document.getElementById('feedback').innerText = "Génial ! 🌟";
            } else {
                btn.classList.add('wrong');
                document.getElementById('feedback').innerText = "Oups ! C'était : " + result.solution + (result.aide ? "\n💡 " + result.aide : "");
            }

            indexQ++;
            setTimeout(() => {
                if (!result.termine) poserQuestion();
                else terminerMission(result);
            }, 2500);
        }

        function terminerMission(result) {
            document.getElementById('points-eleve').innerText = result.new_total;

            document.getElementById('ecran-quiz').classList.add('hidden');
            document.getElementById('ecran-victoire').classList.remove('hidden');
            document.getElementById('etoiles-finales').innerText = result.score;

            lancerAnimations(result.score / result.total);
        }

        // CÉLÉBRATIONS identiques à jeu.html
        function lancerAnimations(ratio) {
            const h1 = document.getElementById('titre-victoire');
            if (ratio === 1) {
                h1.innerText = "L'OR PUR DES SEPT MERS !";
                const end = Date.now() + 5000;
                const interval = setInterval(() => {
                    if (Date.now() > end) return clearInterval(interval);
                    confetti({ startVelocity: 30, spread: 360, ticks: 60, origin: { x: Math.random(), y: Math.random() - 0.2 } });
                    confetti({ particleCount: 20, shapes: ['star'], colors: ['#FFD700'], origin: { y: 0.6 } });
                }, 200);
            } else if (ratio >= 0.75) {
                h1.innerText = "BELLE CANONNADE !";
                const end = Date.now() + 2500;
                (function frame() {
                    confetti({ particleCount: 3, angle: 60, spread: 55, origin: { x: 0 } });
                    confetti({ particleCount: 3, angle: 120, spread: 55, origin: { x: 1 } });
                    if (Date.now() < end) requestAnimationFrame(frame);
                }());
            } else {
                h1.innerText = "BIEN JOUÉ !";
                confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 } });
            }
        }
    </script>
</body>
</html>