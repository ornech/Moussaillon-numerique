<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Application\Port;

use PDO;

/**
 * Achat d'un navire au chantier naval.
 * Le débit est atomique : impossible de passer sous zéro, même avec deux clics simultanés.
 */
final class AchatNavire
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function acheter(int $userId, int $shipId): array
    {
        $stmt = $this->pdo->prepare('SELECT name, price FROM ships WHERE id = ?');
        $stmt->execute([$shipId]);
        $navire = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$navire) {
            return ['success' => false, 'message' => 'Navire introuvable.'];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE users SET points = points - ?, current_ship_id = ?
             WHERE id = ? AND points >= ? AND current_ship_id <> ?'
        );
        $stmt->execute([$navire['price'], $shipId, $userId, $navire['price'], $shipId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Points insuffisants pour acheter ce navire.'];
        }

        return ['success' => true, 'message' => 'Félicitations ! Vous avez acheté le ' . $navire['name'] . ' !'];
    }
}
