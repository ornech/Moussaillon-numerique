<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Application\Auth;

/**
 * Création de session après authentification et redirection selon le rôle.
 */
final class SessionCreator
{
    /**
     * Régénère la session, enregistre l'utilisateur et redirige selon le rôle.
     */
    public static function create(int $id, string $role, string $name): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['role'] = $role;
        $_SESSION['username'] = $name;

        $url = self::getRedirectUrl($role);

        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }

        echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
        exit;
    }

    private static function getRedirectUrl(string $role): string
    {
        switch ($role) {
            case 'eleve':
                return 'modules/eleve/port.php';
            case 'teacher':
                return 'modules/enseignant/dashboard.php';
            case 'admin':
                return 'modules/admin/amirante.php';
            default:
                return 'index.php';
        }
    }
}
