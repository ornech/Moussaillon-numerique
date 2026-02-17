<?php
require_once '../../includes/check_session.php'; 

$matiere = $_GET['matiere'] ?? '';
$theme_selectionne = $_GET['theme'] ?? '';
$affichage_carte = false; // Par défaut, on n'affiche pas la carte

// --- LOGIQUE DE RÉCUPÉRATION ---

if ($theme_selectionne) {
    // CAS 2 : UN THÈME EST CHOISI -> ON AFFICHE LA CARTE DES EXERCICES
    $stmt = $pdo->prepare("SELECT id, title, theme, distance FROM activities WHERE theme = ? AND is_validated = 1 ORDER BY id ASC");
    $stmt->execute([$theme_selectionne]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $affichage_carte = true;

    // --- GÉNÉRATION DE LA CARTE (Uniquement ici) ---
    $largeurMer = 3000;
    $hauteurMer = 2000;
    // Marge énorme pour garantir qu'aucun bout d'île ne touche le bord
    $paddingSecurite = 350; 
    $distanceMinEntreArchipels = 500; 
    
    $carteDonnees = [];

    function getDistance($x1, $y1, $x2, $y2) {
        return sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));
    }

    foreach ($items as $item) {
        $tries = 0;
        $validPosition = false;
        $centerX = 0; $centerY = 0;

        // Tentative de placement avec marge de sécurité stricte
        do {
            $centerX = rand($paddingSecurite, $largeurMer - $paddingSecurite); 
            $centerY = rand($paddingSecurite, $hauteurMer - $paddingSecurite);
            
            $tooClose = false;
            foreach ($carteDonnees as $existing) {
                if (getDistance($centerX, $centerY, $existing['main']['x'], $existing['main']['y']) < $distanceMinEntreArchipels) {
                    $tooClose = true;
                    break;
                }
            }
            if (!$tooClose) $validPosition = true;
            $tries++;
        } while (!$validPosition && $tries < 200);

        // Position de repli (au centre avec un léger décalage) si échec
        if (!$validPosition) {
            $centerX = ($largeurMer/2) + rand(-200, 200);
            $centerY = ($hauteurMer/2) + rand(-200, 200);
        }

        // Génération des îlots
        $nbIlots = rand(3, 6);
        $ilots = [];

        // Île Principale (Centre)
        $ilots[] = [
            'x' => $centerX,
            'y' => $centerY,
            'size' => rand(70, 90), // Assez grosse pour le texte
            'seed' => mt_rand(0, 1000)
        ];

        // Satellites
        for ($i = 1; $i < $nbIlots; $i++) {
            $parent = $ilots[array_rand($ilots)];
            $angle = (mt_rand() / mt_getrandmax()) * 2 * M_PI;
            $dist = 80 + mt_rand(0, 50); 

            // Calcul position satellite
            $satX = $parent['x'] + cos($angle) * $dist;
            $satY = $parent['y'] + sin($angle) * $dist;

            // Clamp strict : Si le satellite sort, on le ramène vers le centre de l'archipel
            // C'est une sécurité supplémentaire
            if ($satX < 100) $satX = 100;
            if ($satX > $largeurMer - 100) $satX = $largeurMer - 100;
            if ($satY < 100) $satY = 100;
            if ($satY > $hauteurMer - 100) $satY = $hauteurMer - 100;

            $ilots[] = [
                'x' => $satX,
                'y' => $satY,
                'size' => rand(30, 55),
                'seed' => mt_rand(0, 1000)
            ];
        }

        $carteDonnees[] = [
            'info' => [
                'url' => "exercices.php?id=" . $item['id'],
                'title' => $item['title'], // Le titre exact de l'exercice
            ],
            'main' => $ilots[0],
            'archipel' => $ilots
        ];
    }
    $jsonMap = json_encode($carteDonnees);

} else {
    // CAS 1 : PAS DE THÈME -> ON AFFICHE LES CARTES CLASSIQUES (MATIÈRE)
    $stmt = $pdo->prepare("SELECT theme, MIN(id) as first_id, MIN(distance) as distance FROM activities WHERE matiere = ? AND is_validated = 1 GROUP BY theme ORDER BY distance ASC");
    $stmt->execute([$matiere]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Univers d'Apprentissage</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/style.css">

    <style>
        /* CSS COMMUN */
        body { margin: 0; font-family: 'Fredoka', sans-serif; }

        /* CSS SPÉCIFIQUE CARTE (EXERCICES) */
        <?php if ($affichage_carte): ?>
        body.map-mode {
            background-color: #f8ea84;
            overflow: hidden; /* Pas de scroll sur la carte */
        }
        #map-container {
            width: 100vw; height: 100vh;
            background: radial-gradient(circle, #00bcc5 0%, #51b2b5 100%);
            display: flex; justify-content: center; align-items: center;
        }
        #world-map { width: 100%; height: 100%; object-fit: contain; }
        

        /* Styles Textes Îles */
        .island-label {
            font-family: 'Fredoka', sans-serif;
            fill: #ffffff;
            font-weight: 700;
            text-anchor: middle;       /* Centre horizontalement */
            dominant-baseline: middle; /* Centre verticalement */
            font-size: 45px;           /* Taille adaptée au viewBox 3000x2000 */
            pointer-events: none;      /* Le clic passe au travers */
            
            /* Contour pour lisibilité */
            stroke: #002233;
            stroke-width: 6px;         /* Plus épais car la police est grande */
            paint-order: stroke fill;
            
            /* On évite la sélection de texte accidentelle */
            user-select: none; 
        }
        
        .archipel-group { 
            cursor: pointer; 
            /* On garde la transition pour la couleur, mais plus pour le mouvement */
            transition: filter 0.2s; 
        }
        
        /* Au survol : On illumine juste, ON NE BOUGE PLUS */
        .archipel-group:hover { 
            filter: brightness(1.15); 
        }
        
        /* Optionnel : changer la couleur du texte au survol */
        .archipel-group:hover .island-label { 
            fill: #f8ea84; /* Jaune clair */
            stroke: #000; 
        }
        <?php endif; ?>

        /* CSS CLASSIQUE (THÈMES) */
        .universe-container { padding: 40px; display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .theme-card { background: white; border-radius: 15px; padding: 20px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .theme-card:hover { transform: translateY(-5px); }
        .card-link { text-decoration: none; color: inherit; }
    </style>
</head>
<body class="<?php echo $affichage_carte ? 'map-mode' : 'grid-mode'; ?>">

    <?php include './nav.php'; ?>

    <?php if (!$affichage_carte): ?>
        <div class="universe-container">
            <?php if (empty($items)): ?>
                <p>Aucun thème trouvé pour cette matière.</p>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <a href="?matiere=<?php echo urlencode($matiere); ?>&theme=<?php echo urlencode($item['theme']); ?>" class="card-link">
                        <div class="theme-card">
                            <span class="matiere-tag"><?php echo htmlspecialchars($matiere); ?></span>
                            <h3 class="card-titre"><?php echo htmlspecialchars($item['theme']); ?></h3>
                            <button class="btn-primary">Explorer</button>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div id="map-container">
            <div id="svg-wrapper" style="width:100%; height:100%;"></div>
        </div>

        <?php include './transition.php'; ?>

<script>
    const mapData = <?php echo $jsonMap; ?>;
    const CONFIG = {
        width: <?php echo $largeurMer; ?>,
        height: <?php echo $hauteurMer; ?>,
        colors: { 
            LAGOON: "#2be0ce", 
            SAND: "#f8ea84", 
            JUNGLE: "#4d9c3bff", 
            MOUNTAIN: "#1b4332", 
            STROKE: "#001219",
            WAVE: "rgba(255, 255, 255, 0.3)"
        }
    };

    function createPath(cx, cy, radius, complexity, chaos, seedOffset, tension) {
        const points = [];
        const step = (Math.PI * 2) / complexity;
        for (let i = 0; i < complexity; i++) {
            const angle = i * step;
            let noise = Math.sin((i + seedOffset) * 4.5) * 0.5 + Math.cos((i + seedOffset * 2) * 2.2) * 0.5;
            let finalChaos = tension > 0.3 ? chaos * 0.3 : chaos; 
            const r = radius + (noise * finalChaos); 
            points.push({ x: cx + Math.cos(angle) * r, y: cy + Math.sin(angle) * r });
        }
        let d = `M ${points[0].x},${points[0].y}`;
        for (let i = 0; i < points.length; i++) {
            const p0 = points[i];
            const p1 = points[(i + 1) % points.length];
            const pPrev = points[(i - 1 + points.length) % points.length];
            const pNext = points[(i + 2) % points.length];
            const cp1x = p0.x + (p1.x - pPrev.x) * tension; 
            const cp1y = p0.y + (p1.y - pPrev.y) * tension;
            const cp2x = p1.x - (pNext.x - p0.x) * tension;
            const cp2y = p1.y - (pNext.y - p0.y) * tension;
            d += ` C ${cp1x},${cp1y} ${cp2x},${cp2y} ${p1.x},${p1.y}`;
        }
        return d + " Z";
    }

    function generateWaves(count) {
        let wavesSVG = "";
        const d = "M-20,0 Q0,15 20,0 T60,0 M0,20 Q20,30 40,20"; 
        
        for(let i = 0; i < count; i++) {
            let wx, wy, validPosition = false;
            let attempts = 0;
            while (!validPosition && attempts < 20) {
                wx = Math.random() * CONFIG.width;
                wy = Math.random() * CONFIG.height;
                validPosition = true;
                for (let item of mapData) {
                    const dx = wx - item.main.x;
                    const dy = wy - item.main.y;
                    const dist = Math.sqrt(dx*dx + dy*dy);
                    if (dist < 250) {
                        validPosition = false;
                        break;
                    }
                }
                attempts++;
            }
            if (validPosition) {
                const rot = (Math.random() * 20) - 10;
                const scale = 0.8 + Math.random() * 0.4;
                wavesSVG += `<path d="${d}" 
                    transform="translate(${wx}, ${wy}) scale(${scale}) rotate(${rot})" 
                    fill="none" stroke="${CONFIG.colors.WAVE}" stroke-width="5" stroke-linecap="round" opacity="0.6" />`;
            }
        }
        return wavesSVG;
    }

    function generatePalmTree(x, y, scale = 1) {
        const s = scale * (1.8 + Math.random() * 1.9);
        const flip = Math.random() > 0.5 ? 1 : -1;
        const rotation = (Math.random() * 25) - 12;

        return `
        <g transform="translate(${x}, ${y}) scale(${s})">
            <g transform="rotate(${rotation}) scale(${flip}, 1)">
                <!-- Tronc courbé avec segments -->
                <path d="M-3.5,0 Q-4,-12 -2,-24 Q0,-36 2,-40 Q3,-36 3.5,-24 Q4,-12 3,0 Z" 
                      fill="#D4A24C" stroke="#5D4022" stroke-width="1.2"/>
                <!-- Segments du tronc -->
                <path d="M-3.2,-4 Q0,-5.5 2.8,-4" fill="none" stroke="#B8863C" stroke-width="1.5"/>
                <path d="M-3.5,-9 Q0,-11 3.2,-9" fill="none" stroke="#B8863C" stroke-width="1.5"/>
                <path d="M-3.2,-14 Q0,-16 3,-14" fill="none" stroke="#B8863C" stroke-width="1.5"/>
                <path d="M-2.5,-19 Q0,-21 2.5,-19" fill="none" stroke="#B8863C" stroke-width="1.3"/>
                <path d="M-1.8,-24 Q0,-26 2,-24" fill="none" stroke="#B8863C" stroke-width="1.2"/>
                <path d="M-1,-29 Q0.5,-31 1.5,-29" fill="none" stroke="#B8863C" stroke-width="1"/>
                <path d="M-0.3,-34 Q1,-35.5 1.2,-34" fill="none" stroke="#B8863C" stroke-width="0.8"/>

                <!-- Feuillage -->
                <g transform="translate(1.5, -40)">
                    
                    <!-- Palme arrière gauche -->
                    <path d="M0,0 C-6,-2 -12,-2 -22,4
                             C-18,2 -16,3 -13,1 C-10,3 -8,2 -5,0 C-3,1 -1,0 0,0 Z" 
                          fill="#3EA63E" stroke="#2D7D2D" stroke-width="1.2" stroke-linejoin="round"/>
                    
                    <!-- Palme arrière droite -->
                    <path d="M0,0 C6,-2 12,-2 22,4
                             C18,2 16,3 13,1 C10,3 8,2 5,0 C3,1 1,0 0,0 Z" 
                          fill="#3EA63E" stroke="#2D7D2D" stroke-width="1.2" stroke-linejoin="round"/>
                    
                    <!-- Grande palme gauche tombante -->
                    <path d="M0,0 C-4,2 -10,6 -24,14
                             C-20,12 -18,14 -14,11 C-11,14 -9,12 -6,9 C-4,11 -2,8 0,0 Z" 
                          fill="#4CBD4C" stroke="#2D7D2D" stroke-width="1.2" stroke-linejoin="round"/>
                    
                    <!-- Grande palme droite tombante -->
                    <path d="M0,0 C4,2 10,6 24,14
                             C20,12 18,14 14,11 C11,14 9,12 6,9 C4,11 2,8 0,0 Z" 
                          fill="#4CBD4C" stroke="#2D7D2D" stroke-width="1.2" stroke-linejoin="round"/>
                    
                    <!-- Palme gauche moyenne -->
                    <path d="M0,0 C-5,0 -12,2 -20,8
                             C-16,6 -14,8 -11,5 C-8,8 -6,6 -4,3 C-2,5 -1,2 0,0 Z" 
                          fill="#5DC55D" stroke="#2D7D2D" stroke-width="1.2" stroke-linejoin="round"/>
                    
                    <!-- Palme droite moyenne -->
                    <path d="M0,0 C5,0 12,2 20,8
                             C16,6 14,8 11,5 C8,8 6,6 4,3 C2,5 1,2 0,0 Z" 
                          fill="#5DC55D" stroke="#2D7D2D" stroke-width="1.2" stroke-linejoin="round"/>
                    
                    <!-- Palme gauche haute -->
                    <path d="M0,0 C-4,-3 -10,-4 -18,-2
                             C-14,-3 -12,-1 -9,-3 C-6,-1 -4,-2 -2,-1 C-1,-1 0,0 0,0 Z" 
                          fill="#6EE46E" stroke="#2D7D2D" stroke-width="1.2" stroke-linejoin="round"/>
                    
                    <!-- Palme droite haute -->
                    <path d="M0,0 C4,-3 10,-4 18,-2
                             C14,-3 12,-1 9,-3 C6,-1 4,-2 2,-1 C1,-1 0,0 0,0 Z" 
                          fill="#6EE46E" stroke="#2D7D2D" stroke-width="1.2" stroke-linejoin="round"/>
                    
                    <!-- Palme centrale haute -->
                    <path d="M0,0 C-1,-4 -1,-8 0,-14
                             C-2,-10 -3,-8 -2,-6 C-3,-4 -2,-2 0,0
                             M0,0 C1,-4 1,-8 0,-14
                             C2,-10 3,-8 2,-6 C3,-4 2,-2 0,0 Z" 
                          fill="#7DF27D" stroke="#2D7D2D" stroke-width="1.2" stroke-linejoin="round"/>
                    
                    <!-- Noix de coco -->
                    <circle cx="-2.5" cy="3" r="2.8" fill="#8B5A2B" stroke="#5D3A1A" stroke-width="1"/>
                    <circle cx="2" cy="2.5" r="2.5" fill="#A0673C" stroke="#5D3A1A" stroke-width="1"/>
                    <circle cx="0" cy="4.5" r="2.3" fill="#7A4A22" stroke="#5D3A1A" stroke-width="1"/>
                </g>
            </g>
        </g>`;
    }

function renderWorld() {
    let svgContent = "";

    // 1. GÉNÉRATION DES VAGUES DE FOND
    const waveCount = 150; 
    svgContent += generateWaves(waveCount);

    // 2. GÉNÉRATION DES ARCHIPELS
    mapData.forEach(item => {
        let localLagoon = "", localSand = "", localJungle = "", localDecor = "", localFoam = "";
        const chaos = 25;

        item.archipel.forEach(isl => {
            // Calcul des tracés
            localLagoon += createPath(isl.x, isl.y, isl.size * 2.5, 8, chaos, isl.seed, 0.35);
            localSand += createPath(isl.x, isl.y, isl.size * 1.1 + 18, 10, chaos, isl.seed + 10, 0.2);
            localJungle += createPath(isl.x, isl.y, isl.size * 1.1, 10, chaos * 1.1, isl.seed + 20, 0.18);
            
            // ÉCUME : Tracé légèrement plus large que le sable pour l'animation
            localFoam += createPath(isl.x, isl.y, isl.size * 1.2 + 22, 10, chaos, isl.seed + 10, 0.2);

            let thisDecor = "";
            const isAtoll = isl.size > 50 && (isl.seed % 10 > 3);
            
            if (isAtoll) {
                const hole = createPath(isl.x, isl.y, isl.size * 0.5, 8, 5, isl.seed + 30, 0.2);
                thisDecor += `<path d="${hole}" fill="${CONFIG.colors.LAGOON}" stroke="${CONFIG.colors.STROKE}" stroke-width="2"/>`;
            } else {
                const mount = createPath(isl.x, isl.y, isl.size * 0.4, 7, 5, isl.seed + 40, 0.2);
                thisDecor += `<path d="${mount}" fill="${CONFIG.colors.MOUNTAIN}" opacity="0.6"/>`;
            }

            // AJOUT DES PALMIERS (uniquement sur la zone jungle)
            const nbPalmiers = Math.floor(Math.random() * 2) + 1; // Entre 1 et 2 palmiers
            for (let p = 0; p < nbPalmiers; p++) {
                const angle = Math.random() * Math.PI * 2;
                let dist;
                if (isAtoll) {
                    // Force les palmiers sur la couronne verte de l'atoll
                    dist = isl.size * (0.65 + Math.random() * 0.25);
                } else {
                    dist = Math.random() * (isl.size * 0.85);
                }
                const px = isl.x + Math.cos(angle) * dist;
                const py = isl.y + Math.sin(angle) * dist;
                const scaleBase = isl.size / 65; 
                thisDecor += generatePalmTree(px, py, scaleBase);
            }
            localDecor += `<g>${thisDecor}</g>`;
        });

        const clickAction = `handleClick(event, '${item.info.url}')`;

    svgContent += `
            <g class="archipel-group" onclick="${clickAction}">
                <g filter="url(#goo)"><path d="${localLagoon}" fill="${CONFIG.colors.LAGOON}" opacity="0.85"/></g>
                
            <g filter="blur(5px)">
                <path d="${localFoam}" fill="white">
                    <animate 
                        attributeName="opacity" 
                        values="0; 0.2; 1; 1; 0" 
                        keyTimes="0; 0.3; 0.8; 0.9; 1"
                        dur="6s" 
                        repeatCount="indefinite" />
                    
                    <animateTransform 
                        attributeName="transform" 
                        type="translate" 
                        values="-30,-30; -5,-5; 2,2" 
                        keyTimes="0; 0.8; 1"
                        dur="6s" 
                        repeatCount="indefinite" />
                </path>
            </g>

                <g filter="url(#goo)" transform="translate(6, 6)">
                    <path d="${localSand}" fill="white" opacity="0.5" />
                </g>
                <g filter="url(#goo)"><path d="${localSand}" fill="${CONFIG.colors.SAND}" /></g>
                <g filter="url(#goo)"><path d="${localJungle}" fill="${CONFIG.colors.JUNGLE}" /></g>
                <g>${localDecor}</g>
                <text x="${item.main.x}" y="${item.main.y}" class="island-label">${item.info.title}</text>
            </g>
        `;
    });

    const svgHTML = `
        <svg id="world-map" viewBox="0 0 ${CONFIG.width} ${CONFIG.height}" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <filter id="goo">
                    <feGaussianBlur in="SourceGraphic" stdDeviation="15" result="blur" />
                    <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo" />
                    <feComposite in="SourceGraphic" in2="goo" operator="atop"/>
                </filter>
                <filter id="dropShadow" x="-20%" y="-20%" width="140%" height="140%">
                    <feGaussianBlur in="SourceAlpha" stdDeviation="10"/>
                    <feOffset dx="10" dy="15" result="offsetblur"/>
                    <feComponentTransfer><feFuncA type="linear" slope="0.5"/></feComponentTransfer>
                    <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>
                </filter>
            </defs>
            ${svgContent}
        </svg>
    `;
    document.getElementById('svg-wrapper').innerHTML = svgHTML;
}

    function handleClick(event, url) {
        event.preventDefault();
        if (typeof lancerTransition === 'function') {
            const dummyLink = document.createElement('a');
            dummyLink.href = url;
            lancerTransition(event, dummyLink);
        } else {
            window.location.href = url;
        }
    }
    
    document.getElementById('svg-wrapper').style.opacity = 0;
    setTimeout(() => {
        renderWorld();
        document.getElementById('svg-wrapper').style.transition = "opacity 1s ease";
        document.getElementById('svg-wrapper').style.opacity = 1;
    }, 100);
    
</script>
    <?php endif; ?>

</body>
</html>