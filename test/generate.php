<?php
// --- CONFIGURATION PHP ---
$nombreDeCartes = 6; // Nombre d'archipels à générer
$tailleCarte = 300;  // Taille d'une case SVG (carré)

// Limites pour qu'une île ne sorte pas de sa petite case
$bordureSecurite = 60;
$safeMin = $bordureSecurite;
$safeMax = $tailleCarte - $bordureSecurite;

$tousLesArchipels = [];

// BOUCLE PRINCIPALE : On génère N archipels
for ($k = 0; $k < $nombreDeCartes; $k++) {
    
    $nbIlots = rand(3, 6); // Nombre d'îlots par carte
    $ilots = [];

    // 1. Îlot central de cet archipel
    $ilots[] = [
        'x' => $tailleCarte / 2,
        'y' => $tailleCarte / 2,
        'size' => rand(35, 45), // Taille adaptée à la petite case
        'seed' => mt_rand(0, 1000)
    ];

    // 2. Génération des satellites (Chain Linking)
    for ($i = 1; $i < $nbIlots; $i++) {
        $parent = $ilots[array_rand($ilots)];
        
        $angle = (mt_rand() / mt_getrandmax()) * 2 * M_PI;
        $dist = 40 + mt_rand(0, 30); // Distance réduite pour tenir dans 300px

        $newX = $parent['x'] + cos($angle) * $dist;
        $newY = $parent['y'] + sin($angle) * $dist;

        // Clamp pour rester dans la case
        $newX = max($safeMin, min($newX, $safeMax));
        $newY = max($safeMin, min($newY, $safeMax));

        $ilots[] = [
            'x' => $newX,
            'y' => $newY,
            'size' => rand(15, 30),
            'seed' => mt_rand(0, 1000)
        ];
    }
    
    // On ajoute cet archipel à la collection
    $tousLesArchipels[] = $ilots;
}

$jsonDonnees = json_encode($tousLesArchipels);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Collection d'Îles</title>
    <style>
        body {
            background-color: #f0f4f8;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 40px;
        }

        h1 { text-align: center; color: #2c3e50; margin-bottom: 40px; }

        /* GRILLE DE CARTES */
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            background: linear-gradient(135deg, #48cae4 0%, #0077b6 100%); /* Fond Océan */
            border-radius: 20px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
            aspect-ratio: 1 / 1; /* Carré parfait */
            overflow: hidden;
            position: relative;
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 30px rgba(0,0,0,0.25);
        }

        .card-title {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            color: white;
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            pointer-events: none;
            z-index: 10;
        }

        svg {
            width: 100%;
            height: 100%;
            /* Ombre portée interne à l'archipel */
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.3));
        }

        /* SVG cachée pour les définitions de filtres */
        #shared-defs { position: absolute; width: 0; height: 0; }
    </style>
</head>
<body>

    <h1>Exploration de l'Archipel</h1>

    <svg id="shared-defs">
        <defs>
            <filter id="goo">
                <feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur" />
                <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo" />
                <feComposite in="SourceGraphic" in2="goo" operator="atop"/>
            </filter>
        </defs>
    </svg>

    <div class="grid-container" id="grid">
        </div>

<script>
    // Données PHP injectées
    const collectionData = <?php echo $jsonDonnees; ?>;
    const PALETTE = { LAGOON: "#4cc9f0", SAND: "#fefae0", JUNGLE: "#2d6a4f", MOUNTAIN: "#1b4332", STROKE: "#001219" };

    // Fonction de dessin Bézier (Identique à avant)
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

    // Fonction pour générer le SVG d'un seul archipel
    function generateSVG(islands) {
        let pathsLagoon = "", pathsSand = "", pathsJungle = "", decor = "";
        
        islands.forEach((isl) => {
            const chaos = 15; // Chaos réduit car les cartes sont petites (300px)
            
            // 1. Lagon (Lisse)
            pathsLagoon += createPath(isl.x, isl.y, isl.size * 2.3, 8, chaos, isl.seed, 0.35);
            // 2. Sable
            pathsSand += createPath(isl.x, isl.y, isl.size * 1.1 + 15, 10, chaos, isl.seed + 10, 0.2);
            // 3. Jungle
            pathsJungle += createPath(isl.x, isl.y, isl.size * 1.1, 10, chaos * 1.1, isl.seed + 20, 0.18);
            
            // 4. Décor
            let thisDecor = "";
            const isAtoll = isl.size > 25 && (isl.seed % 10 > 4);
            if (isAtoll) {
                 const hole = createPath(isl.x, isl.y, isl.size * 0.5, 8, 5, isl.seed + 30, 0.2);
                 thisDecor += `<path d="${hole}" fill="${PALETTE.LAGOON}" stroke="${PALETTE.STROKE}" stroke-width="1.5"/>`;
            } else {
                 const mount = createPath(isl.x, isl.y, isl.size * 0.4, 7, 5, isl.seed + 40, 0.2);
                 thisDecor += `<path d="${mount}" fill="${PALETTE.MOUNTAIN}" opacity="0.6"/>`;
            }
            decor += `<g>${thisDecor}</g>`;
        });

        // Note: viewBox="0 0 300 300" correspond à la variable $tailleCarte PHP
        return `
            <svg viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg">
                <g filter="url(#goo)" opacity="0.8"><path d="${pathsLagoon}" fill="${PALETTE.LAGOON}" /></g>
                <g filter="url(#goo)"><path d="${pathsSand}" fill="${PALETTE.SAND}" /></g>
                <g filter="url(#goo)"><path d="${pathsJungle}" fill="${PALETTE.JUNGLE}" /></g>
                <g>${decor}</g>
            </svg>
        `;
    }

    // BOUCLE D'AFFICHAGE JS
    const container = document.getElementById('grid');
    
    collectionData.forEach((archipel, index) => {
        // Création de la carte HTML
        const card = document.createElement('div');
        card.className = 'card';
        
        // Injection du SVG généré
        card.innerHTML = generateSVG(archipel);
        
        // Ajout d'un titre
        const title = document.createElement('div');
        title.className = 'card-title';
        title.innerText = "Zone " + (index + 1);
        card.appendChild(title);
        
        container.appendChild(card);
    });

</script>
</body>
</html>