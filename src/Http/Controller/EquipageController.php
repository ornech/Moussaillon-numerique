<?php

declare(strict_types=1);

namespace Jf\Moussaillons\Http\Controller;

use Jf\Moussaillons\Application\Equipage\EquipageService;
use Jf\Moussaillons\Application\ErreurMetier;
use Jf\Moussaillons\Http\JsonResponse;
use Jf\Moussaillons\Infrastructure\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Actions des adultes sur leurs équipages (appelées depuis le tableau de bord).
 * Les succès sont aussi déposés en "flash" : la page se recharge et affiche le message.
 */
final class EquipageController
{
    /**
     * POST /equipages {nom}
     */
    public function creer(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        return $this->executer($response, function () use ($request, $body) {
            $equipage = $this->service()->creer((int) $request->getAttribute('user_id'), (string) ($body['nom'] ?? ''));
            return "Équipage « {$equipage['name']} » créé. Son code : {$equipage['code']}";
        });
    }

    /**
     * POST /equipages/{id}/moussaillons {pseudo, pin}
     */
    public function ajouterMoussaillon(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        return $this->executer($response, function () use ($request, $args, $body) {
            $service = $this->service();
            if (!$service->peutGererEquipage((int) $args['id'], (int) $request->getAttribute('user_id'), (bool) $request->getAttribute('est_admin'))) {
                throw new ErreurMetier('Équipage introuvable.');
            }
            $pseudo = EquipageService::validerPseudo((string) ($body['pseudo'] ?? ''));
            $pin = (string) ($body['pin'] ?? '');
            $service->ajouterMoussaillon((int) $args['id'], $pseudo, $pin);
            return "Moussaillon ajouté ! Pseudo : $pseudo — Code secret : $pin. Notez-les pour l'enfant.";
        });
    }

    /**
     * POST /moussaillons/{id}/pin {pin} : nouveau code secret pour un enfant qui l'a oublié.
     */
    public function changerPin(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        return $this->executer($response, function () use ($request, $args, $body) {
            $service = $this->service();
            if (!$service->peutGererMoussaillon((int) $args['id'], (int) $request->getAttribute('user_id'), (bool) $request->getAttribute('est_admin'))) {
                throw new ErreurMetier('Moussaillon introuvable.');
            }
            $pin = (string) ($body['pin'] ?? '');
            $service->changerPin((int) $args['id'], $pin);
            return "Nouveau code secret enregistré : $pin";
        });
    }

    /**
     * Exécute l'action ; une ErreurMetier devient un message affichable (422).
     */
    private function executer(Response $response, callable $action): Response
    {
        try {
            $message = $action();
        } catch (ErreurMetier $e) {
            return JsonResponse::error($e->getMessage(), 422);
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => $message];
        return JsonResponse::ok($response, ['success' => true, 'message' => $message]);
    }

    private function service(): EquipageService
    {
        return new EquipageService(Database::getInstance()->getConnection());
    }
}
