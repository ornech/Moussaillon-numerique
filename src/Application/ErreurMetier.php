<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Application;

/**
 * Erreur dont le message est destiné à l'utilisateur (validation, règle métier).
 * Les autres exceptions restent techniques et ne sont jamais affichées.
 */
final class ErreurMetier extends \DomainException
{
}
