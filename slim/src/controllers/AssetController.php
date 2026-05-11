<?php

namespace App\controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\models\Asset;

class AssetController {

    // Handle GET /assets
    public static function getAssets(Request $request, Response $response) {
        // 1. Obtener todos los parámetros de la query string (ej: ?type=Bitcoin&min_price=50) como un array asociativo.
        $filters = $request->getQueryParams();

        // 2. Llamar a un nuevo método en el modelo, pasándole los filtros.
        $assets = Asset::getFiltered($filters);

        $response->getBody()->write(json_encode($assets));
        return $response->withStatus(200);
    }

    // Handle GET /assets/{asset_id}/history/{quantity}
    public static function getAssetHistory(Request $request, Response $response, array $args) {
        // 1. Obtener el ID del activo y la cantidad de registros a mostrar desde la URL.
        $asset_id = $args['asset_id'];
        $quantity = $args['quantity'];

        // 2. Validar la cantidad. El TP especifica un máximo de 5.
        // Usamos min() para asegurarnos de que no se pidan más de 5.
        // (int) convierte el string de la URL a un número.
        $limit = min((int)$quantity, 5);

        // 3. Si se pide 0 o un número negativo, no tiene sentido, así que lo ajustamos a 5 por defecto.
        if ($limit <= 0) {
            $limit = 5;
        }

        // 4. Llamar al modelo de Transacciones para obtener el historial del activo.
        $history = Asset::getHistoryForAsset($asset_id, $limit);
        if ($history === false || empty($history)) {
            $response->getBody()->write(json_encode(['error' => 'No se registraron transferencias de este activo.']));
            return $response->withStatus(404);
        }
        // 5. Devolver la respuesta.
        $response->getBody()->write(json_encode($history));
        return $response->withStatus(200);
    }

    // Handle PUT /assets
    public static function updateAssets(Request $request, Response $response) {
        // 1. Autorización: Verificar que el usuario sea administrador.
        // El middleware ya nos dio los datos del usuario.
        $loggedInUser = $request->getAttribute('user');
        if (!$loggedInUser || !$loggedInUser['is_admin']) {
            $response->getBody()->write(json_encode(['error' => 'Acceso denegado. Se requiere ser administrador.']));
            return $response->withStatus(401);
        }

        // 2. Obtener todos los activos existentes.
        $assets = Asset::getAll();

        // 3. Iterar sobre cada activo para actualizar su precio.
        foreach ($assets as $asset) {
            // 4. Calcular el nuevo precio usando la lógica de variación del modelo.
            $lastUpdateTimestamp = strtotime($asset['last_update']);
            $newPrice = Asset::variarPrecioPorTiempo($asset['current_price'], $lastUpdateTimestamp);
            Asset::updatePrice($asset['id'], $newPrice);
        }

        $response->getBody()->write(json_encode(['message' => 'Precios de los activos actualizados con exito.']));
        return $response->withStatus(200);
    }
}
