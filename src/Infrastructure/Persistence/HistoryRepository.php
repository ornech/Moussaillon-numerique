<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Infrastructure\Persistence;

use PDO;

final class HistoryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findScore(int $userId, int $activityId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT score_max, nbr_question FROM history WHERE user_id = ? AND activity_id = ?'
        );
        $stmt->execute([$userId, $activityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getScoreMax(int $userId, int $activityId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT score_max FROM history WHERE user_id = ? AND activity_id = ?'
        );
        $stmt->execute([$userId, $activityId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? (int) $row['score_max'] : null;
    }

    public function upsert(int $userId, int $activityId, int $scoreMax, int $nbrQuestion): void
    {
        $existing = $this->findScore($userId, $activityId);
        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE history SET score_max = ?, nbr_question = ?, date_completion = NOW() WHERE user_id = ? AND activity_id = ?'
            );
            $stmt->execute([$scoreMax, $nbrQuestion, $userId, $activityId]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO history (user_id, activity_id, score_max, nbr_question, date_completion) VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$userId, $activityId, $scoreMax, $nbrQuestion]);
        }
    }

    public function getAverageScore(): ?float
    {
        $v = $this->pdo->query('SELECT ROUND(AVG(score_max), 1) FROM history')->fetchColumn();
        return $v !== false ? (float) $v : null;
    }

    public function countLast24h(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM history WHERE date_completion > NOW() - INTERVAL 1 DAY"
        )->fetchColumn();
    }

    public function getByUserWithActivity(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT h.score_max, h.nbr_question, h.date_completion, a.title, a.matiere
             FROM history h JOIN activities a ON h.activity_id = a.id
             WHERE h.user_id = ? ORDER BY h.date_completion DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
