<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Infrastructure\Persistence;

use PDO;

final class TeacherRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, pin_hash FROM teachers WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(string $username, string $email, string $pinHash): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO teachers (username, email, pin_hash) VALUES (?, ?, ?)');
        $stmt->execute([$username, $email, $pinHash]);
        return (int) $this->pdo->lastInsertId();
    }
}
