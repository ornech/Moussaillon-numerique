<style>
    #transition-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100vw; height: 100vh;
        background: linear-gradient(to bottom, #2680d4 0%, #ffffff 100%);
        z-index: 10000;
        display: none; /* Important : display none par défaut */
        flex-direction: column;
        justify-content: center;
        align-items: center;
        pointer-events: none;
    }

    #transition-ship {
        width: 250px;
        height: auto;
    }

    .transition-text {
        font-family: 'Fredoka', sans-serif;
        color: #92400e;
        font-size: 1.8rem;
        margin-top: 20px;
        font-weight: 700;
    }

    .ship-animating {
        animation: sailAway 1.6s forwards cubic-bezier(0.4, 0, 0.2, 1);
    }

    @keyframes sailAway {
        0% { transform: translateX(-20vw); opacity: 1; }
        100% { transform: translateX(120vw); opacity: 0; }
    }
</style>

<div id="transition-overlay">
    <div class="transition-scene">
        <img id="transition-ship" src="../../assets/img/ui/carte1.png" alt="Navire">
    </div>
    <div class="transition-text">Le navire lève l'ancre...</div>
</div>

<script>
// On définit la fonction globalement
window.lancerTransition = function(event, element) {
    const destination = element.href;

    // Si le lien est vide ou verrouillé, on laisse le comportement par défaut
    if (!destination || destination.includes('#') || element.classList.contains('locked')) {
        return true; 
    }

    // On bloque la navigation
    event.preventDefault();

    const overlay = document.getElementById('transition-overlay');
    const ship = document.getElementById('transition-ship');

    // On affiche l'overlay
    overlay.style.display = 'flex';
    overlay.style.pointerEvents = 'all';

    // On lance l'animation
    setTimeout(() => {
        ship.classList.add('ship-animating');
    }, 10);

    // On change de page après le délai
    setTimeout(() => {
        window.location.href = destination;
    }, 1500);

    return false;
};
</script>