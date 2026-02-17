<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Infrastructure\Persistence;

use PDO;

final class StaffRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, pin_hash, role FROM staff WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn();
    }
}
