<?php 
require_once '../../includes/check_session.php';  

// LOGIQUE D'ACHAT
if (isset($_GET['action']) && $_GET['action'] === 'acheter' && isset($_GET['ship_id'])) { 
    $ship_id = (int)$_GET['ship_id']; 

    $stmt = $pdo->prepare("SELECT price FROM ships WHERE id = ?"); 
    $stmt->execute([$ship_id]); 
    $nouveau_bateau = $stmt->fetch(); 

    if ($nouveau_bateau && $user['points'] >= $nouveau_bateau['price']) { 
        $stmt = $pdo->prepare("UPDATE users SET points = points - ?, current_ship_id = ? WHERE id = ?"); 
        $stmt->execute([$nouveau_bateau['price'], $ship_id, $user['id']]); 
        header("Location: port.php"); 
        exit; 
    } 
} 

// RÉCUPÉRATION DES BOUTIQUE SHIPS
$stmt = $pdo->prepare("SELECT id, name, img_url, price FROM ships WHERE id != (SELECT current_ship_id FROM users WHERE id = ?)"); 
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
</head> 

<body class="layout-grid">  
    <?php include './nav.php'; ?> 

    <main class="main-container"> 
        <div class="viewport" id="ciel"> 
            <img src="../../assets/img/port_fond.png" class="img-fond"> 
            <img src="../../assets/img/nuages.png" class="img-nuages-animes"> 
            <div class="limiteur-espace"></div> 
            <img src="../../assets/img/port_fond_reflets1.png" class="img-mer-reflets"> 

            <img src="../../assets/img/ships/<?php echo $user['img_url']; ?>" class="navire-actuel" alt="Navire Actuel"> 
        </div> 

        <aside class="sidebar-navires"> 
            <h2 style="margin:0; color:var(--primary); text-align:center;">🚢 Chantier Naval</h2> 
             
            <div class="scroll-list"> 
                <?php foreach ($boutique_ships as $ship): ?> 
                    <div class="ship-card"> 
                        <img src="../../assets/img/ships/<?php echo $ship['img_url']; ?>" class="ship-preview"> 
                        <h3 style="margin:5px 0;"><?php echo htmlspecialchars($ship['name']); ?></h3> 
                        <div style="color: var(--secondary); font-weight: bold;"><?php echo $ship['price']; ?> 🪙</div> 
                         
                        <?php if ($user['points'] >= $ship['price']): ?> 
                            <a href="?action=acheter&ship_id=<?php echo $ship['id']; ?>" class="btn-acheter">ACHETER</a> 
                        <?php else: ?> 
                            <span style="color:#cbd5e1; display:block; margin-top:10px;">Points insuffisants</span> 
                        <?php endif; ?> 
                    </div> 
                <?php endforeach; ?> 
            </div> 
        </aside> 
    </main> 

    <script> 
         function genererMouette() { 
             const ciel = document.getElementById('ciel'); 
             if (!ciel) return; 
             const m = document.createElement('div'); 

             // Retour de tes vraies mouettes PNG
             m.innerHTML = '<img src="../../assets/img/mouette.png" style="width:100%; height:100%; object-fit:contain;">'; 
             
             const versLaDroite = Math.random() > 0.5; 
             const r = Math.sqrt(Math.random()); 
             const taillePx = 10 + (r * 25); 
             const duree = 25 - (r * 15); 
              
             Object.assign(m.style, { 
                 position: 'absolute', 
                 top: (Math.random() * 80) + '%', 
                 left: versLaDroite ? '-50px' : (ciel.offsetWidth + 50) + 'px', 
                 width: taillePx + 'px', 
                 height: (taillePx * 0.6) + 'px', 
                 zIndex: '2', 
                 transition: `transform ${duree}s linear`, 
                 transform: `scaleX(${versLaDroite ? -1 : 1})`, 
                 pointerEvents: 'none' 
             }); 

             ciel.appendChild(m); 
             setTimeout(() => { 
                 m.style.transform = `translateX(${versLaDroite ? ciel.offsetWidth + 200 : -ciel.offsetWidth - 200}px) scaleX(${versLaDroite ? -1 : 1})`; 
             }, 100); 
             setTimeout(() => m.remove(), duree * 1000); 
         }  
         setInterval(() => { if (Math.random() < 0.4) genererMouette(); }, 3000); 
    </script> 
</body> 
</html>