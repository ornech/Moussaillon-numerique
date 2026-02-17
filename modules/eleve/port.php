<?php 
require_once '../../includes/check_session.php';  

$message = '';
$message_type = '';

if (isset($_GET['action']) && $_GET['action'] === 'acheter' && isset($_GET['ship_id'])) { 
    $ship_id = (int)$_GET['ship_id']; 

    $stmt = $pdo->prepare("SELECT name, price FROM ships WHERE id = ?"); 
    $stmt->execute([$ship_id]); 
    $nouveau_bateau = $stmt->fetch(); 

    if ($nouveau_bateau) {
        if ($user['points'] >= $nouveau_bateau['price']) { 
            $stmt = $pdo->prepare("UPDATE users SET points = points - ?, current_ship_id = ? WHERE id = ?"); 
            $stmt->execute([$nouveau_bateau['price'], $ship_id, $user['id']]); 
            
            $message = "Félicitations ! Vous avez acheté le " . htmlspecialchars($nouveau_bateau['name']) . " !";
            $message_type = 'success';
            
            // Recharger avec la colonne size
            $stmt = $pdo->prepare("SELECT u.*, s.name as ship_name, s.img_url, s.size FROM users u JOIN ships s ON u.current_ship_id = s.id WHERE u.id = ?");
            $stmt->execute([$user['id']]);
            $user = $stmt->fetch();
        } else {
            $message = "Points insuffisants pour acheter ce navire.";
            $message_type = 'error';
        }
    } else {
        $message = "Navire introuvable.";
        $message_type = 'error';
    }
} 

// Récupération avec la colonne size
$stmt = $pdo->prepare("SELECT id, name, img_url, price, size FROM ships WHERE id != (SELECT current_ship_id FROM users WHERE id = ?)"); 
$stmt->execute([$user['id']]); 
$boutique_ships = $stmt->fetchAll(); 
?> 

<!DOCTYPE html> 
<html lang="fr"> 
<head> 
    <meta charset="UTF-8"> 
    <title>Le Port - <?php echo htmlspecialchars($user['ship_name']); ?></title> 
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;700&display=swap" rel="stylesheet"> 
    <link rel="stylesheet" href="../../assets/css/style.css"> 
    <style>
        .message-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            animation: slideDown 0.3s ease-out;
        }
        
        .message {
            padding: 15px 30px;
            border-radius: 12px;
            font-weight: bold;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            font-size: 1.1em;
        }
        
        .message.success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .message.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 9998;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            animation: popIn 0.3s ease-out;
        }
        
        @keyframes popIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .modal-ship-preview {
            width: 150px;
            height: auto;
            margin: 15px auto;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn-confirm, .btn-cancel {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            font-size: 1em;
            transition: transform 0.2s;
        }
        
        .btn-confirm {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-cancel {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-confirm:hover, .btn-cancel:hover {
            transform: scale(1.05);
        }
        
        .navire-actuel {
            animation: tangage 5s ease-in-out infinite;
        }

        @keyframes tangage {
            0% {
                transform: translateX(-50%) translateY(0px) rotate(0deg);
            }
            25% {
                transform: translateX(-50%) translateY(-4px) rotate(-0.3deg);
            }
            50% {
                transform: translateX(-50%) translateY(-6px) rotate(0deg);
            }
            75% {
                transform: translateX(-50%) translateY(-4px) rotate(0.3deg);
            }
            100% {
                transform: translateX(-50%) translateY(0px) rotate(0deg);
            }
        }
  
    </style>
</head> 

<body class="layout-grid">  
    <?php include './nav.php'; ?> 

    <?php if ($message): ?>
    <div class="message-container" id="messageContainer">
        <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="modal-overlay" id="modalConfirm">
        <div class="modal-content">
            <h2 style="margin-top:0; color:var(--primary);">Confirmer l'achat</h2>
            <img id="modalShipImg" src="" alt="" class="modal-ship-preview">
            <h3 id="modalShipName" style="margin:10px 0;"></h3>
            <p style="font-size:1.2em; font-weight:bold; color:var(--secondary);">
                Prix: <span id="modalShipPrice"></span> 🪙
            </p>
            <p style="color:#64748b;">
                Nouveau solde: <span id="modalNewBalance"></span> 🪙
            </p>
            <div class="modal-buttons">
                <button class="btn-cancel" onclick="fermerModal()">Annuler</button>
                <button class="btn-confirm" id="btnConfirmerAchat">Acheter</button>
            </div>
        </div>
    </div>

    <main class="main-container"> 
        <div class="viewport" id="ciel"> 
            <img src="../../assets/img/port_fond.png" class="img-fond"> 
            <img src="../../assets/img/nuages.png" class="img-nuages-animes"> 
            <div class="limiteur-espace"></div> 
            <img src="../../assets/img/port_fond_reflets1.png" class="img-mer-reflets"> 

            <div class="navire-actuel <?php echo $message_type === 'success' ? 'nouveau' : ''; ?>" 
                style="width: <?php echo $user['size']; ?>px !important;">
                <img src="../../assets/img/ships/<?php echo $user['img_url']; ?>" 
                    alt="Navire Actuel">
            </div>
        </div> 

        <aside class="sidebar-navires"> 
            <h2 style="margin:0; color:var(--primary); text-align:center;">🚢 Chantier Naval</h2> 
            <p style="text-align:center; color:var(--secondary); margin:10px 0;">
                Votre solde: <strong><?php echo $user['points']; ?> 🪙</strong>
            </p>
             
            <div class="scroll-list"> 
                <?php foreach ($boutique_ships as $ship): ?> 
                    <div class="ship-card"> 
                        <img src="../../assets/img/ships/<?php echo $ship['img_url']; ?>" 
                             class="ship-preview"
                             style="width: <?php echo $ship['size'] * 0.5; ?>px !important;"> 
                        <h3 style="margin:5px 0;"><?php echo htmlspecialchars($ship['name']); ?></h3> 
                        <div style="color: var(--secondary); font-weight: bold;"><?php echo $ship['price']; ?> 🪙</div> 
                         
                        <?php if ($user['points'] >= $ship['price']): ?> 
                            <button class="btn-acheter" 
                                    data-ship-id="<?php echo $ship['id']; ?>"
                                    data-ship-name="<?php echo htmlspecialchars($ship['name']); ?>"
                                    data-ship-img="<?php echo htmlspecialchars($ship['img_url']); ?>"
                                    data-ship-price="<?php echo $ship['price']; ?>"
                                    onclick="ouvrirConfirmation(this)">
                                ACHETER
                            </button>
                        <?php else: ?> 
                            <span style="color:#cbd5e1; display:block; margin-top:10px;">Points insuffisants</span> 
                        <?php endif; ?> 
                    </div> 
                <?php endforeach; ?> 
            </div> 
        </aside> 
    </main> 

    <script> 
        <?php if ($message): ?>
        setTimeout(() => {
            const msgContainer = document.getElementById('messageContainer');
            if (msgContainer) {
                msgContainer.style.animation = 'slideDown 0.3s ease-out reverse';
                setTimeout(() => msgContainer.remove(), 300);
            }
        }, 4000);
        <?php endif; ?>
        
        let achatEnCours = null;
        
        function ouvrirConfirmation(button) {
            const shipId = button.getAttribute('data-ship-id');
            const shipName = button.getAttribute('data-ship-name');
            const shipImg = button.getAttribute('data-ship-img');
            const shipPrice = parseInt(button.getAttribute('data-ship-price'));
            const userPoints = <?php echo $user['points']; ?>;
            const newBalance = userPoints - shipPrice;
            
            document.getElementById('modalShipImg').src = `../../assets/img/ships/${shipImg}`;
            document.getElementById('modalShipName').textContent = shipName;
            document.getElementById('modalShipPrice').textContent = shipPrice;
            document.getElementById('modalNewBalance').textContent = newBalance;
            
            achatEnCours = shipId;
            document.getElementById('modalConfirm').classList.add('active');
        }
        
        function fermerModal() {
            document.getElementById('modalConfirm').classList.remove('active');
            achatEnCours = null;
        }
        
        document.getElementById('btnConfirmerAchat').addEventListener('click', () => {
            if (achatEnCours) {
                window.location.href = `?action=acheter&ship_id=${achatEnCours}`;
            }
        });
        
        document.getElementById('modalConfirm').addEventListener('click', (e) => {
            if (e.target.id === 'modalConfirm') {
                fermerModal();
            }
        });
        
        function genererMouette() {
            const ciel = document.getElementById('ciel');
            if (!ciel) return;
            
            const mouette = new Image();
            mouette.src = "../../assets/img/mouette.png";
            
            const y = (Math.random() * 0.3) + 0.1; 
            const scale = 0.2 + (y * 3.5); 
            const vitesseBase = 2.5;
            const dureeMs = (vitesseBase / y) * 1000;
            
            const versLaDroite = Math.random() > 0.5;
            const largeurCiel = ciel.offsetWidth;
            const distanceMarge = 300 * scale;
            const distanceTotal = largeurCiel + (distanceMarge * 2);
            const ondulationY = (Math.random() - 0.5) * 20 * scale;
            
            Object.assign(mouette.style, {
                position: 'absolute',
                top: (y * 100) + '%',
                left: versLaDroite ? `-${distanceMarge}px` : `${largeurCiel + distanceMarge}px`,
                width: (45 * scale) + 'px',
                height: 'auto',
                zIndex: Math.floor(y * 100),
                pointerEvents: 'none',
                transform: `scaleX(${versLaDroite ? -1 : 1})`
            });
            
            ciel.appendChild(mouette);
            
            mouette.animate([
                { transform: `translateX(0) translateY(0) scaleX(${versLaDroite ? -1 : 1})` },
                { transform: `translateX(${versLaDroite ? distanceTotal : -distanceTotal}px) translateY(${ondulationY}px) scaleX(${versLaDroite ? -1 : 1})` }
            ], {
                duration: dureeMs,
                easing: 'ease-in-out'
            }).onfinish = () => mouette.remove();
        }

        function startAnimation() {
            for(let i=0; i<3; i++) {
                genererMouette();
            }
            setInterval(() => {
                if (Math.random() < 0.4) genererMouette();
            }, 3000);
        }

        startAnimation();
    </script> 
</body> 
</html>