<?php

namespace App\middleware;

use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use App\models\User;
use \DateTime;

class AuthMiddleware implements Middleware{
    private ResponseFactoryInterface $responseFactory;

    public function __construct() {
        $this->responseFactory = new \Slim\Psr7\Factory\ResponseFactory();
    }

    public function process(Request $request, RequestHandler $handler): Response {
        // 1. Obtener el token del header 'Authorization'.
        $authHeader = $request->getHeaderLine('Authorization');
        $token = null;

        // 2. Extraer el token del formato "Bearer <token>".
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }

        // 3. Si no hay token, denegar el acceso inmediatamente.
        if (!$token) {
            $response = $this->responseFactory->createResponse();
            $response->getBody()->write(json_encode(['error' => 'Acceso no autorizado. Se requiere un token.']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // 4. Buscar el usuario asociado al token en la base de datos.
        $user = User::findByToken($token);

        // 5. Validar que el usuario exista y que el token no haya expirado.
        if (!$user || (new DateTime() > new DateTime($user['token_expired_at']))) {
            $response = $this->responseFactory->createResponse();
            $response->getBody()->write(json_encode(['error' => 'Token invalido o expirado.']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // 6. Extender la vida del token 5 minutos más.
        $new_expired_at = (new DateTime())->modify('+5 minutes')->format('Y-m-d H:i:s');
        User::updateToken($user['id'], $token, $new_expired_at);

        // 7. ¡La parte clave! Añadir los datos del usuario al objeto $request.
        $request = $request->withAttribute('user', $user);

        // 8. Pasar la petición (ya modificada) al siguiente eslabón (otro middleware o el controlador final).
        return $handler->handle($request);
    }
}