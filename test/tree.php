<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Univers - Mode Carte</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; font-family: 'Fredoka', sans-serif; background-color: #f8ea84; overflow: hidden; }
        
        #map-container {
            width: 100vw; height: 100vh;
            background: radial-gradient(circle, #00bcc5 0%, #51b2b5 100%);
            display: flex; justify-content: center; align-items: center;
        }

        .island-label {
            font-family: 'Fredoka', sans-serif;
            fill: #ffffff;
            font-weight: 700;
            text-anchor: middle;
            dominant-baseline: middle;
            font-size: 45px;
            pointer-events: none;
            stroke: #002233;
            stroke-width: 6px;
            paint-order: stroke fill;
            user-select: none; 
        }
        
        .archipel-group { cursor: pointer; transition: filter 0.2s; }
        .archipel-group:hover { filter: brightness(1.15); }
        .archipel-group:hover .island-label { fill: #f8ea84; stroke: #000; }
    </style>
</head>
<body>

    <div id="map-container">
        <div id="svg-wrapper" style="width:100%; height:100%;"></div>
    </div>

<script>
    // --- DONNÉES DE TEST ---
    const mapData = [
        {
            info: { url: "#", title: "Variables PHP" },
            main: { x: 800, y: 500, size: 85, seed: 123 },
            archipel: [
                { x: 800, y: 500, size: 85, seed: 123 },
                { x: 920, y: 550, size: 45, seed: 456 },
                { x: 750, y: 620, size: 40, seed: 789 }
            ]
        },
        {
            info: { url: "#", title: "Boucles & Itérations" },
            main: { x: 1800, y: 1200, size: 80, seed: 321 },
            archipel: [
                { x: 1800, y: 1200, size: 80, seed: 321 },
                { x: 1950, y: 1250, size: 55, seed: 654 },
                { x: 1700, y: 1300, size: 35, seed: 987 }
            ]
        }
    ];

    const CONFIG = {
        width: 3000,
        height: 2000,
        colors: { 
            LAGOON: "#2be0ce", 
            SAND: "#f8ea84", 
            JUNGLE: "#3b9926", 
            MOUNTAIN: "#1b4332", 
            STROKE: "#001219",
            WAVE: "rgba(255, 255, 255, 0.3)"
        }
    };

    // --- FONCTIONS DE DESSIN ---

    function generatePalmTree(x, y, scale = 1) {
        const s = scale * 3; 
        const flip = Math.random() > 0.5 ? 1 : -1;
        const rotation = (Math.random() * 20) - 10;
        return `
        <g transform="translate(${x}, ${y}) scale(${s})">
            <g transform="rotate(${rotation}) scale(${flip}, 1)">
                <path d="M-2,0 Q-5,-15 2,-40 L5,-40 Q0,-15 4,0 Z" fill="#5d4037" />
                <g transform="translate(2, -40)" fill="#2d6a4f">
                    <path d="M0,0 Q-15,-5 -25,10 Q-15,5 -5,5 Z" />
                    <path d="M-12,0 Q-25,5 -35,20 Q-20,10 -10,8 Z" />
                    <path d="M0,0 Q15,-5 25,10 Q15,5 5,5 Z" />
                    <path d="M12,0 Q25,5 35,20 Q20,10 10,8 Z" />
                    <path d="M-5,-2 Q0,-15 5,-2 Z" />
                </g>
            </g>
        </g>`;
    }

    function generateRock(x, y, scale = 1) {
        const s = scale * 2;
        const rotation = Math.random() * 360;
        return `
        <g transform="translate(${x}, ${y}) scale(${s}) rotate(${rotation})">
            <path d="M-5,0 Q-8,-10 0,-12 Q8,-10 5,0 Z" fill="#7f8c8d" />
            <path d="M-2,-3 Q0,-6 2,-3" stroke="#bdc3c7" fill="none" stroke-width="0.5" opacity="0.5" />
        </g>`;
    }

    function generateBush(x, y, scale = 1) {
        const s = scale * 1.5;
        return `
        <g transform="translate(${x}, ${y}) scale(${s})">
            <path d="M0,0 Q-2,-10 -5,-12 M0,0 Q0,-12 0,-15 M0,0 Q2,-10 5,-12" 
                stroke="#1e5631" stroke-width="2" fill="none" stroke-linecap="round" />
        </g>`;
    }

    function createPath(cx, cy, radius, complexity, chaos, seedOffset, tension) {
        const points = [];
        const step = (Math.PI * 2) / complexity;
        for (let i = 0; i < complexity; i++) {
            const angle = i * step;
            let noise = Math.sin((i + seedOffset) * 4.5) * 0.5 + Math.cos((i + seedOffset * 2) * 2.2) * 0.5;
            const r = radius + (noise * chaos); 
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
            let wx = Math.random() * CONFIG.width;
            let wy = Math.random() * CONFIG.height;
            const scale = 0.8 + Math.random() * 0.4;
            wavesSVG += `<path d="${d}" transform="translate(${wx}, ${wy}) scale(${scale})" fill="none" stroke="${CONFIG.colors.WAVE}" stroke-width="5" stroke-linecap="round" opacity="0.6" />`;
        }
        return wavesSVG;
    }

    // --- MOTEUR DE RENDU ---
function renderWorld() {
        let svgContent = "";
        svgContent += generateWaves(80);

        mapData.forEach(item => {
            let localLagoon = "", localSand = "", localSandShadow = "", localJungle = "", localDecor = "";
            const chaos = 25;

            item.archipel.forEach(isl => {
                localLagoon += createPath(isl.x, isl.y, isl.size * 2.2, 8, chaos, isl.seed, 0.35);
                
                // --- SABLE ET SA FINE BORDURE ---
                // Le sable original est à size * 1.1 + 18
                localSand += createPath(isl.x, isl.y, isl.size * 1.1 + 18, 10, chaos, isl.seed + 10, 0.2);
                
                // Bordure blanche subtile : Rayon légèrement plus grand (+21)
                // Décalage réduit à 3px pour un effet discret
                let shadowPath = createPath(isl.x, isl.y, isl.size * 1.1 + 25, 10, chaos, isl.seed + 10, 0.2);
                localSandShadow += `<g transform="translate(3, 3)"><path d="${shadowPath}" fill="white" opacity="0.8" /></g>`;

                localJungle += createPath(isl.x, isl.y, isl.size * 1.1, 10, chaos * 1.1, isl.seed + 20, 0.18);
                
                // ... (Le reste de la boucle reste identique) ...
                let thisDecor = "";
                const isAtoll = isl.size > 50 && (isl.seed % 10 > 3);
                
                if (isAtoll) {
                    const hole = createPath(isl.x, isl.y, isl.size * 0.5, 8, 5, isl.seed + 30, 0.2);
                    thisDecor += `<path d="${hole}" fill="${CONFIG.colors.LAGOON}" stroke="${CONFIG.colors.STROKE}" stroke-width="2"/>`;
                } else {
                    const mount = createPath(isl.x, isl.y, isl.size * 0.4, 7, 5, isl.seed + 40, 0.2);
                    thisDecor += `<path d="${mount}" fill="${CONFIG.colors.MOUNTAIN}" opacity="0.6"/>`;
                }

                if (!isAtoll) {
                    let rocksLayer = "";
                    let vegetationLayer = "";
                    const nbRocks = Math.floor(isl.size / 50);
                    for (let r = 0; r < nbRocks; r++) {
                        const angle = ((isl.seed + r) * 77) * (Math.PI / 180);
                        const dist = isl.size * (0.9 + (Math.random() * 0.2)); 
                        rx = isl.x + Math.cos(angle) * dist;
                        ry = isl.y + Math.sin(angle) * dist;
                        rocksLayer += generateRock(rx, ry, 0.4 + (Math.random() * 0.4));
                    }
                    const nbPalmiers = Math.floor(isl.size / 40); 
                    for (let p = 0; p < nbPalmiers; p++) {
                        const angle = ((isl.seed + p) * 137.5) * (Math.PI / 180); 
                        const dist = (isl.size * 0.4) + (p * 2); 
                        px = isl.x + Math.cos(angle) * dist;
                        py = isl.y + Math.sin(angle) * dist;
                        vegetationLayer += generatePalmTree(px, py, 0.6 + (Math.random() * 0.4));
                    }
                    const nbBushes = Math.floor(isl.size / 8);
                    for (let b = 0; b < nbBushes; b++) {
                        const offset = (isl.seed + b) * 999;
                        const bx = isl.x + (Math.sin(offset) * (isl.size * 0.55));
                        const by = isl.y + (Math.cos(offset) * (isl.size * 0.55));
                        vegetationLayer += generateBush(bx, by, 0.5 + (Math.random() * 0.5));
                    }
                    thisDecor = `<g class="rocks">${rocksLayer}</g><g class="vegetation">${vegetationLayer}</g>`;
                }
                localDecor += `<g>${thisDecor}</g>`;
            });

            svgContent += `
                <g class="archipel-group">
                    <g filter="url(#goo)"><path d="${localLagoon}" fill="${CONFIG.colors.LAGOON}" opacity="0.85"/></g>
                    <g filter="url(#goo)">${localSandShadow}</g>
                    <g filter="url(#goo)"><path d="${localSand}" fill="${CONFIG.colors.SAND}" /></g>
                    <g filter="url(#goo)"><path d="${localJungle}" fill="${CONFIG.colors.JUNGLE}" /></g>
                    <g>${localDecor}</g>
                    <text x="${item.main.x}" y="${item.main.y}" class="island-label">${item.info.title}</text>
                </g>
            `;
        });
        // ...

        const svgHTML = `
            <svg id="world-map" viewBox="0 0 ${CONFIG.width} ${CONFIG.height}" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <filter id="goo">
                        <feGaussianBlur in="SourceGraphic" stdDeviation="15" result="blur" />
                        <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo" />
                        <feComposite in="SourceGraphic" in2="goo" operator="atop"/>
                    </filter>
                </defs>
                ${svgContent}
            </svg>
        `;
        document.getElementById('svg-wrapper').innerHTML = svgHTML;
    }

    renderWorld();
</script>
</body>
</html>