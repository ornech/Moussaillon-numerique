<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Application\Session;

/**
 * Vérifie si la portée navale (session) permet d'accoster sur une île à la distance donnée.
 */
final class PorteeChecker
{
    /**
     * Vérifie si le navire actuel de l'élève (portée en session) permet d'accoster.
     * La portée est stockée en session lors de la connexion.
     */
    public static function verifier(int $distanceActivite): bool
    {
        if (!isset($_SESSION['user_range'])) {
            return false;
        }
        return (int) $_SESSION['user_range'] >= $distanceActivite;
    }
}
