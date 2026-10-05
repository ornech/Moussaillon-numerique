<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Application\Quiz;

use Jf\Moussaillons\Application\Progression\EligibilitePoints;
use PDO;

/**
 * Logique serveur du quiz : tirage des questions, correction, crédit des points.
 * Les solutions ne quittent jamais le serveur avant que l'élève ait répondu.
 */
final class QuizService
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * Prépare une série de questions pour une activité validée.
     * Applique le tirage aléatoire si nb_tirage est défini, mélange les options.
     *
     * @return list<array{question: string, options: list<string>, solution: string, aide: ?string}>|null
     *         null si l'activité n'existe pas, n'est pas validée ou n'a pas de question exploitable.
     */
    public function preparer(int $activityId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT quiz_json, nb_tirage FROM activities WHERE id = ? AND is_validated = 1'
        );
        $stmt->execute([$activityId]);
        $activite = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$activite) {
            return null;
        }

        $brut = json_decode($activite['quiz_json'] ?? '[]', true);
        $questions = array_values(array_filter(
            array_map([self::class, 'normaliser'], is_array($brut) ? $brut : [])
        ));
        if ($questions === []) {
            return null;
        }

        // Série aléatoire : nb_tirage questions tirées au hasard dans la banque
        $nbTirage = (int) ($activite['nb_tirage'] ?? 0);
        if ($nbTirage > 0 && count($questions) > $nbTirage) {
            shuffle($questions);
            $questions = array_slice($questions, 0, $nbTirage);
        }

        foreach ($questions as &$q) {
            shuffle($q['options']);
        }

        return $questions;
    }

    public function estCorrect(array $question, string $choix): bool
    {
        return $choix === $question['solution'];
    }

    /**
     * Enregistre la tentative et crédite les points (règle : pas de points si le dernier essai était un sans-faute).
     *
     * @return array{points_gagnes: int, new_total: int}
     */
    public function crediter(int $userId, int $activityId, int $score, int $total): array
    {
        $this->pdo->beginTransaction();
        try {
            $eligible = (new EligibilitePoints($this->pdo))->estEligible($userId, $activityId);
            $pointsGagnes = $eligible ? $score : 0;

            if ($pointsGagnes > 0) {
                $this->pdo->prepare('UPDATE users SET points = points + ? WHERE id = ?')
                    ->execute([$pointsGagnes, $userId]);
            }

            $this->pdo->prepare(
                'INSERT INTO history (user_id, activity_id, score_max, nbr_question, date_completion)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE score_max = VALUES(score_max),
                     nbr_question = VALUES(nbr_question), date_completion = NOW()'
            )->execute([$userId, $activityId, $score, $total]);

            $stmt = $this->pdo->prepare('SELECT points FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $nouveauTotal = (int) $stmt->fetchColumn();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['points_gagnes' => $pointsGagnes, 'new_total' => $nouveauTotal];
    }

    /**
     * Ramène une question du JSON à un format unique.
     * La bonne réponse peut être dans "reponse" ou "answer", sous forme de texte ou d'index.
     */
    private static function normaliser(mixed $q): ?array
    {
        if (!is_array($q) || !isset($q['question'], $q['options']) || !is_array($q['options'])) {
            return null;
        }

        $options = array_values(array_map('strval', $q['options']));
        $solution = $q['reponse'] ?? $q['answer'] ?? null;
        if (is_int($solution) && isset($options[$solution])) {
            $solution = $options[$solution];
        }
        if ($solution === null || !in_array((string) $solution, $options, true)) {
            return null;
        }

        return [
            'question' => (string) $q['question'],
            'options'  => $options,
            'solution' => (string) $solution,
            'aide'     => isset($q['aide']) ? (string) $q['aide'] : null,
        ];
    }
}
