<?php

namespace App\controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\models\Asset;

class AssetController {

    // Handle GET /assets
    public static function getAssets(Request $request, Response $response) {
        // Obtener todos los parámetros de la query string (ej: ?type={bitcoin}) como un array asociativo.
        $filters = $request->getQueryParams();

        // Llamar a un método en el modelo asset que devuelvo los assets filtrados.
        $assets = Asset::getFiltered($filters);

        $response->getBody()->write(json_encode($assets));
        return $response->withStatus(200);
    }

    // Handle GET /assets/{asset_id}/history/{quantity}
    public static function getAssetHistory(Request $request, Response $response, array $args) {
        // Obtener el ID del activo y la cantidad de movimientos a mostrar desde la URL.
        $asset_id = $args['asset_id'];
        $quantity = $args['quantity'];

        // Validar la cantidad. 
        // Usamos min() para asegurarnos de que no se pidan más de 5.
        // (int) convierte el string de la URL a un número.
        $limit = min((int)$quantity, 5);

        // Si se pide 0 o un número negativo, no tiene sentido, así que lo ajustamos a 5 por defecto.
        if ($limit <= 0) {
            $limit = 5;
        }

        // Llamar al modelo de asset para obtener el historial del activo.
        $history = Asset::getHistoryForAsset($asset_id, $limit);
        if ($history === false || empty($history)) {
            $response->getBody()->write(json_encode(['error' => 'No se registraron transferencias de este activo.']));
            return $response->withStatus(404);
        }
        // Devolver la respuesta.
        $response->getBody()->write(json_encode($history));
        return $response->withStatus(200);
    }

    // Handle PUT /assets
    public static function updateAssets(Request $request, Response $response) {
        // Autorización: Verificar que el usuario sea administrador.
        // El middleware ya nos dio los datos del usuario.
        $loggedInUser = $request->getAttribute('user');
        if (!$loggedInUser || !$loggedInUser['is_admin']) {
            $response->getBody()->write(json_encode(['error' => 'Acceso denegado. Se requiere ser administrador.']));
            return $response->withStatus(401);
        }

        // Obtener todos los activos existentes.
        $assets = Asset::getAll();

        // Iterar sobre cada activo para actualizar su precio.
        foreach ($assets as $asset) {
            // Calcular el nuevo precio usando la lógica de variación del modelo.
            $lastUpdateTimestamp = strtotime($asset['last_update']);
            $newPrice = Asset::variarPrecioPorTiempo($asset['current_price'], $lastUpdateTimestamp);
            Asset::updatePrice($asset['id'], $newPrice);
        }

        $response->getBody()->write(json_encode(['message' => 'Precios de los activos actualizados con exito.']));
        return $response->withStatus(200);
    }
}
