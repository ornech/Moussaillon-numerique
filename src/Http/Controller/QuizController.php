<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Http\Controller;

use Jf\Moussaillons\Application\Quiz\QuizService;
use Jf\Moussaillons\Http\JsonResponse;
use Jf\Moussaillons\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Déroulé d'un quiz côté serveur.
 * La série en cours (questions + solutions + score) est gardée en session :
 * le navigateur ne reçoit une solution qu'après avoir répondu, et ne peut pas fixer son score.
 */
final class QuizController
{
    private const SESSION_KEY = 'quiz';

    /**
     * POST /quiz/{id}/start : tire une nouvelle série et renvoie les questions sans les solutions.
     */
    public function demarrer(Request $request, Response $response, array $args): Response
    {
        $activityId = (int) $args['id'];
        $questions = $this->service()->preparer($activityId);
        if ($questions === null) {
            return JsonResponse::error('Mission introuvable ou non validée.', 404);
        }

        $_SESSION[self::SESSION_KEY][$activityId] = [
            'questions' => $questions,
            'index'     => 0,
            'score'     => 0,
        ];

        return JsonResponse::ok($response, [
            'success'   => true,
            'questions' => array_map(
                fn (array $q) => ['question' => $q['question'], 'options' => $q['options']],
                $questions
            ),
        ]);
    }

    /**
     * POST /quiz/{id}/answer {choix} : corrige la question courante.
     * À la dernière question, enregistre la tentative et crédite les points.
     */
    public function repondre(Request $request, Response $response, array $args): Response
    {
        $activityId = (int) $args['id'];
        $partie = $_SESSION[self::SESSION_KEY][$activityId] ?? null;
        if ($partie === null) {
            return JsonResponse::error('Aucune mission en cours. Rechargez la page.', 409);
        }

        $body = (array) $request->getParsedBody();
        $choix = isset($body['choix']) ? (string) $body['choix'] : '';

        $service = $this->service();
        $question = $partie['questions'][$partie['index']];
        $correct = $service->estCorrect($question, $choix);

        $partie['index']++;
        if ($correct) {
            $partie['score']++;
        }

        $resultat = [
            'success'  => true,
            'correct'  => $correct,
            'solution' => $question['solution'],
            'aide'     => $question['aide'],
            'termine'  => false,
        ];

        $total = count($partie['questions']);
        if ($partie['index'] < $total) {
            $_SESSION[self::SESSION_KEY][$activityId] = $partie;
            return JsonResponse::ok($response, $resultat);
        }

        // Fin de série : la partie est retirée de la session avant le crédit (pas de double crédit)
        unset($_SESSION[self::SESSION_KEY][$activityId]);
        $credit = $service->crediter((int) $request->getAttribute('user_id'), $activityId, $partie['score'], $total);

        return JsonResponse::ok($response, array_merge($resultat, $credit, [
            'termine' => true,
            'score'   => $partie['score'],
            'total'   => $total,
        ]));
    }

    private function service(): QuizService
    {
        return new QuizService(Database::getInstance()->getConnection());
    }
}
