<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Http\Controller;

use Jf\Moussaillons\Application\Port\AchatNavire;
use Jf\Moussaillons\Http\JsonResponse;
use Jf\Moussaillons\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PortController
{
    /**
     * POST /port/ships/{id}/buy
     * Le résultat est aussi déposé en "flash" pour que port.php l'affiche après rechargement.
     */
    public function acheter(Request $request, Response $response, array $args): Response
    {
        $achat = new AchatNavire(Database::getInstance()->getConnection());
        $resultat = $achat->acheter((int) $request->getAttribute('user_id'), (int) $args['id']);

        $_SESSION['flash'] = [
            'type'    => $resultat['success'] ? 'success' : 'error',
            'message' => $resultat['message'],
        ];

        return JsonResponse::ok($response, $resultat);
    }
}
