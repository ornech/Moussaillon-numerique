<?php
/**
 * API D'AUTHENTIFICATION UNIFIÉE - LES MOUSSAILLONS NUMÉRIQUES
 * Gère la bifurcation Éleve (PIN) / Staff (Password) selon le Point 4 du CDC.
 */
require_once '../includes/config.php';
header('Content-Type: application/json');

// 1. COLLECTE DES DONNÉES
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$role     = isset($_POST['role'])     ? $_POST['role']      : 'eleve';
$mode     = isset($_POST['mode'])     ? $_POST['mode']      : 'login';

// On aiguille la lecture selon le champ envoyé (pin vs pass)
$password_input = ($role === 'eleve') 
    ? (isset($_POST['pin']) ? trim($_POST['pin']) : '') 
    : (isset($_POST['pass']) ? trim($_POST['pass']) : '');

// 2. VÉRIFICATION DE LA COMPLÉTUDE
if (empty($username) || empty($password_input)) {
    echo json_encode(['success' => false, 'message' => 'Identifiants incomplets.']);
    exit;
}

try {
    if ($role === 'eleve') {
        // --- LOGIQUE ÉLÈVE (Point 4.2 : Bifurcation) ---
        if ($mode === 'create') {
            // Création automatique
            $hash = password_hash($password_input, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, pin_hash, current_ship_id) VALUES (?, ?, 1)");
            $stmt->execute([$username, $hash]);
            $userId = $pdo->lastInsertId();
            $range  = 1; // Portée initiale (Point 2)
        } else {
            // Connexion existante
            $sql = "SELECT u.id, u.pin_hash, s.range_level 
                    FROM users u 
                    JOIN ships s ON u.current_ship_id = s.id 
                    WHERE u.username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
            $user_found = $stmt->fetch();

            if (!$user_found || !password_verify($password_input, $user_found['pin_hash'])) {
                echo json_encode(['success' => false, 'message' => 'Pseudo ou PIN incorrect.']);
                exit;
            }
            $userId = $user_found['id'];
            $range  = $user_found['range_level'];
        }
    } else {
        // --- LOGIQUE STAFF : ENSEIGNANT / ADMIN (Point 5 & 6) ---
        $table = ($role === 'admin') ? 'staff' : 'teachers';
        
        $stmt = $pdo->prepare("SELECT id, pin_hash FROM $table WHERE username = ?");
        $stmt->execute([$username]);
        $staff_found = $stmt->fetch();

        if (!$staff_found || !password_verify($password_input, $staff_found['pin_hash'])) {
            echo json_encode(['success' => false, 'message' => 'Identifiants incorrects.']);
            exit;
        }

        $userId = $staff_found['id'];
        $range  = 999; // Accès total pour l'amirauté et les profs
    }

    // 3. INITIALISATION DE LA SESSION (Source de vérité)
    $_SESSION['user_id']    = $userId;
    $_SESSION['username']   = $username;
    $_SESSION['role']       = $role;
    $_SESSION['user_range'] = $range;

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Erreur Auth : " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur technique de liaison.']);
}