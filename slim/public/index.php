<?php
 
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
// Importo los controladores
use App\controllers\UserController;
use App\controllers\AssetController;
use App\controllers\TransactionController;
use App\controllers\PortfolioController;
use App\controllers\AuthController;
use App\middleware\AuthMiddleware;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);
$app->add( function ($request, $handler) {
    $response = $handler->handle($request);

    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, PATCH, DELETE')
        ->withHeader('Content-Type', 'application/json')
    ;
});

// ACÁ VAN LOS ENDPOINTS

// --- Rutas Públicas que no requieren autenticación ---

// --- Autenticación ---
$app->post('/login', AuthController::class . '::login');

// --- Usuarios ---
$app->post('/users', UserController::class . '::create');

// --- Activos ---
$app->get('/assets', AssetController::class . '::getAssets');
$app->get('/assets/{asset_id}/history/{quantity}', AssetController::class . '::getAssetHistory');

// --- Rutas Protegidas que requieren autenticación ---
// Todas las rutas dentro de este grupo pasarán primero por el AuthMiddleware.

$app->group('', function ($group) {
    // Autenticacion
    $group->post('/logout', AuthController::class . '::logout');

    // Usuarios
    $group->get('/users/{user_id}', UserController::class . '::getUserById'); 
    $group->put('/users/{user_id}', UserController::class . '::update');
    $group->get('/users', UserController::class . '::getUsers');

    // Activos 
    $group->put('/assets', AssetController::class . '::updateAssets');

    // Operaciones (compra/venta)
    $group->post('/trade/buy', TransactionController::class . '::buyAsset');
    $group->post('/trade/sell', TransactionController::class . '::sellAsset');

    // Portfolio e Historial
    $group->get('/portfolio', PortfolioController::class . '::getPortfolioForUser');
    $group->delete('/portfolio/{asset_id}', PortfolioController::class . '::deletePortfolio');
    $group->get('/transactions', TransactionController::class . '::getTransactionsByUser');
})->add(new AuthMiddleware());

$app->run();
