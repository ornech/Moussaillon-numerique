<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Http\Middleware;

use Jf\Moussaillons\Http\JsonResponse;
use Jf\Moussaillons\Infrastructure\Security\Csrf;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Refuse toute requête qui modifie des données sans jeton CSRF valide.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function process(Request $request, Handler $handler): Response
    {
        if (!in_array($request->getMethod(), self::SAFE_METHODS, true)
            && !Csrf::isValid($request->getHeaderLine('X-CSRF-Token'))) {
            return JsonResponse::error('Jeton de sécurité invalide. Rechargez la page.', 403);
        }

        return $handler->handle($request);
    }
}
