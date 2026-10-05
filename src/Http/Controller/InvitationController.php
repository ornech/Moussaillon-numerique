<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Http\Controller;

use Jf\Moussaillons\Application\Auth\InscriptionAdulte;
use Jf\Moussaillons\Http\JsonResponse;
use Jf\Moussaillons\Infrastructure\Config;
use Jf\Moussaillons\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class InvitationController
{
    /**
     * POST /invitations : lien d'inscription à envoyer à un parent ou un enseignant.
     */
    public function creer(Request $request, Response $response): Response
    {
        $inscription = new InscriptionAdulte(Database::getInstance()->getConnection());
        $jeton = $inscription->inviter((int) $request->getAttribute('user_id'));

        return JsonResponse::ok($response, [
            'success'        => true,
            'lien'           => Config::appUrl() . '/inscription.php?invitation=' . $jeton,
            'validite_jours' => InscriptionAdulte::validiteJours(),
        ]);
    }
}
