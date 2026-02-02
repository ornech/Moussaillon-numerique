<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if (empty($username) || empty($email) || strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Données invalides (Pass: 6 caract. min).']);
    exit;
}

try {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO teachers (username, email, pin_hash) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $hash]);

    echo json_encode(['success' => true, 'message' => 'Compte créé ! Vous pouvez vous connecter.']);

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'message' => 'Ce pseudo ou email est déjà utilisé.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du compte.']);
    }
}