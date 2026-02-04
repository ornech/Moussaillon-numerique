<style>
    #transition-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100vw; height: 100vh;
        background: #4facfe; /* Ciel */
        z-index: 10000;
        display: none; 
        overflow: hidden;
    }

    .sea-container {
        position: absolute;
        bottom: 0; left: 0;
        width: 100%;
        height: 45%; /* Hauteur de la mer sur l'écran */
        z-index: 10001;
    }

    /* Chaque calque est une bande opaque qui descend jusqu'au bas de l'écran */
    .wave-layer {
        position: absolute;
        left: 0;
        width: 400%; /* Pour le défilement infini */
        height: 100vh; /* Très haut pour garantir l'opacité totale en dessous */
        background-repeat: repeat-x;
        /* SVG avec un remplissage (fill) opaque */
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 120' preserveAspectRatio='none'%3E%3Cpath d='M0,30 C150,0 150,60 300,30 C450,0 450,60 600,30 C750,0 750,60 900,30 C1050,0 1050,60 1200,30 V120 H0 Z' fill='%232680d4'/%3E%3C/svg%3E");
    }

    /* CALQUE 1 (Lointain) : Fréquence maximale, le plus clair */
    .wave-back {
        top: -40px;
        z-index: 5;
        background-size: 5% 60px; /* Fréquence très haute */
        filter: brightness(1.3);
        animation: moveWave 20s linear infinite;
    }

    /* CALQUE 2 (Milieu) : Fréquence haute, vitesse moyenne */
    .wave-mid {
        top: -10px;
        z-index: 15;
        background-size: 8% 70px;
        filter: brightness(1.1);
        animation: moveWave 12s linear infinite reverse;
    }

    /* CALQUE 3 (Premier Plan) : Fréquence moyenne, le plus sombre et rapide */
    /* C'est lui qui "bouche" tout l'espace inférieur */
    .wave-front {
        top: 25px;
        z-index: 30;
        background-size: 12% 90px;
        filter: brightness(0.9);
        animation: moveWave 7s linear infinite;
    }

    .wave-premier {
    position: fixed;   /* Reste fixé en bas même si on scroll */
    bottom: 0;         /* Aligné sur le bord inférieur */
    left: 0;           /* Aligné sur le bord gauche */
    width: 100vw;      /* Largeur totale (100% de la largeur de vue) */
    height: 35%;      /* Hauteur de 40% (40% de la hauteur de vue) */
    background-color: #2273bf; /* Ta couleur bleu foncé */
    z-index: 10001;    /* Pour passer au-dessus des autres éléments */

    }

    @keyframes moveWave {
        0% { transform: translateX(0); }
        100% { transform: translateX(-25%); }
    }

    /* On anime le déplacement horizontal sur l'ID */
#transition-ship {
    position: absolute;
    bottom: 42%; 
    left: -350px;
    width: 250px;
    z-index: 20;
    /* On prépare la transition de base */
    transition: none;
}

/* La classe déclenchée par JS */
.ship-sailing {
    /* On combine la traversée ET le tangage (floating) ici */
    animation: 
        sailAcross 5s forwards ease-in-out, 
        floating 1.5s ease-in-out infinite !important;
}

/* Le tangage est appliqué directement sur l'image pour boucler */
.ship-sway {
    animation: floating 1.5s ease-in-out infinite;
}

/* Animation de traversée (Uniquement la position) */
@keyframes sailAcross {
    0% { left: -350px; }
    100% { left: 130vw; }
}

/* Animation de tangage (Translation verticale + Rotation) */
@keyframes floating {
    0%, 100% { 
        transform: translateY(0) rotate(6deg); 
    }
    50% { 
        transform: translateY(-0px) rotate(-6deg); 
    }
}

    .transition-text {
        position: absolute;
        top: 15%; width: 100%;
        text-align: center;
        font-family: 'Fredoka', sans-serif;
        color: white; font-size: 3rem;
        text-shadow: 0 4px 8px rgba(0,0,0,0.3);
        z-index: 10005;
    }
</style>

<div id="transition-overlay">
    <div class="transition-text">On lève l'ancre. moussaillon..</div>
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

    overlay.style.display = 'block';
    
    // CORRECTION : On ajoute les DEUX classes pour que ça bouge
    ship.classList.add('ship-sailing');
    ship.classList.add('ship-sway');

    setTimeout(() => {
        window.location.href = destination;
    }, 4200);
 
    return false;
};
</script>