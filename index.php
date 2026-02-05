<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'includes/config.php';
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? 'eleve';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Rôles autorisés (évite les valeurs "fantômes")
    $allowedRoles = ['eleve', 'teacher', 'admin'];
    if (!in_array($role, $allowedRoles, true)) {
        $message = "Accès refusé : rôle invalide.";
    } elseif ($username === '' || $password === '') {
        $message = "Identifiant ou secret manquant.";
    } else {
        try {
            if ($role === 'eleve') {
                $stmt = $pdo->prepare("SELECT id, pin_hash FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    // Création automatique si le moussaillon n'existe pas
                    if (preg_match('/^\d{4}$/', $password)) {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $ins = $pdo->prepare("INSERT INTO users (username, pin_hash, current_ship_id) VALUES (?, ?, 1)");
                        $ins->execute([$username, $hash]);
                        creerSession((int)$pdo->lastInsertId(), 'eleve', $username);
                    } else {
                        $message = "Le PIN doit contenir exactement 4 chiffres.";
                    }
                } else {
                    if (password_verify($password, $user['pin_hash'])) {
                        creerSession((int)$user['id'], 'eleve', $username);
                    } else {
                        $message = "Code PIN incorrect.";
                    }
                }
            } else {
                // Staff : teacher/admin
                $stmt = $pdo->prepare("SELECT id, pin_hash, role FROM staff WHERE username = ?");
                $stmt->execute([$username]);
                $staff = $stmt->fetch(PDO::FETCH_ASSOC);

                // Ne pas "nettoyer" un hash ; on vérifie et on compare le rôle demandé
                if ($staff && password_verify($password, $staff['pin_hash']) && $staff['role'] === $role) {
                    creerSession((int)$staff['id'], $staff['role'], $username);
                } else {
                    $message = "Accès refusé : identifiants ou rang incorrects.";
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "La capitainerie est injoignable (Erreur BDD).";
        }
    }
}

/**
 * Gère la session et redirige proprement
 */
function creerSession(int $id, string $role, string $name): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    $_SESSION['role'] = $role;
    $_SESSION['username'] = $name;

    // Redirection stricte par rôle (le bug était ici)
    switch ($role) {
        case 'eleve':
            $url = 'modules/eleve/port.php';
            break;
        case 'teacher':
            $url = 'modules/teacher/port.php'; // à créer/adapter
            break;
        case 'admin':
            $url = 'modules/admin/amirante.php'; // vérifier le nom du fichier
            break;
        default:
            // rôle impossible si validation faite plus haut
            $url = 'index.php';
    }

    if (!headers_sent()) {
        header("Location: $url");
        exit;
    }

    // Fallback si des headers ont déjà été envoyés : on force l'arrêt + redirection HTML
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '">';
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Les Moussaillons Numériques</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hidden { display: none; }
        .portal-box { max-width: 400px; margin: 80px auto; text-align: center; padding: 40px; background: white; border-radius: 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); font-family: sans-serif; }
        .btn-choice { width: 100%; padding: 18px; margin: 10px 0; border-radius: 15px; border: none; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-choice:hover { opacity: 0.9; transform: scale(1.02); }
        input { width: 100%; padding: 15px; margin: 10px 0; border-radius: 10px; border: 1px solid #ddd; box-sizing: border-box; }
        .error { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="portal-box">
        <?php if ($message) echo "<div class='error'>⚠️ $message</div>"; ?>

        <div id="selector">
            <img src="assets/img/ui/logo.png" alt="Logo" style="width: 250px; margin-bottom: 20px;">
            <h1>Bienvenue à Bord</h1>
            <button class="btn-choice" style="background:#3b82f6;color:white;" onclick="openForm('form-eleve')">Moussaillon (Élève)</button>
            <button class="btn-choice" style="background:#1e293b;color:white;" onclick="openForm('form-teacher')">Officier (Enseignant)</button>
            <p onclick="openForm('form-admin')" style="color:#cbd5e1; cursor:pointer; font-size:0.75rem; margin-top:30px;">Accès Amirauté 🔒</p>
        </div>

        <form id="form-eleve" class="hidden" method="POST">
            <h2>Espace Élève</h2>
            <input type="hidden" name="role" value="eleve">
            <input type="text" name="username" placeholder="Pseudo" required autofocus>
            <input type="password" name="password" placeholder="PIN (4 chiffres)" required>
            <button type="submit" class="btn-choice" style="background:#10b981;color:white;">ENTRER</button>
            <p onclick="location.reload()" style="cursor:pointer; text-decoration:underline; font-size: 0.9rem;">Retour</p>
        </form>

        <form id="form-teacher" class="hidden" method="POST">
            <h2>Espace Enseignant</h2>
            <input type="hidden" name="role" value="teacher">
            <input type="text" name="username" placeholder="Identifiant Officier" required autofocus>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit" class="btn-choice" style="background:#10b981;color:white;">CONNEXION</button>
            <p onclick="location.reload()" style="cursor:pointer; text-decoration:underline; font-size: 0.9rem;">Retour</p>
        </form>

        <form id="form-admin" class="hidden" method="POST">
            <h2>Amirauté</h2>
            <input type="hidden" name="role" value="admin">
            <input type="text" name="username" placeholder="Login Admin" required autofocus>
            <input type="password" name="password" placeholder="Clé de sécurité" required>
            <button type="submit" class="btn-choice" style="background:#10b981;color:white;">ACCÈS COMMANDEMENT</button>
            <p onclick="location.reload()" style="cursor:pointer; text-decoration:underline; font-size: 0.9rem;">Retour</p>
        </form>
    </div>

    <script>
        function openForm(id) {
            document.getElementById('selector').classList.add('hidden');
            document.getElementById(id).classList.remove('hidden');
        }
    </script>
</body>
</html>