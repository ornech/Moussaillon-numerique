<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Infrastructure\Persistence;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.username, u.points, u.current_ship_id, s.range_level, s.name as ship_name, s.img_url
             FROM users u LEFT JOIN ships s ON u.current_ship_id = s.id WHERE u.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, pin_hash, current_ship_id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findIdAndPinHashByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, pin_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(string $username, string $pinHash, int $currentShipId = 1): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, pin_hash, current_ship_id) VALUES (?, ?, ?)'
        );
        $stmt->execute([$username, $pinHash, $currentShipId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function addPoints(int $userId, int $points): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET points = points + ? WHERE id = ?');
        $stmt->execute([$points, $userId]);
    }

    public function updateShipAndDeductPoints(int $userId, int $shipId, int $price): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET points = points - ?, current_ship_id = ? WHERE id = ?'
        );
        $stmt->execute([$price, $shipId, $userId]);
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}
