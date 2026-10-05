<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Http;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;

/**
 * Raccourcis pour les réponses JSON de l'API.
 */
final class JsonResponse
{
    public static function ok(Response $response, array $data): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function error(string $message, int $status): Response
    {
        return self::ok(new SlimResponse(), ['success' => false, 'message' => $message])
            ->withStatus($status);
    }
}
