<?php
// --- CONFIGURATION DE LA CARTE ---
$largeurMer = 3000;
$hauteurMer = 2000;
$nombreArchipels = 12; 
$distanceMinEntreArchipels = 450; // Pour éviter les chevauchements

$carteDonnees = [];

// Fonction pour calculer la distance entre deux points
function getDistance($x1, $y1, $x2, $y2) {
    return sqrt(pow($x2 - $x1, 2) + pow($y2 - $y1, 2));
}

// --- GÉNÉRATION DES POSITIONS DES ARCHIPELS ---
for ($k = 0; $k < $nombreArchipels; $k++) {
    $tries = 0;
    $validPosition = false;
    $centerX = 0;
    $centerY = 0;

    // Tentative de placement avec anti-collision
    do {
        $centerX = rand(200, $largeurMer - 200); // Marge de 200px sur les bords
        $centerY = rand(200, $hauteurMer - 200);
        
        $tooClose = false;
        foreach ($carteDonnees as $existing) {
            $mainIsland = $existing[0]; // La première île est le centre
            if (getDistance($centerX, $centerY, $mainIsland['x'], $mainIsland['y']) < $distanceMinEntreArchipels) {
                $tooClose = true;
                break;
            }
        }
        
        if (!$tooClose) {
            $validPosition = true;
        }
        $tries++;
    } while (!$validPosition && $tries < 100);

    // Si on a trouvé une place, on génère l'archipel autour de ce point
    if ($validPosition) {
        $nbIlots = rand(3, 7);
        $ilots = [];

        // 1. Île principale de cet archipel
        $ilots[] = [
            'x' => $centerX,
            'y' => $centerY,
            'size' => rand(50, 70), // Taille
            'seed' => mt_rand(0, 1000)
        ];

        // 2. Satellites (Chain Linking local)
        for ($i = 1; $i < $nbIlots; $i++) {
            $parent = $ilots[array_rand($ilots)];
            $angle = (mt_rand() / mt_getrandmax()) * 2 * M_PI;
            $dist = 60 + mt_rand(0, 60); // Distance locale

            $ilots[] = [
                'x' => $parent['x'] + cos($angle) * $dist,
                'y' => $parent['y'] + sin($angle) * $dist,
                'size' => rand(20, 45),
                'seed' => mt_rand(0, 1000)
            ];
        }
        $carteDonnees[] = $ilots;
    }
}

$jsonMap = json_encode($carteDonnees);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Carte des Mers Procédurale</title>
    <style>
        body {
            margin: 0;
            height: 100vh;
            width: 100vw;
            background-color: #0077b6; /* Fond Océan profond */
            overflow: hidden; /* On peut mettre 'auto' si on veut scroller */
            font-family: 'Segoe UI', sans-serif;
        }

        /* Conteneur de la carte (simule la table à cartes) */
        #map-container {
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: radial-gradient(circle, #0096c7 0%, #0077b6 100%);
            position: relative;
        }

        /* Grille de cartographie (Décoration) */
        .grid-lines {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 100px 100px;
            pointer-events: none;
        }

        /* L'immense SVG */
        #world-map {
            width: 100%;
            height: 100%;
            /* Conserver les proportions ou remplir ? ici on remplit au mieux */
            object-fit: contain; 
            filter: drop-shadow(0 20px 40px rgba(0,0,0,0.4));
        }
    </style>
</head>
<body>

    <div id="map-container">
        <div class="grid-lines"></div>
        <div id="svg-wrapper" style="width:95%; height:95%;"></div>
    </div>

<script>
    const mapData = <?php echo $jsonMap; ?>;
    const CONFIG = {
        width: <?php echo $largeurMer; ?>,
        height: <?php echo $hauteurMer; ?>,
        colors: { LAGOON: "#4cc9f0", SAND: "#fefae0", JUNGLE: "#2d6a4f", MOUNTAIN: "#1b4332", STROKE: "#001219" }
    };

    // Fonction de dessin Bézier (Standardisée V4)
    function createPath(cx, cy, radius, complexity, chaos, seedOffset, tension) {
        const points = [];
        const step = (Math.PI * 2) / complexity;
        for (let i = 0; i < complexity; i++) {
            const angle = i * step;
            let noise = Math.sin((i + seedOffset) * 4.5) * 0.5 + Math.cos((i + seedOffset * 2) * 2.2) * 0.5;
            // Lissage forcé si tension élevée (pour le bleu)
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

    function renderWorld() {
        // Chaînes géantes pour stocker tous les chemins de toute la carte
        let globalLagoon = "";
        let globalSand = "";
        let globalJungle = "";
        let globalDecor = "";

        // On parcourt chaque archipel
        mapData.forEach(archipel => {
            // On parcourt chaque îlot de l'archipel
            archipel.forEach(isl => {
                const chaos = 25; 
                
                // 1. LAGON (Tension haute = pas de creux)
                globalLagoon += createPath(isl.x, isl.y, isl.size * 2.2, 8, chaos, isl.seed, 0.35);
                
                // 2. SABLE (Tension moyenne)
                globalSand += createPath(isl.x, isl.y, isl.size * 1.1 + 18, 10, chaos, isl.seed + 10, 0.2);
                
                // 3. JUNGLE (Tension basse = déchiqueté)
                globalJungle += createPath(isl.x, isl.y, isl.size * 1.1, 10, chaos * 1.1, isl.seed + 20, 0.18);
                
                // 4. DÉCOR
                let thisDecor = "";
                const isAtoll = isl.size > 40 && (isl.seed % 10 > 3);
                if (isAtoll) {
                    const hole = createPath(isl.x, isl.y, isl.size * 0.5, 8, 5, isl.seed + 30, 0.2);
                    thisDecor += `<path d="${hole}" fill="${CONFIG.colors.LAGOON}" stroke="${CONFIG.colors.STROKE}" stroke-width="2"/>`;
                } else {
                    const mount = createPath(isl.x, isl.y, isl.size * 0.4, 7, 5, isl.seed + 40, 0.2);
                    thisDecor += `<path d="${mount}" fill="${CONFIG.colors.MOUNTAIN}" opacity="0.6"/>`;
                }
                globalDecor += `<g>${thisDecor}</g>`;
            });
        });

        // Construction du SVG Unique
        // viewBox utilise les dimensions définies en PHP
        const svgHTML = `
            <svg id="world-map" viewBox="0 0 ${CONFIG.width} ${CONFIG.height}" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <filter id="goo">
                        <feGaussianBlur in="SourceGraphic" stdDeviation="15" result="blur" />
                        <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo" />
                        <feComposite in="SourceGraphic" in2="goo" operator="atop"/>
                    </filter>
                </defs>

                <g filter="url(#goo)" opacity="0.8">
                    <path d="${globalLagoon}" fill="${CONFIG.colors.LAGOON}" />
                </g>

                <g filter="url(#goo)">
                    <path d="${globalSand}" fill="${CONFIG.colors.SAND}" />
                </g>

                <g filter="url(#goo)">
                    <path d="${globalJungle}" fill="${CONFIG.colors.JUNGLE}" />
                </g>

                <g>${globalDecor}</g>
            </svg>
        `;

        document.getElementById('svg-wrapper').innerHTML = svgHTML;
    }

    renderWorld();

</script>
</body>
</html>