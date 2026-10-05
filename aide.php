<?php
/**
 * AIDE - LES MOUSSAILLONS NUMÉRIQUES
 * Page publique expliquant le fonctionnement des équipages aux parents et aux enseignants.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

use Jf\Moussaillons\Application\Auth\LoginThrottle;
use Jf\Moussaillons\Application\Auth\InscriptionAdulte;

require_once __DIR__ . '/bootstrap.php';

// Retour vers l'espace de la personne connectée, sinon vers l'accueil
$retour = match ($_SESSION['role'] ?? null) {
    'teacher', 'admin' => 'modules/enseignant/dashboard.php',
    'eleve'            => 'modules/eleve/port.php',
    default            => 'index.php',
};
$libelleRetour = $retour === 'index.php' ? "Page d'accueil" : 'Retour à mon espace';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comment ça marche ? - Les Moussaillons Numériques</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        html, body { height: auto !important; overflow-y: auto !important; overflow-x: hidden; }
        body { background: #f0f4f8; margin: 0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; line-height: 1.6; display: block !important; }
        .aide { max-width: 860px; margin: 0 auto; padding: 20px 16px 60px; text-align: left; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
        .topbar img { height: 50px; }
        .topbar a { padding: 10px 16px; border-radius: 12px; background: #10b981; color: white; font-weight: bold; text-decoration: none; }
        h1 { font-size: 1.9rem; margin: 25px 0 5px; }
        h2 { font-size: 1.4rem; margin: 0 0 15px; }
        .intro { font-size: 1.1rem; color: #475569; }
        .choix { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 25px 0; }
        .choix a { display: block; padding: 22px; border-radius: 20px; background: white; text-align: center; text-decoration: none; color: inherit; font-weight: bold; font-size: 1.15rem; box-shadow: var(--shadow-relief); border: 3px solid transparent; transition: 0.2s; }
        .choix a:hover { border-color: #10b981; transform: translateY(-3px); }
        .choix span { display: block; font-size: 2.5rem; }
        .panel { background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px; box-shadow: var(--shadow-relief); scroll-margin-top: 20px; }
        .schema { display: grid; grid-template-columns: 1fr auto 1fr auto 1fr; align-items: center; gap: 10px; text-align: center; margin-top: 10px; }
        .schema div.bloc { background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 15px; padding: 15px 10px; }
        .schema div.bloc span { display: block; font-size: 2rem; }
        .schema .fleche { font-size: 1.5rem; color: #94a3b8; }
        .code { font-family: monospace; font-weight: bold; letter-spacing: 2px; background: #ecfdf5; color: #065f46; padding: 2px 8px; border-radius: 6px; }
        ol.etapes { counter-reset: etape; list-style: none; padding: 0; margin: 0; }
        ol.etapes li { counter-increment: etape; position: relative; padding: 0 0 18px 55px; }
        ol.etapes li::before { content: counter(etape); position: absolute; left: 0; top: 0; width: 38px; height: 38px; border-radius: 50%; background: #10b981; color: white; font-weight: bold; display: flex; align-items: center; justify-content: center; }
        .astuce { background: #fffbeb; border-left: 5px solid #f59e0b; padding: 12px 15px; border-radius: 10px; margin-top: 10px; }
        details { border-bottom: 1px solid #f1f5f9; padding: 12px 0; }
        details summary { cursor: pointer; font-weight: bold; }
        details p { margin: 8px 0 0; color: #475569; }
        @media (max-width: 600px) {
            .choix { grid-template-columns: 1fr; }
            .schema { grid-template-columns: 1fr; }
            .schema .fleche { transform: rotate(90deg); }
            h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="aide">
    <div class="topbar">
        <img src="assets/img/ui/logo.png" alt="Les Moussaillons Numériques">
        <a href="<?= $retour ?>"><?= $libelleRetour ?></a>
    </div>

    <h1>Comment ça marche ?</h1>
    <p class="intro">
        Les enfants révisent en réussissant des missions (des quiz courts). Chaque bonne réponse rapporte des pièces,
        qui permettent d'acheter des navires capables d'aller plus loin. Vous, l'adulte, suivez leurs progrès.
    </p>

    <div class="choix">
        <a href="#parents"><span>👨‍👩‍👧</span>Je suis parent</a>
        <a href="#enseignants"><span>🧑‍🏫</span>Je suis enseignant</a>
    </div>

    <section class="panel">
        <h2>⚓ Le principe : l'équipage</h2>
        <p>Chaque adulte regroupe ses enfants ou ses élèves dans un <strong>équipage</strong>. Un équipage a un <strong>code</strong> (par exemple <span class="code">K7MQ4P</span>) et un <strong>lien</strong> à partager.</p>
        <div class="schema">
            <div class="bloc"><span>🧑</span>Vous<br><small>espace adulte</small></div>
            <div class="fleche">➜</div>
            <div class="bloc"><span>🏴‍☠️</span>Votre équipage<br><small>un code, un lien</small></div>
            <div class="fleche">➜</div>
            <div class="bloc"><span>👦👧</span>Les moussaillons<br><small>pseudo + code secret</small></div>
        </div>
        <p>Vous ne voyez que <strong>vos</strong> équipages, et personne d'autre ne voit vos enfants ou vos élèves.</p>
    </section>

    <section class="panel" id="parents">
        <h2>👨‍👩‍👧 Pour les parents</h2>
        <ol class="etapes">
            <li><strong>Créez votre espace</strong> en ouvrant le lien d'invitation que vous avez reçu. Choisissez « Parent » et donnez un nom à votre équipage (« Les pirates de la maison »…).</li>
            <li><strong>Embarquez votre enfant</strong>, au choix :
                <br>– dans votre espace, tapez son pseudo et un code secret de 4 chiffres, puis cliquez sur « Ajouter » ;
                <br>– ou envoyez-lui le lien de l'équipage (bouton « Copier le lien ») : il choisit lui-même son pseudo et son code.</li>
            <li><strong>C'est parti !</strong> L'enfant se connecte sur la page d'accueil avec son pseudo et son code secret. Ses résultats apparaissent dans votre espace.</li>
        </ol>
        <div class="astuce">💡 Plusieurs enfants ? Mettez-les dans le même équipage. Un copain veut jouer ? Ses parents peuvent avoir leur propre espace : demandez une invitation pour eux à l'administrateur du site.</div>
    </section>

    <section class="panel" id="enseignants">
        <h2>🧑‍🏫 Pour les enseignants</h2>
        <ol class="etapes">
            <li><strong>Créez votre espace</strong> avec le lien d'invitation reçu. Choisissez « Enseignant » et nommez l'équipage d'après votre classe (« CM1 B »).</li>
            <li><strong>Embarquez la classe</strong> : écrivez le code de l'équipage au tableau ou déposez son lien sur l'ENT. À sa première connexion, chaque élève choisit son pseudo et son code secret.
                Pour les plus jeunes, vous pouvez aussi créer les comptes vous-même depuis votre espace.</li>
            <li><strong>Suivez la classe</strong> : taux de réussite, activité récente et détail par élève, matière par matière.</li>
        </ol>
        <div class="astuce">💡 Plusieurs classes ? Créez un équipage par classe (« ➕ Nouvel équipage ») : chacun a son propre code.</div>
        <div class="astuce">🔒 Utilisez des pseudos sans nom de famille. Avant un usage en classe, parlez-en à votre direction : le délégué à la protection des données (DPO) de votre académie peut vous accompagner.</div>
    </section>

    <section class="panel">
        <h2>🧒 Ce que l'enfant doit retenir</h2>
        <p>Sur la page d'accueil : <strong>son pseudo</strong> et <strong>son code secret à 4 chiffres</strong>. C'est tout.
            La toute première fois seulement, il lui faut le lien ou le code de l'équipage.</p>
    </section>

    <section class="panel">
        <h2>❓ Questions fréquentes</h2>
        <details>
            <summary>Mon enfant a oublié son code secret</summary>
            <p>Dans votre espace, cliquez sur la clé 🔑 à côté de son pseudo et choisissez un nouveau code.</p>
        </details>
        <details>
            <summary>« Ce pseudo est déjà pris »</summary>
            <p>Les pseudos sont uniques sur tout le site. Ajoutez un chiffre ou une initiale (« Lucas7 », « LucasB »).</p>
        </details>
        <details>
            <summary>« Trop d'essais »</summary>
            <p>Après 5 codes erronés, la connexion à ce pseudo est bloquée <?= LoginThrottle::minutesAttente() ?> minutes. Cela empêche de deviner le code d'un camarade.</p>
        </details>
        <details>
            <summary>Mon lien d'invitation ne fonctionne plus</summary>
            <p>Un lien d'invitation ne sert qu'une fois et reste valable <?= InscriptionAdulte::validiteJours() ?> jours. Demandez-en un nouveau à la personne qui vous l'a envoyé.</p>
        </details>
        <details>
            <summary>Qui voit les résultats de mon enfant ?</summary>
            <p>Uniquement l'adulte responsable de son équipage, et l'administrateur du site.</p>
        </details>
        <details>
            <summary>Quelles données sont enregistrées ?</summary>
            <p>Pour l'enfant : son pseudo, son code secret (enregistré sous forme chiffrée), ses résultats, ses pièces et son navire. Aucun nom, e-mail ou photo n'est demandé.
               Pour l'adulte : son identifiant, son mot de passe (chiffré) et, s'il le souhaite, son e-mail.</p>
        </details>
        <details>
            <summary>Comment supprimer un compte ?</summary>
            <p>Contactez l'administrateur du site, qui supprimera le compte et toutes les données associées.</p>
        </details>
    </section>
</div>
</body>
</html>
