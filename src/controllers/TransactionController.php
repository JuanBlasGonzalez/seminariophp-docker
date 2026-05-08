<?php

namespace App\controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\models\Transaction;
use App\models\Asset;
use App\models\User;
use App\models\Portfolio;

class TransactionController {

    // Handle GET /transactions
    public static function getTransactionsByUser(Request $request, Response $response) {
        // 1. Obtener el usuario logueado que fue añadido a la petición por el AuthMiddleware.
        $loggedInUser = $request->getAttribute('user');
        // Se obtienen los posibles filtros de la URL (ej: ?type=buy)
        $filters = $request->getQueryParams();

        // 2. Usar el ID del usuario logueado para buscar su historial de transacciones.
        $transactions = Transaction::getByUser($loggedInUser['id'], $filters);

        // 3. Devolver las transacciones encontradas.
        $response->getBody()->write(json_encode($transactions));
        return $response->withStatus(200);
    }

    public static function buyAsset(Request $request, Response $response) {
        // 1. Obtener el usuario autenticado
        $loggedInUser = $request->getAttribute('user');
        $user_id = $loggedInUser['id'];

        // 2. Obtener los datos del cuerpo de la petición
        $data = $request->getParsedBody();
        $asset_id = $data['asset_id'] ?? null;
        $quantity = $data['quantity'] ?? null;

        // 3. Validar los datos de entrada
        if (!$asset_id || !$quantity) {
            $response->getBody()->write(json_encode(['error' => 'El asset_id y la quantity son requeridos.']));
            return $response->withStatus(400);
        }
        if (!is_int($quantity) || $quantity <= 0) {
            $response->getBody()->write(json_encode(['error' => 'La cantidad debe ser un numero entero mayor que cero.']));
            return $response->withStatus(400);
        }

        try{
            // 5. Obtener el activo y su precio actual usando el modelo Asset
            $asset = Asset::findById($asset_id);

            if (!$asset) {
                $response->getBody()->write(json_encode(['error' => 'El activo especificado no existe.']));
                return $response->withStatus(404);
            }

            $price_per_unit = $asset['current_price'];
            $total_cost = $quantity * $price_per_unit;

            // 6. Obtener el saldo del usuario usando el modelo User
            $user_balance = User::getBalanceById($user_id);

            // Comprobación defensiva: ¿se encontró el saldo del usuario?
            if ($user_balance === null) {
                $response->getBody()->write(json_encode(['error' => 'No se pudo encontrar el usuario para la transaccion.']));
                return $response->withStatus(404);
            }
            // 7. Verificar si el usuario tiene saldo suficiente
            if ($user_balance < $total_cost) {
                $response->getBody()->write(json_encode(['error' => 'Saldo insuficiente para realizar la compra.']));
                return $response->withStatus(409);
            }

            // 8. Ejecutar las operaciones de la compra
            // 8a. Restar el costo del saldo del usuario
            User::changeBalance($user_id, -$total_cost);

            // 8b. Actualizar el portfolio del usuario
            Portfolio::updateStock($user_id, $asset_id, $quantity);

            // 8c. Registrar la transacción en el historial
            Transaction::create($user_id, $asset_id, 'buy', $quantity, $price_per_unit);

            // 10. Devolver una respuesta de éxito
            $response->getBody()->write(json_encode(['message' => 'Compra realizada con exito.']));
            return $response->withStatus(200);

        } catch (\Exception $e) {            
            error_log('Error en la compra de activo: ' . $e->getMessage());

            // Devolver un error de servidor genérico al cliente
            $response->getBody()->write(json_encode(['error' => 'Ocurrio un error al procesar la compra.']));
            return $response->withStatus(500);
        }
    }

    public static function sellAsset(Request $request, Response $response) {
        // 1. Obtener el usuario autenticado
        $loggedInUser = $request->getAttribute('user');
        $user_id = $loggedInUser['id'];

        // 2. Obtener los datos del cuerpo de la petición
        $data = $request->getParsedBody();
        $asset_id = $data['asset_id'] ?? null;
        $quantity = $data['quantity'] ?? null;

        // 3. Validar los datos de entrada
        if (!$asset_id || !$quantity) {
            $response->getBody()->write(json_encode(['error' => 'El asset_id y la quantity son requeridos.']));
            return $response->withStatus(400);
        }
        if (!is_int($quantity) || $quantity <= 0) {
            $response->getBody()->write(json_encode(['error' => 'La cantidad debe ser un numero entero mayor que cero.']));
            return $response->withStatus(400);
        }

        try {
            // 4. Obtener el activo y su precio actual usando el modelo Asset.
            //    Este paso también valida que el activo exista ANTES de cualquier otra cosa.
            $asset = Asset::findById($asset_id);

            if (!$asset) {
                $response->getBody()->write(json_encode(['error' => 'El activo especificado no existe.']));
                return $response->withStatus(404);
            }

            $price_per_unit = $asset['current_price'];
            $total_value = $quantity * $price_per_unit;

            // 5. Ahora que sabemos que el activo existe, verificamos que el usuario posea suficientes para vender.
            $user_asset_quantity = Portfolio::getAssetQuantityForUser($user_id, $asset_id);

            if ($user_asset_quantity < $quantity) {
                $response->getBody()->write(json_encode(['error' => 'No tienes suficientes activos para vender. Cantidad poseida: ' . $user_asset_quantity]));
                return $response->withStatus(400);
            }

            // 6. Ejecutar las operaciones de la venta
            // 6a. Añadir el valor de la venta al saldo del usuario
            User::changeBalance($user_id, $total_value);

            // 6b. Restar el activo del portfolio del usuario. Usamos una cantidad negativa.
            Portfolio::updateStock($user_id, $asset_id, -$quantity);

            // 6c. Registrar la transacción en el historial
            Transaction::create($user_id, $asset_id, 'sell', $quantity, $price_per_unit);

            $response->getBody()->write(json_encode(['message' => 'Venta realizada con exito.']));
            return $response->withStatus(200);

        } catch (\Exception $e) {
            error_log('Error en la venta de activo: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Ocurrio un error al procesar la venta.']));
            return $response->withStatus(500);
        }
    }
}
