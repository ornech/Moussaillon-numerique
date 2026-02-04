<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Générateur Archipel V4 - Final</title>
    <style>
        :root {
            --bg-color: #0077b6;
            --ui-bg: rgba(255, 255, 255, 0.95);
            --accent: #8e44ad;
        }

        body {
            margin: 0; display: flex; height: 100vh; font-family: 'Segoe UI', sans-serif;
            background-color: var(--bg-color); overflow: hidden;
        }

        #controls {
            width: 300px; background: var(--ui-bg); padding: 25px;
            box-shadow: 5px 0 20px rgba(0,0,0,0.2); z-index: 10;
            display: flex; flex-direction: column; gap: 20px; overflow-y: auto;
        }

        h2 { margin: 0 0 10px 0; color: var(--accent); }
        .group { border-bottom: 1px solid #ccc; padding-bottom: 15px; }
        label { display: block; font-size: 0.9rem; font-weight: 600; color: #555; margin-bottom: 5px; }
        input[type="range"] { width: 100%; cursor: pointer; }
        
        button {
            padding: 15px; background: var(--accent); color: white; border: none;
            border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1.1rem;
        }
        button:hover { background: #9b59b6; }

        #viewport {
            flex-grow: 1; display: flex; justify-content: center; align-items: center;
            background-image: radial-gradient(circle at center, #48cae4 0%, #0077b6 100%);
        }

        svg {
            /* On force le SVG à ne jamais dépasser la vue, mais on gère le contenu via viewBox */
            max-width: 95%; max-height: 95%;
            filter: drop-shadow(0 15px 25px rgba(0,0,0,0.3));
        }
    </style>
</head>
<body>

<div id="controls">
    <h2>Génération V4.0</h2>
    
    <div class="group">
        <label>Nombre d'extensions</label>
        <input type="range" id="param-count" min="2" max="8" value="4">
    </div>

    <div class="group">
        <label>Étalement (Distance)</label>
        <input type="range" id="param-spread" min="50" max="120" value="80">
    </div>

    <div class="group">
        <label>Chaos des Terres (Vert/Jaune)</label>
        <input type="range" id="param-chaos" min="10" max="60" value="30">
    </div>
    
    <div class="group">
        <label>Taille globale</label>
        <input type="range" id="param-size" min="0.7" max="1.2" step="0.1" value="1.0">
    </div>

    <button onclick="generateMap()">🎲 Générer</button>
</div>

<div id="viewport">
    <div id="canvas-container"></div>
</div>

<script>
    const PALETTE = {
        LAGOON: "#4cc9f0", 
        SAND: "#fefae0",
        JUNGLE: "#2d6a4f",
        MOUNTAIN: "#1b4332",
        STROKE: "#001219"
    };

    /**
     * Génère un chemin SVG organique
     * @param {number} tension - Plus c'est haut, plus la courbe est large/molle (pour le bleu)
     */
    function createOrganicPath(cx, cy, radius, complexity, chaos, seedOffset, tension = 0.2) {
        const points = [];
        const step = (Math.PI * 2) / complexity;

        for (let i = 0; i < complexity; i++) {
            const angle = i * step;
            // Bruit organique
            const noise = Math.sin((i + seedOffset) * 4.5) * 0.5 + Math.cos((i + seedOffset * 2) * 2.2) * 0.5;
            
            // Pour le bleu, on veut minimiser l'impact du chaos pour éviter les "creux"
            // Si tension est élevée (mode lagon), on réduit drastiquement l'effet du noise négatif
            let finalChaos = chaos;
            if (tension > 0.3) { 
                // Mode Lagon : on empêche le rayon de trop diminuer (pas de creux)
                finalChaos = chaos * 0.3; 
            }
            
            const r = radius + (noise * finalChaos); 
            points.push({
                x: cx + Math.cos(angle) * r,
                y: cy + Math.sin(angle) * r
            });
        }

        let d = `M ${points[0].x},${points[0].y}`;
        for (let i = 0; i < points.length; i++) {
            const p0 = points[i];
            const p1 = points[(i + 1) % points.length];
            const pPrev = points[(i - 1 + points.length) % points.length];
            const pNext = points[(i + 2) % points.length];

            // Calcul des points de contrôle Bézier
            // tension modifie la courbure.
            const cp1x = p0.x + (p1.x - pPrev.x) * tension; 
            const cp1y = p0.y + (p1.y - pPrev.y) * tension;
            const cp2x = p1.x - (pNext.x - p0.x) * tension;
            const cp2y = p1.y - (pNext.y - p0.y) * tension;

            d += ` C ${cp1x},${cp1y} ${cp2x},${cp2y} ${p1.x},${p1.y}`;
        }
        return d + " Z";
    }

    function generateMap() {
        const count = parseInt(document.getElementById('param-count').value);
        const spread = parseInt(document.getElementById('param-spread').value);
        const chaos = parseInt(document.getElementById('param-chaos').value);
        const sizeMod = parseFloat(document.getElementById('param-size').value);

        // Dimensions du Canvas SVG
        const width = 1000;
        const height = 800;
        const cx = width / 2;
        const cy = height / 2;

        // Limites de sécurité (Padding de 150px autour du centre)
        // Les centres des îles ne peuvent pas dépasser cette boîte virtuelle
        const SAFE_X_MIN = 250;
        const SAFE_X_MAX = 750;
        const SAFE_Y_MIN = 200;
        const SAFE_Y_MAX = 600;

        let islands = [];
        
        // 1. Île centrale
        islands.push({ x: cx, y: cy, size: 100 * sizeMod, seed: Math.random()*100 });

        // 2. Placement des extensions (avec contrainte de bordure)
        for (let i = 0; i < count; i++) {
            const parent = islands[Math.floor(Math.random() * islands.length)];
            const angle = Math.random() * Math.PI * 2;
            const dist = (spread * 0.8) + (Math.random() * spread * 0.5);
            
            let newX = parent.x + Math.cos(angle) * dist;
            let newY = parent.y + Math.sin(angle) * dist;

            // --- CLAUSE DE SÉCURITÉ ---
            // On force les coordonnées à rester dans la zone sûre pour ne pas couper le SVG
            newX = Math.max(SAFE_X_MIN, Math.min(newX, SAFE_X_MAX));
            newY = Math.max(SAFE_Y_MIN, Math.min(newY, SAFE_Y_MAX));

            islands.push({
                x: newX,
                y: newY,
                size: (50 + Math.random() * 60) * sizeMod,
                seed: Math.random()*100
            });
        }

        // --- PRÉPARATION DES CALQUES ---
        let pathsLagoon = "";     
        let pathsSand = "";       
        let pathsJungleBase = ""; 
        let contentDecor = "";    

        islands.forEach((isl, idx) => {
            // A. LAGON (BLEU) : Paramètres très spécifiques pour éviter les creux
            // - Radius: Très large (* 2.4) pour englober le reste
            // - Complexity: Faible (8 points) pour une forme simple
            // - Chaos: Réduit dans la fonction createOrganicPath grâce au paramètre 'tension'
            // - Tension: 0.35 (élevée) pour des courbes très larges et douces
            pathsLagoon += createOrganicPath(isl.x, isl.y, isl.size * 2.4, 8, chaos, isl.seed, 0.35);
            
            // B. SABLE (JAUNE) : Chaos normal
            pathsSand += createOrganicPath(isl.x, isl.y, isl.size * 1.1 + 25, 12, chaos, isl.seed + 10, 0.2);
            
            // C. JUNGLE (VERT) : Chaos élevé pour le côté déchiqueté
            pathsJungleBase += createOrganicPath(isl.x, isl.y, isl.size * 1.1, 12, chaos * 1.1, isl.seed + 20, 0.18);
            
            // D. DÉCORS (Sans palmiers)
            let thisDecor = "";
            const isAtoll = isl.size > 80 && Math.random() > 0.5;

            if (isAtoll) {
                 // Trou d'eau interne (Lagon)
                 const hole = createOrganicPath(isl.x, isl.y, isl.size * 0.5, 8, 5, isl.seed + 30, 0.2);
                 thisDecor += `<path d="${hole}" fill="${PALETTE.LAGOON}" stroke="${PALETTE.STROKE}" stroke-width="2"/>`;
            } else {
                 // Montagne simple
                 const mount = createOrganicPath(isl.x, isl.y, isl.size * 0.4, 7, 8, isl.seed + 40, 0.2);
                 thisDecor += `<path d="${mount}" fill="${PALETTE.MOUNTAIN}" opacity="0.6"/>`;
            }
            contentDecor += `<g>${thisDecor}</g>`;
        });

        // --- RENDU SVG ---
        const svg = `
            <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <filter id="goo" color-interpolation-filters="sRGB">
                        <feGaussianBlur in="SourceGraphic" stdDeviation="15" result="blur" />
                        <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 20 -9" result="goo" />
                        <feComposite in="SourceGraphic" in2="goo" operator="atop"/>
                    </filter>
                </defs>

                <g filter="url(#goo)" opacity="0.8">
                    <path d="${pathsLagoon}" fill="${PALETTE.LAGOON}" />
                </g>

                <g filter="url(#goo)">
                    <path d="${pathsSand}" fill="${PALETTE.SAND}" />
                </g>

                <g filter="url(#goo)">
                    <path d="${pathsJungleBase}" fill="${PALETTE.JUNGLE}" />
                </g>

                <g>${contentDecor}</g>
            </svg>
        `;

        document.getElementById('canvas-container').innerHTML = svg;
    }

    window.onload = generateMap;
</script>

</body>
</html>