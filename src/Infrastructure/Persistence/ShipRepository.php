<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Infrastructure\Persistence;

use PDO;

final class ShipRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, img_url, price, range_level FROM ships WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPrice(int $id): ?int
    {
        $stmt = $this->pdo->prepare('SELECT price FROM ships WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? (int) $row['price'] : null;
    }

    public function getRangeLevel(int $id): ?int
    {
        $stmt = $this->pdo->prepare('SELECT range_level FROM ships WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? (int) $row['range_level'] : null;
    }

    /** Navires disponibles à l'achat (exclut le navire actuel de l'utilisateur) */
    public function getShipsExceptCurrentForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, img_url, price FROM ships WHERE id != (SELECT current_ship_id FROM users WHERE id = ?)'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
