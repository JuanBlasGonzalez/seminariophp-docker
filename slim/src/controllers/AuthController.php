<?php

namespace App\controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\models\User;
use \DateTime;

class AuthController {

    public static function login(Request $request, Response $response) {
        // Obtiene los datos enviados en el cuerpo de la petición (ej: el JSON con email y password).
        $data = $request->getParsedBody();
        
        // Extrae el email y la contraseña. El '?? null' es una seguridad para evitar errores si no vienen.
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        
        // Valida que ambos campos hayan sido enviados. Si no, devuelve un error 400 (Bad Request).
        if (!$email || !$password) {
            $response->getBody()->write(json_encode(['error' => 'Email y contraseña son requeridos.']));
            return $response->withStatus(400);
        }
        
        // Usa el modelo User para buscar en la base de datos un usuario con ese email.
        $user = User::findByEmail($email);
        
        // Verifico que el usuario exista y que las credenciales sean correctas.
        if (!$user || !password_verify($password, $user['password'])) {
            $response->getBody()->write(json_encode(['error' => 'Credenciales invalidas.']));
            return $response->withStatus(401);
        }
        
        // Si las credenciales son correctas, genera un token seguro y aleatorio.
        $token = bin2hex(random_bytes(32));
        
        // Crea un objeto de fecha y le suma 5 minutos para establecer la expiración del token.
        $expired_at = new DateTime();
        $expired_at->modify('+5 minutes');
        $expired_at_string = $expired_at->format('Y-m-d H:i:s'); // Lo convierte a formato para la DB.
        
        // Llama al modelo User para guardar el nuevo token y su fecha de expiración en la base de datos.
        if (User::updateToken($user['id'], $token, $expired_at_string)) {
            // Si se guardó correctamente, responde con un 200 OK y envía el token al cliente.
            $response->getBody()->write(json_encode([
                'message' => 'Login exitoso.',
                'token' => $token
            ]));
            return $response->withStatus(200);
        }
        
        // Si hubo un error al guardar en la DB, devuelve un error 500.
        $response->getBody()->write(json_encode(['error' => 'No se pudo guardar el token en la DB.']));
        return $response->withStatus(500);
    }

    public static function logout(Request $request, Response $response) {
        // Obtener el usuario autenticado (añadido por el AuthMiddleware)
        $loggedInUser = $request->getAttribute('user');
        
        // Limpiar el token del usuario en la base de datos
        User::clearToken($loggedInUser['id']);
        
        // Responder con un mensaje de éxito
        $response->getBody()->write(json_encode(['message' => 'Logout exitoso.']));
        return $response->withStatus(200);
    }
}
