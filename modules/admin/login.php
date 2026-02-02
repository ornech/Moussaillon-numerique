<?php 
require_once '../../includes/config.php'; 

// Sécurité : Si déjà admin, on redirige vers le tableau de bord admin
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: amirante.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion Amirauté - Les Moussaillons</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #1e293b; } /* Fond plus sombre pour l'admin */
        .card-admin { border-bottom: 8px solid #0f172a; max-width: 400px; }
        h1 { font-size: 1.5rem; margin-bottom: 20px; color: #1e293b; }
        .badge-admin { background: #1e293b; color: white; padding: 5px 10px; border-radius: 5px; font-size: 0.7rem; }
    </style>
</head>
<body>

    <div class="card card-admin">
        <div style="font-size: 40px;">🔒</div>
        <h1>Accès Amirauté <span class="badge-admin">ADMIN</span></h1>
        
        <form id="admin-form" onsubmit="authAdmin(event)">
            <input type="text" id="adm-user" placeholder="Identifiant administrateur" required>
            <input type="password" id="adm-pass" placeholder="Mot de passe" required style="margin-top: 15px;">
            
            <button type="submit" id="btn-adm" style="margin-top: 25px; width: 100%; background: #1e293b; box-shadow: 0 5px 0 #0f172a;">
                VÉRIFIER LES ACCÈS
            </button>
        </form>

        <a href="../../index.php" style="margin-top: 20px; font-size: 0.8rem; color: #64748b; text-decoration: none;">
            ← Retour au portail public
        </a>
    </div>

    <script>
        async function authAdmin(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-adm');
    
    // On prépare les données proprement
    const params = new URLSearchParams();
    params.append('username', document.getElementById('adm-user').value);
    params.append('pass', document.getElementById('adm-pass').value);
    params.append('role', 'admin');

    btn.disabled = true;
    btn.innerText = "SÉCURISATION...";

    try {
        // VÉRIFIE BIEN : deux 'g' à loggin.php si c'est ton nom de fichier
        const resp = await fetch('../../api/login.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params.toString()
        });

        // Si le serveur répond (même une erreur 404), on sort du bloc catch
        if (!resp.ok) {
            throw new Error("Fichier introuvable sur le serveur (404)");
        }

        const data = await resp.json();

        if (data.success) {
            window.location.href = 'amirante.php';
        } else {
            alert(data.message);
            btn.disabled = false;
            btn.innerText = "VÉRIFIER LES ACCÈS";
        }
    } catch (err) {
        // C'est ici que tu arrives actuellement
        alert("Erreur de liaison : " + err.message);
        console.error("Détail de l'erreur :", err);
        btn.disabled = false;
        btn.innerText = "VÉRIFIER LES ACCÈS";
    }
}
    </script>
</body>
</html>