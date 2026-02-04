<?php
// modules/eleve/exercices.php
require_once '../../includes/check_session.php';    

// 1. RÉCUPÉRATION DES PARAMÈTRES
$activite_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id']; 

// exercices.php
$activite_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id']; // Déjà disponible via check_session.php


if ($activite_id <= 0) {
    die("Mission introuvable.");
}

// 2. RÉCUPÉRATION DE L'ACTIVITÉ
$stmt = $pdo->prepare("SELECT * FROM activities WHERE id = ? AND is_validated = 1");
$stmt->execute([$activite_id]);
$activite = $stmt->fetch();

if (!$activite) {
    die("Mission introuvable ou non validée.");
}

// 3. TRAITEMENT AJAX DU SCORE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['score'])) {
    $score = (int)$_POST['score'];
    $total = (int)$_POST['total'];

    // Récupérer le score de la DERNIÈRE tentative enregistrée
    $stmt = $pdo->prepare("SELECT score_max, nbr_question FROM history WHERE user_id = ? AND activity_id = ?");
    $stmt->execute([$user_id, $activite_id]);
    $last_attempt = $stmt->fetch();

    $points_a_ajouter = 0;

    // LOGIQUE : On crédite si aucune tentative n'existe OU si la dernière n'était pas un 100%
    if (!$last_attempt || ($last_attempt['score_max'] < $last_attempt['nbr_question'])) {
        // Ici on crédite l'intégralité du score réalisé lors de cette nouvelle tentative
        $points_a_ajouter = $score;
        
        $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $stmt->execute([$points_a_ajouter, $user_id]);
    }

    // MISE À JOUR SYSTÉMATIQUE DE LA TABLE HISTORY (Dernier score et nombre de questions)
    if ($last_attempt) {
        $stmt = $pdo->prepare("UPDATE history SET score_max = ?, nbr_question = ?, date_completion = NOW() WHERE user_id = ? AND activity_id = ?");
        $stmt->execute([$score, $total, $user_id, $activite_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO history (user_id, activity_id, score_max, nbr_question, date_completion) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $activite_id, $score, $total]);
    }

    echo json_encode([
        'status' => 'success', 
        'points_gagnes' => $points_a_ajouter,
        'new_total' => ($user['points'] + $points_a_ajouter)
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($activite['title']); ?> - Mission</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        /* Styles extraits de jeu.html pour garantir la cohérence visuelle */
        :root {
            --primary: #10b981; --secondary: #f59e0b; --danger: #ef4444;
            --bg-app: #fefce8; --text: #451a03; --card-bg: #ffffff;
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
        const quizData = <?php echo $activite['quiz_json']; ?>;
        let indexQ = 0;
        let etoilesGagnees = 0;

        document.getElementById('btn-start').onclick = () => {
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

            const options = [...q.options].sort(() => Math.random() - 0.5);
            options.forEach(opt => {
                const btn = document.createElement('button');
                btn.className = 'btn-reponse';
                btn.innerText = opt;
                // On vérifie par rapport à l'index de la réponse correcte ou le texte selon ta structure JSON
                btn.onclick = () => verifier(btn, opt, q.reponse || q.options[q.answer]);
                grid.appendChild(btn);
            });
        }

        function verifier(btn, choix, correct) {
            document.querySelectorAll('.btn-reponse').forEach(b => b.style.pointerEvents = 'none');

            if (choix == correct) {
                btn.classList.add('correct');
                etoilesGagnees++;
                document.getElementById('feedback').innerText = "Génial ! 🌟";
            } else {
                btn.classList.add('wrong');
                document.getElementById('feedback').innerText = "Oups ! C'était : " + correct;
            }

            indexQ++;
            setTimeout(() => {
                if (indexQ < quizData.length) poserQuestion();
                else terminerMission();
            }, 1200);
        }

        async function terminerMission() {
            // Sauvegarde AJAX vers la même page (PHP en haut)
            const formData = new FormData();
            formData.append('score', etoilesGagnees);
            formData.append('total', quizData.length);

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if(result.new_total) document.getElementById('points-eleve').innerText = result.new_total;

            document.getElementById('ecran-quiz').classList.add('hidden');
            document.getElementById('ecran-victoire').classList.remove('hidden');
            document.getElementById('etoiles-finales').innerText = etoilesGagnees;

            lancerAnimations(etoilesGagnees / quizData.length);
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