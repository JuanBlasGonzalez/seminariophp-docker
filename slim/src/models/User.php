<?php

namespace App\models;

use App\config\DB;
use PDO;

class User {
    private $id;
    private $name;
    private $email;
    private $password;
    private $balance;
    private $is_admin;
    private $token;
    private $token_expired_at;
    private $created_at;

    // Obtener todos los usuarios para el endpoint GET /users
    public static function getAll() {
        $db = DB::getConnection();
        // Esta consulta calcula el valor total del portfolio para cada usuario no administrador.
        // 1. Selecciona el nombre del usuario (u.name).
        // 2. Calcula el valor total del portfolio ('total_portfolio_value') sumando:
        //    - El valor total de sus activos, que es la suma (SUM) de la cantidad de cada activo (p.quantity) 
        //      multiplicada por su precio actual (a.current_price).
        //    - COALESCE se usa para manejar usuarios sin activos; si la suma es NULL, la convierte a 0.
        // 3. Une las tablas 'users' (u), 'portfolio' (p) y 'assets' (a) para acceder a todos los datos necesarios.
        //    - LEFT JOIN asegura que se incluyan todos los usuarios, incluso aquellos sin activos en su portfolio.
        // 4. Filtra los resultados para excluir a los administradores (u.is_admin = 0).
        // 5. Agrupa los resultados por usuario para que la función SUM() funcione correctamente para cada uno.
        $query = "
            SELECT 
                u.name,(COALESCE(SUM(p.quantity * a.current_price), 0)) AS total_portfolio_value
            FROM 
                users u
            LEFT JOIN 
                portfolio p ON u.id = p.user_id
            LEFT JOIN 
                assets a ON p.asset_id = a.id
            WHERE
                u.is_admin = 0
            GROUP BY 
                u.id, u.name
        ";
        $stmt = $db->query($query); 
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Para el POST /users
    public static function save($name, $email, $password) {
        $db = DB::getConnection();
        $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $email, $password]);
    }

    // Para el PUT /users/{id}. Permite actualizar solo los datos enviados.
    public static function update($id, $data) {
        $db = DB::getConnection();
        $fields = [];
        $params = [];
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        if (isset($data['password'])) {
            $fields[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if (empty($fields)) { return false; } // No hay nada que actualizar
        $query = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $params[] = $id;
        $stmt = $db->prepare($query);
        return $stmt->execute($params);
    }

    // Para GET /users/{id}: Obtener el perfil de un solo usuario con el valor de su portfolio
    public static function getProfileById($id) {
        $db = DB::getConnection();
        $query = "
            SELECT 
                u.id,
                u.name,
                u.email,
                u.balance,
                COALESCE(SUM(p.quantity * a.current_price), 0) AS total_portfolio_value
            FROM 
                users u
            LEFT JOIN 
                portfolio p ON u.id = p.user_id
            LEFT JOIN 
                assets a ON p.asset_id = a.id
            WHERE 
                u.id = ?
            GROUP BY 
                u.id, u.name, u.email, u.balance
        ";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getBalanceById($id) {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $balance = $stmt->fetchColumn();
        return $balance === false ? null : (float)$balance;
    }

    public static function changeBalance($id, $amount) {
        $db = DB::getConnection();
        $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        return $stmt->execute([$amount, $id]);
    }

    // Método para validar el password
    public static function validarPassword($password) {
        // Mínimo 8 caracteres, una mayúscula, una minúscula, un número y un especial
        $regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/";
        return preg_match($regex, $password); //preg_match compara un patron contra un texto
    }

    // Para el login: buscar usuario por su email
    public static function findByEmail($email) {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Para el middleware y logout: buscar usuario por su token
    public static function findByToken($token) {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Guardar/actualizar token y expiración en la DB
    public static function updateToken($id, $token, $expired_at) {
        $db = DB::getConnection();
        $stmt = $db->prepare("UPDATE users SET token = ?, token_expired_at = ? WHERE id = ?");
        return $stmt->execute([$token, $expired_at, $id]);
    }

    // Limpiar token en el logout
    public static function clearToken($id) {
        $db = DB::getConnection();
        $stmt = $db->prepare("UPDATE users SET token = NULL, token_expired_at = NULL WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
