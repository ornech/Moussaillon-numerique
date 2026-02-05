<style>
    #transition-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100vw; height: 100vh;
        background: #6bc4ef;
        z-index: 10000;
        display: none; 
        overflow: hidden;
    }

    /* --- AJOUT : Préparation de la montée (ne touche pas au contenu) --- */
    .sea-container, .wave-premier {
        transform: translateY(100%); /* On les place en bas */
        transition: transform 0.8s ease-out; /* Animation de marée */
    }

    .transition-text {
        position: absolute;
        top: 15%; width: 100%;
        text-align: center;
        font-family: 'Fredoka', sans-serif;
        color: white; font-size: 3rem;
        text-shadow: 0 4px 8px rgba(0,0,0,0.3);
        z-index: 10005;
        /* AJOUT : Le texte aussi monte doucement */
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.6s ease-out;
    }

    /* --- AJOUT : Déclencheur de la montée --- */
    .is-active .sea-container, 
    .is-active .wave-premier {
        transform: translateY(0);
    }
    .is-active .transition-text {
        opacity: 1;
        transform: translateY(0);
    }

    /* --- TES RÉGLAGES (NON MODIFIÉS) --- */
    .sea-container {
        position: absolute;
        bottom: 0; left: 0;
        width: 100%;
        height: 45%;
        z-index: 10001;
    }

    .wave-layer {
        position: absolute;
        left: 0;
        width: 400%;
        height: 100vh;
        background-repeat: repeat-x;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 120' preserveAspectRatio='none'%3E%3Cpath d='M0,30 C150,0 150,60 300,30 C450,0 450,60 600,30 C750,0 750,60 900,30 C1050,0 1050,60 1200,30 V120 H0 Z' fill='%232680d4'/%3E%3C/svg%3E");
    }

    .wave-back { top: -40px; z-index: 5; background-size: 5% 60px; filter: brightness(1.3); animation: moveWave 20s linear infinite; }
    .wave-mid { top: -10px; z-index: 15; background-size: 8% 70px; filter: brightness(1.1); animation: moveWave 12s linear infinite reverse; }
    .wave-front { top: 25px; z-index: 30; background-size: 12% 90px; filter: brightness(0.9); animation: moveWave 7s linear infinite; }

    .wave-premier {
        position: fixed;
        bottom: 0; left: 0;
        width: 100vw; height: 35%;
        background-color: #2273bf;
        z-index: 10001;
    }

    @keyframes moveWave { 0% { transform: translateX(0); } 100% { transform: translateX(-25%); } }

    #transition-ship {
        position: absolute;
        bottom: 42%; left: -350px;
        width: 250px; z-index: 20;
        transition: none;
    }

    .ship-sailing {
        animation: 
            sailAcross 5s forwards ease-in-out, 
            floating 1.5s ease-in-out infinite !important;
    }

    .ship-sway { animation: floating 1.5s ease-in-out infinite; }

    @keyframes sailAcross { 0% { left: -350px; } 100% { left: 130vw; } }

    @keyframes floating {
        0%, 100% { transform: translateY(0) rotate(6deg); }
        50% { transform: translateY(-0px) rotate(-6deg); }
    }

    /* Conteneur des nuages */
.clouds-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 40%; /* On limite les nuages au haut de l'écran */
    z-index: 10002;
    pointer-events: none;
    opacity: 0;
    transition: opacity 1s ease-in;
}

/* Apparition quand l'overlay est actif */
.is-active .clouds-container {
    opacity: 1;
}

.cloud {
    position: absolute;
    fill: white;
    filter: drop-shadow(0 5px 15px rgba(0,0,0,0.1));
    animation: drift linear infinite;
}

/* Différentes tailles et vitesses */
.cloud-1 { width: 200px; top: 10%; animation-duration: 25s; opacity: 0.9; }
.cloud-2 { width: 150px; top: 25%; animation-duration: 40s; opacity: 0.7; animation-delay: -5s; }
.cloud-3 { width: 250px; top: 5%; animation-duration: 35s; opacity: 0.8; animation-delay: -15s; }

@keyframes drift {
    from { transform: translateX(-300px); }
    to { transform: translateX(100vw); }
}
</style>

<div id="transition-overlay">
    <div class="transition-text">On lève l'ancre, moussaillon...</div>

    <div class="clouds-container">
        <svg class="cloud cloud-1" viewBox="0 0 240 120" xmlns="http://www.w3.org/2000/svg">
            <path d="M50,100 Q20,100 20,70 Q20,40 50,40 Q50,10 80,10 Q110,10 120,30 Q140,10 170,10 Q210,10 210,50 Q230,50 230,80 Q230,110 200,110 Z" />
        </svg>
        
        <svg class="cloud cloud-2" viewBox="0 0 240 120" xmlns="http://www.w3.org/2000/svg">
            <path d="M50,100 Q20,100 20,70 Q20,40 50,40 Q50,10 80,10 Q110,10 120,30 Q140,10 170,10 Q210,10 210,50 Q230,50 230,80 Q230,110 200,110 Z" />
        </svg>

        <svg class="cloud cloud-3" viewBox="0 0 240 120" xmlns="http://www.w3.org/2000/svg">
            <path d="M50,100 Q20,100 20,70 Q20,40 50,40 Q50,10 80,10 Q110,10 120,30 Q140,10 170,10 Q210,10 210,50 Q230,50 230,80 Q230,110 200,110 Z" />
        </svg>
    </div>

    <img id="transition-ship" src="../../assets/img/ui/transition-bateau.png" alt="Navire">
    
    <div class="sea-container">    
        <div class="wave-layer wave-back"></div>
        <div class="wave-layer wave-mid"></div>
        <div class="wave-layer wave-front"></div>
    </div>
    <div class="wave-premier"></div>
</div>

<script>
window.lancerTransition = function(event, element) {
    const destination = element.href;
    if (!destination || destination.includes('#')) return true;

    event.preventDefault();
    const overlay = document.getElementById('transition-overlay');
    const ship = document.getElementById('transition-ship');

    // 1. Affichage de l'overlay (vide au début car mer en bas)
    overlay.style.display = 'block';
    
    // 2. Déclenchement immédiat de la marée montante
    setTimeout(() => {
        overlay.classList.add('is-active');
    }, 10);

    // 3. On attend que la mer soit montée (800ms) avant de lancer le bateau
    setTimeout(() => {
        ship.classList.add('ship-sailing');
        ship.classList.add('ship-sway');
    }, 800);

    // 4. Redirection (allongée à 5s pour laisser le temps à la montée + voyage)
    setTimeout(() => {
        window.location.href = destination;
    }, 5200);
 
    return false;
};
</script>