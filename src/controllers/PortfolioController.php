<?php

namespace App\controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\models\Portfolio;

class PortfolioController {

    // Handle GET /portfolio
    public static function getPortfolioForUser(Request $request, Response $response) {
        // 1. Obtener el usuario logueado que fue añadido a la petición por el AuthMiddleware.
        $loggedInUser = $request->getAttribute('user');

        // 2. Usar el ID del usuario logueado para buscar su portfolio.
        $portfolio = Portfolio::getByUser($loggedInUser['id']);

        // 3. Devolver el portfolio encontrado.
        $response->getBody()->write(json_encode($portfolio));
        return $response->withStatus(200);
    }

    // Handle DELETE /portfolio/{asset_id}
    public static function deletePortfolio(Request $request, Response $response, array $args) {
        // 1. Obtener el usuario logueado y el ID del activo desde la URL.
        $loggedInUser = $request->getAttribute('user');
        $user_id = $loggedInUser['id'];
        $asset_id = $args['asset_id'];

        // 2. Verificar la cantidad de activos que posee el usuario.
        //    Este método devuelve 0 si el usuario no tiene el activo o si la cantidad es cero.
        $quantity = Portfolio::getAssetQuantityForUser($user_id, $asset_id);

        // 3. Si la cantidad es mayor a cero, no se puede borrar el registro.
        //    El usuario debe vender sus activos primero.
        if ($quantity > 0) {
            $response->getBody()->write(json_encode(['error' => 'No puedes quitar un activo de tu portfolio si aun tienes unidades. Debes venderlas primero.']));
            return $response->withStatus(409); // 409 Conflict: la acción no se puede realizar por el estado actual del recurso.
        }

        // 4. Si la cantidad es 0, intentar eliminar el registro.
        $deletedRows = Portfolio::deleteAssetForUser($user_id, $asset_id);

        // 5. Analizar el resultado de la eliminación.
        if ($deletedRows > 0) {
            // Caso 2: El registro existía (con cantidad 0) y fue eliminado con éxito.
            $response->getBody()->write(json_encode(['message' => 'Activo eliminado del portfolio con exito.']));
            return $response->withStatus(200);
        } else {
            // Caso 3: El usuario nunca tuvo este activo en su portfolio. El registro a eliminar no fue encontrado.
            $response->getBody()->write(json_encode(['error' => 'Este activo no se encuentra en tu portfolio.']));
            return $response->withStatus(404);
        }
    }
}
