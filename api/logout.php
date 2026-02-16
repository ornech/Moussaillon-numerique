<?php
/**
 * DECONNEXION DU NAVIRE
 */

// 1. On rejoint la session existante
session_start();

// 2. On vide toutes les variables de session
$_SESSION = array();

// 3. Si on veut détruire complètement la session, on efface aussi le cookie de session.
// Note : cela détruit la session et pas seulement les données de session !
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. On détruit la session côté serveur
session_destroy();

// 5. Redirection vers la page de connexion (index.php ou login.php à la racine)
header("Location: ../index.php");
exit;