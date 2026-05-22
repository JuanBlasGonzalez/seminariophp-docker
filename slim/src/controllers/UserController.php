<?php

namespace App\controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\models\User;

class UserController {

    // Handle GET /users
    public static function getUsers(Request $request, Response $response) {
        // Obtener el usuario logueado que fue añadido a la petición por el AuthMiddleware.
        $loggedInUser = $request->getAttribute('user');

        // Validar que el usuario sea administrador.
        if (!$loggedInUser || !$loggedInUser['is_admin']) {
            $response->getBody()->write(json_encode(['error' => 'Acceso denegado. Se requiere ser administrador.']));
            return $response->withStatus(401);
        }

        // Si es admin, obtener la lista de usuarios.
        $users = User::getAll();
        $response->getBody()->write(json_encode($users));
        return $response->withStatus(200);
    }

    // Handle POST /users
    public static function create(Request $request, Response $response) {
        $data = $request->getParsedBody();
        $name = $data['name'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        // Validar que el nombre no esté vacío y solo contenga letras.
        if (empty($name) || !preg_match('/^[a-zA-Z\s]+$/', $name)) {
            $response->getBody()->write(json_encode(['error' => 'El nombre es invalido. No puede ser vacio y solo debe contener letras.']));
            return $response->withStatus(400);
        }
        // Validar que el email tenga un formato válido.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode(['error' => 'El formato del email es invalido.']));
            return $response->withStatus(400);
        }
        // Validar que la contraseña cumpla con los requisitos de seguridad definidos en el modelo.
        if (!User::validarPassword($password)) {
            $response->getBody()->write(json_encode(['error' => 'La contrasena no cumple los requisitos: minimo 8 caracteres, una mayuscula, una minuscula, un numero y un caracter especial.']));
            return $response->withStatus(400);
        }

        // Usamos un bloque try-catch para manejar posibles errores de la base de datos, como un email duplicado.
        try {
            // Hasheamos la contraseña antes de guardarla.
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            // Llama al método del modelo user para guardar el nuevo usuario en la base de datos.
            User::save($name, $email, $hashedPassword);
            // SI salio todo bien devuelvo mensaje de exito con codigo 200.
            $response->getBody()->write(json_encode(['message' => 'Usuario creado con exito. Recibio un bono de 1000 USD.']));
            return $response->withStatus(200);
        } catch (\PDOException $e) {
            // Si se produce una excepción de la base de datos
            // Comprobamos si el código de error es 23000, que corresponde a email duplicado.
            if ($e->getCode() == 23000) { // Error de entrada duplicada (email único)
                 // Devolvemos un error 409 (Conflict) indicando que el email ya existe.
                 $response->getBody()->write(json_encode(['error' => 'El email ya esta registrado.']));
                 return $response->withStatus(409);
            }
            // Para cualquier otro error de la base de datos, devolvemos un error genérico 500.
            $response->getBody()->write(json_encode(['error' => 'Error en la base de datos al crear el usuario.']));
            return $response->withStatus(500);
        }
    }

    // Handle GET /users/{user_id}
    public static function getUserById(Request $request, Response $response, array $args) {
        // Obtener el ID del usuario de los argumentos de la ruta (la parte {user_id} de la URL).
        $user_id = $args['user_id'];

        // Solo el propio usuario o un admin puedan ver el perfil.
        $loggedInUser = $request->getAttribute('user');
        if (!$loggedInUser || ($loggedInUser['id'] != $user_id && !$loggedInUser['is_admin'])) {
            $response->getBody()->write(json_encode(['error' => 'No autorizado para ver este perfil.']));
            return $response->withStatus(401);
        }

        // Buscar el perfil del usuario en la base de datos usando el método del modelo.
        // Este método devuelve los datos del usuario y el valor total de su portfolio.
        $user = User::getProfileById($user_id);

        // Comprobar si el usuario fue encontrado.
        if ($user) {
            // Si se encuentra, devolver los datos con un estado 200 OK.
            $response->getBody()->write(json_encode($user));
            return $response->withStatus(200);
        } else {
            // Si no se encuentra, devolver un error 404 Not Found.
            $response->getBody()->write(json_encode(['error' => 'Usuario no encontrado.']));
            return $response->withStatus(404);
        }
    }

    // Handle PUT /users/{user_id}
    public static function update(Request $request, Response $response, array $args) {
        // Obtener el ID del usuario de los argumentos de la ruta.
        $user_id = $args['user_id'];
        
        // Solo el propio usuario o un admin puedan editar.
        $loggedInUser = $request->getAttribute('user');
        if (!$loggedInUser || ($loggedInUser['id'] != $user_id && !$loggedInUser['is_admin'])) {
            $response->getBody()->write(json_encode(['error' => 'No autorizado para modificar este usuario.']));
            return $response->withStatus(401);
        }

        // Obtener los datos enviados en el cuerpo de la petición.
        $data = $request->getParsedBody();
        $updateData = [];

        // Validar los datos que se pueden modificar (name y/o password).
        // Si se envió un nombre, se valida y se añade a los datos a actualizar.
        if (isset($data['name'])) {
            if (empty($data['name']) || !preg_match('/^[a-zA-Z\s]+$/', $data['name'])) {
                $response->getBody()->write(json_encode(['error' => 'El nombre proporcionado es invalido. Solo puede contener letras y no puede ser vacio.']));
                return $response->withStatus(400);
            }
            $updateData['name'] = $data['name'];
        }

        // Si se envió una contraseña, se valida y se añade a los datos a actualizar.
        if (isset($data['password'])) {
            if (!User::validarPassword($data['password'])) {
                $response->getBody()->write(json_encode(['error' => 'La nueva contraseña no cumple los requisitos. Debe tener minimo 8 caracteres, una mayuscula, una minuscula, un numero y un caracter especial.']));
                return $response->withStatus(400);
            }
            $updateData['password'] = $data['password'];
        }

        // Comprobar si se envió algún dato válido para actualizar. Si no, es un Bad Request.
        if (empty($updateData)) {
            $response->getBody()->write(json_encode(['error' => 'No se proporcionaron datos validos para actualizar (solo se permite name y/o password).']));
            return $response->withStatus(400);
        }

        // Llamar al método del modelo para actualizar el usuario.
        // El método `User::update` ya se encarga de construir la consulta SQL y hashear la contraseña.
        $success = User::update($user_id, $updateData);

        // Devolver la respuesta adecuada.
        if ($success) {
            // Si la actualización fue exitosa, devolver 200 OK.
            $response->getBody()->write(json_encode(['message' => 'Usuario actualizado con exito.']));
            return $response->withStatus(200);
        } else {
            // Si no, devolver 404 Not Found.
            $response->getBody()->write(json_encode(['error' => 'No se pudo actualizar el usuario.']));
            return $response->withStatus(404);
        }
    }
}
