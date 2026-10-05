<?php

declare(strict_types=1);

/**
 * API SLIM - LES MOUSSAILLONS NUMÉRIQUES
 * Point d'entrée unique des appels AJAX. Les pages historiques restent servies directement
 * et migrent ici au fur et à mesure.
 *
 * Sans réécriture d'URL, les routes s'appellent via : api/index.php/quiz/12/start
 */

use Jf\Moussaillons\Http\Controller\EquipageController;
use Jf\Moussaillons\Http\Controller\InvitationController;
use Jf\Moussaillons\Http\Controller\PortController;
use Jf\Moussaillons\Http\Controller\QuizController;
use Jf\Moussaillons\Http\Middleware\CsrfMiddleware;
use Jf\Moussaillons\Http\Middleware\RoleMiddleware;
use Jf\Moussaillons\Infrastructure\Config;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

require_once __DIR__ . '/../bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (Config::maintenanceMode() && ($_SESSION['role'] ?? null) !== 'admin') {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Le port est actuellement en travaux.']);
    exit;
}

$app = AppFactory::create();

// Base des routes : ".../api/index.php" (sans réécriture) ou ".../api" (avec réécriture)
$scriptName = $_SERVER['SCRIPT_NAME'];
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$app->setBasePath(str_starts_with($requestPath, $scriptName) ? $scriptName : dirname($scriptName));

$app->group('', function (RouteCollectorProxy $eleve) {
    $eleve->post('/quiz/{id:[0-9]+}/start', [QuizController::class, 'demarrer']);
    $eleve->post('/quiz/{id:[0-9]+}/answer', [QuizController::class, 'repondre']);
    $eleve->post('/port/ships/{id:[0-9]+}/buy', [PortController::class, 'acheter']);
})->add(new RoleMiddleware('eleve'));

// Adultes (parents, enseignants) et amirauté : gestion de leurs équipages
$app->group('', function (RouteCollectorProxy $adulte) {
    $adulte->post('/equipages', [EquipageController::class, 'creer']);
    $adulte->post('/equipages/{id:[0-9]+}/moussaillons', [EquipageController::class, 'ajouterMoussaillon']);
    $adulte->post('/moussaillons/{id:[0-9]+}/pin', [EquipageController::class, 'changerPin']);
})->add(new RoleMiddleware('teacher', 'admin'));

$app->post('/invitations', [InvitationController::class, 'creer'])->add(new RoleMiddleware('admin'));

// Ordre d'exécution (dernier ajouté = premier exécuté) : erreurs → routage → CSRF → lecture du corps
$app->addBodyParsingMiddleware();
$app->add(new CsrfMiddleware());
$app->addRoutingMiddleware();
$app->addErrorMiddleware(filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN), true, true);

$app->run();
