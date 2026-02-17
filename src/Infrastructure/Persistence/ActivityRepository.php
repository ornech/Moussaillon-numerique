<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Infrastructure\Persistence;

use PDO;

final class ActivityRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id, bool $validatedOnly = false): ?array
    {
        $sql = 'SELECT * FROM activities WHERE id = ?';
        if ($validatedOnly) {
            $sql .= ' AND is_validated = 1';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByTheme(string $theme): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title, theme, distance FROM activities WHERE theme = ? AND is_validated = 1 ORDER BY id ASC'
        );
        $stmt->execute([$theme]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMatieres(): array
    {
        $stmt = $this->pdo->query(
            'SELECT DISTINCT matiere FROM activities WHERE matiere IS NOT NULL ORDER BY matiere ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getThemesByMatiere(string $matiere): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT theme, MIN(id) as first_id, MIN(distance) as distance
             FROM activities WHERE matiere = ? AND is_validated = 1
             GROUP BY theme ORDER BY distance ASC'
        );
        $stmt->execute([$matiere]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMatiereEntries(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT MIN(id) as id, matiere, MIN(distance) as distance
             FROM activities WHERE is_validated = 1
             GROUP BY matiere ORDER BY matiere ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listAll(?string $matiereFilter = null): array
    {
        $sql = 'SELECT * FROM activities';
        $params = [];
        if ($matiereFilter !== null && $matiereFilter !== '') {
            $sql .= ' WHERE matiere = ?';
            $params[] = $matiereFilter;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE activities SET title=?, theme=?, matiere=?, content_html=?, distance=?, quiz_json=? WHERE id=?'
        );
        $stmt->execute([
            $data['title'] ?? '',
            $data['theme'] ?? '',
            $data['matiere'] ?? '',
            $data['content_html'] ?? '',
            (int) ($data['distance'] ?? 0),
            $data['quiz_json'] ?? '[]',
            $id,
        ]);
    }

    public function getStatsByMatiere(): array
    {
        $stmt = $this->pdo->query(
            'SELECT matiere, COUNT(DISTINCT theme) as nb_themes, COUNT(id) as nb_islands FROM activities GROUP BY matiere'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
