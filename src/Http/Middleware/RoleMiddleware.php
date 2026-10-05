<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Http\Middleware;

use Jf\Moussaillons\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Réserve un groupe de routes à un ou plusieurs rôles de session (eleve, teacher, admin).
 * Ajoute les attributs "user_id" et "est_admin" à la requête pour les contrôleurs.
 */
final class RoleMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = $roles;
    }

    public function process(Request $request, Handler $handler): Response
    {
        if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], $this->roles, true)) {
            return JsonResponse::error('Accès refusé.', 401);
        }

        return $handler->handle(
            $request
                ->withAttribute('user_id', (int) $_SESSION['user_id'])
                ->withAttribute('est_admin', $_SESSION['role'] === 'admin')
        );
    }
}
