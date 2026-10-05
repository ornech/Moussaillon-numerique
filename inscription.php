<?php
/**
 * INSCRIPTION ADULTE - LES MOUSSAILLONS NUMÉRIQUES
 * Accessible uniquement par un lien d'invitation : inscription.php?invitation=JETON
 * Crée le compte (parent ou enseignant) et son premier équipage en une seule étape.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'includes/config.php';

use Jf\Moussaillons\Application\Auth\InscriptionAdulte;
use Jf\Moussaillons\Application\Auth\SessionCreator;
use Jf\Moussaillons\Application\ErreurMetier;
use Jf\Moussaillons\Infrastructure\Security\Csrf;

$inscription = new InscriptionAdulte($pdo);
$jeton = (string) ($_POST['invitation'] ?? $_GET['invitation'] ?? '');
$invitationValide = $jeton !== '' && $inscription->invitationValide($jeton);

$profil = $_POST['profil'] ?? 'parent';
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$nomEquipage = trim($_POST['equipage'] ?? '');
$message = '';

if ($invitationValide && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::isValid($_POST['_csrf'] ?? null)) {
        $message = "La page a expiré, réessayez.";
    } else {
        try {
            $staffId = $inscription->inscrire($jeton, $profil, $username, $_POST['password'] ?? '', $email, $nomEquipage);
            SessionCreator::create($staffId, 'teacher', $username);
        } catch (ErreurMetier $e) {
            $message = $e->getMessage();
            $invitationValide = $inscription->invitationValide($jeton);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "La capitainerie est injoignable (Erreur BDD).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer mon espace - Les Moussaillons Numériques</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .portal-box { max-width: 460px; margin: 40px auto; padding: 35px; background: white; border-radius: 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); font-family: sans-serif; box-sizing: border-box; text-align: left; }
        .portal-box h1 { text-align: center; font-size: 1.5rem; }
        label { display: block; font-weight: bold; margin-top: 15px; color: #334155; }
        label small { font-weight: normal; color: #64748b; }
        input[type=text], input[type=password], input[type=email] { width: 100%; padding: 14px; margin-top: 6px; border-radius: 10px; border: 1px solid #ddd; box-sizing: border-box; font-size: 1rem; }
        .profils { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 8px; }
        .profils input { display: none; }
        .profils label { margin: 0; padding: 18px 10px; border: 2px solid #e2e8f0; border-radius: 15px; text-align: center; cursor: pointer; font-size: 1rem; }
        .profils input:checked + label { border-color: #10b981; background: #ecfdf5; }
        .profils span { display: block; font-size: 2rem; }
        .btn-choice { width: 100%; padding: 18px; margin-top: 25px; border-radius: 15px; border: none; font-weight: bold; cursor: pointer; background: #10b981; color: white; font-size: 1rem; }
        .error { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .info { background: #f1f5f9; padding: 12px 15px; border-radius: 12px; font-size: 0.9rem; color: #475569; }
        .links { text-align: center; margin-top: 20px; font-size: 0.9rem; }
        @media (max-width: 500px) { .portal-box { margin: 16px; padding: 22px; } }
    </style>
</head>
<body>
    <div class="portal-box">
        <h1>⚓ Créer mon espace adulte</h1>

        <?php if (!$invitationValide): ?>
            <div class="error">Ce lien d'invitation n'est plus valide (déjà utilisé ou expiré).</div>
            <p class="info">Demandez un nouveau lien à la personne qui vous a invité. Si vous avez déjà créé votre compte, connectez-vous simplement.</p>
            <div class="links"><a href="index.php?espace=adulte">Se connecter</a> · <a href="aide.php">Comment ça marche ?</a></div>
        <?php else: ?>
            <p class="info">
                En une étape, vous créez votre compte et votre premier <strong>équipage</strong> : le groupe où vous retrouverez vos enfants ou vos élèves.
                <a href="aide.php" target="_blank">En savoir plus</a>
            </p>
            <?php if ($message): ?><div class="error">⚠️ <?= s($message) ?></div><?php endif; ?>

            <form method="POST">
                <input type="hidden" name="_csrf" value="<?= s(Csrf::token()) ?>">
                <input type="hidden" name="invitation" value="<?= s($jeton) ?>">

                <label>Vous êtes…</label>
                <div class="profils">
                    <input type="radio" name="profil" id="p-parent" value="parent" <?= $profil !== 'enseignant' ? 'checked' : '' ?>>
                    <label for="p-parent"><span>👨‍👩‍👧</span>Parent</label>
                    <input type="radio" name="profil" id="p-enseignant" value="enseignant" <?= $profil === 'enseignant' ? 'checked' : '' ?>>
                    <label for="p-enseignant"><span>🧑‍🏫</span>Enseignant</label>
                </div>

                <label for="username">Identifiant <small>(pour vous connecter)</small></label>
                <input type="text" id="username" name="username" value="<?= s($username) ?>" required minlength="3" maxlength="50" autocomplete="username">

                <label for="password">Mot de passe <small>(8 caractères minimum)</small></label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">

                <label for="equipage">Nom de votre équipage</label>
                <input type="text" id="equipage" name="equipage" value="<?= s($nomEquipage) ?>" required minlength="2" maxlength="60"
                       placeholder="ex. Les pirates de la maison, CM1 B…">

                <label for="email">E-mail <small>(facultatif, pour vous recontacter)</small></label>
                <input type="email" id="email" name="email" value="<?= s($email) ?>" autocomplete="email">

                <button type="submit" class="btn-choice">CRÉER MON ESPACE</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
