<?php

namespace App\controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\models\User;
use \DateTime;

class AuthController {

    public static function login(Request $request, Response $response) {
        // 1. Obtiene los datos enviados en el cuerpo de la petición (ej: el JSON con email y password).
        $data = $request->getParsedBody();
        
        // 2. Extrae el email y la contraseña. El '?? null' es una seguridad para evitar errores si no vienen.
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        
        // 3. Valida que ambos campos hayan sido enviados. Si no, devuelve un error 400 (Bad Request).
        if (!$email || !$password) {
            $response->getBody()->write(json_encode(['error' => 'Email y contraseña son requeridos.']));
            return $response->withStatus(400);
        }
        
        // 4. Usa el modelo User para buscar en la base de datos un usuario con ese email.
        $user = User::findByEmail($email);
        
        // 5. Verifica las credenciales. Hay dos posibilidades de fallo:
        //    a) El usuario no existe (`!$user`).
        //    b) La contraseña enviada no coincide con la guardada en la DB (`!password_verify`). $user['password'] devuelve la contraseña hasheada 
        //    En ambos casos, se devuelve un error 401 (Unauthorized) con un mensaje genérico para no dar pistas a atacantes.
        if (!$user || !password_verify($password, $user['password'])) {
            $response->getBody()->write(json_encode(['error' => 'Credenciales invalidas.']));
            return $response->withStatus(401);
        }
        
        // 6. Si las credenciales son correctas, genera un token seguro y aleatorio.
        $token = bin2hex(random_bytes(32));
        
        // 7. Crea un objeto de fecha y le suma 5 minutos para establecer la expiración del token.
        $expired_at = new DateTime();
        $expired_at->modify('+5 minutes');
        $expired_at_string = $expired_at->format('Y-m-d H:i:s'); // Lo convierte a formato para la DB.
        
        // 8. Llama al modelo User para guardar el nuevo token y su fecha de expiración en la base de datos.
        if (User::updateToken($user['id'], $token, $expired_at_string)) {
            // 9. Si se guardó correctamente, responde con un 200 OK y envía el token al cliente.
            $response->getBody()->write(json_encode([
                'message' => 'Login exitoso.',
                'token' => $token
            ]));
            return $response->withStatus(200);
        }
        
        // 10. Si hubo un error al guardar en la DB, devuelve un error 500 (Internal Server Error).
        $response->getBody()->write(json_encode(['error' => 'No se pudo guardar el token.']));
        return $response->withStatus(500);
    }

    public static function logout(Request $request, Response $response) {
        // 1. Obtiene la cabecera 'Authorization' de la petición, que debería contener el token.
        $authHeader = $request->getHeaderLine('Authorization');
        
        // 2. Verifica que la cabecera tenga el formato "Bearer <token>" y extrae el token.
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1]; // El token es la parte capturada por los paréntesis.
        
            // 3. Busca en la base de datos qué usuario tiene este token.
            $user = User::findByToken($token);
        
            // 4. Si se encuentra un usuario...
            if ($user) {
                // 5. ...se llama al modelo para borrar el token de la base de datos (ponerlo en NULL).
                User::clearToken($user['id']);
            }
        }
        else {
            // Si no se encuentra el token en la cabecera, o no tiene el formato correcto, respondemos con un error 400 (Bad Request).
            $response->getBody()->write(json_encode(['error'=> 'El token es invalido o no se proporciono correctamente.']));
            return $response->withStatus(400); 
        }
        // 6. Responde con un mensaje de éxito 
        $response->getBody()->write(json_encode(['message' => 'Logout exitoso.']));
        return $response->withStatus(200);
        
    }
}
