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

//probando el endpoint raíz para verificar que el servidor funciona correctamente
$app->get('/', function (Request $request, Response $response, $args) {
        $response->getBody()->write(json_encode(['Hello world!']));
        return $response;
});

// --- Autenticación ---
// El login es público
$app->post('/login', AuthController::class . '::login');

// --- Usuarios ---
// El registro de usuarios es público
$app->post('/users', UserController::class . '::create');

// --- Activos (El Mercado) ---
// La consulta de activos y su historial es pública
$app->get('/assets', AssetController::class . '::getAssets');
$app->get('/assets/{asset_id}/history/{quantity}', AssetController::class . '::getAssetHistory');

// --- Rutas Protegidas ---
// Todas las rutas dentro de este grupo pasarán primero por el AuthMiddleware.
// $app->group(...): Esto le dice a Slim: "Voy a definir varias rutas que comparten una característica en común". 
// En este caso, la característica común es que todas necesitan autenticación.
//->add(new AuthMiddleware()): El método .add() adjunta un middleware a todo el grupo. 
// Esto significa que antes de que se ejecute el código de cualquier 
// controlador (como UserController::getUsers o AuthController::logout), la 
// petición primero debe pasar por el AuthMiddleware.
$app->group('', function ($group) {
    // Logout
    $group->post('/logout', AuthController::class . '::logout');

    // Usuarios (ver perfil, editar, listar para admin)
    $group->get('/users/{user_id}', UserController::class . '::getUserById'); 
    $group->put('/users/{user_id}', UserController::class . '::update');
    $group->get('/users', UserController::class . '::getUsers');

    // Activos (actualización de precios por admin)
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
