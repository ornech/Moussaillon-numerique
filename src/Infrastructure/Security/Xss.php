<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Infrastructure\Security;

/**
 * Échappement des sorties pour la protection XSS.
 */
final class Xss
{
    /**
     * Échappe une chaîne pour affichage HTML (attributs ou contenu).
     */
    public static function escape(?string $data): string
    {
        if ($data === null || $data === '') {
            return '';
        }
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}
