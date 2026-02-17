<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Application\Progression;

use PDO;

/**
 * Règle du crédit unique (anti-triche) : éligibilité au gain de points pour une activité.
 */
final class EligibilitePoints
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * Vérifie si l'élève est encore éligible au gain de points pour cette activité.
     * Éligible si : pas d'historique OU score précédent strictement inférieur à 100%.
     */
    public function estEligible(int $userId, int $activityId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT score_max FROM history WHERE user_id = ? AND activity_id = ?'
        );
        $stmt->execute([$userId, $activityId]);
        $historique = $stmt->fetch(PDO::FETCH_ASSOC);

        return !$historique || (int) $historique['score_max'] < 100;
    }
}
