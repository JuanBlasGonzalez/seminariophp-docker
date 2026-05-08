<?php

namespace App\models;

use App\config\DB;
use PDO;

class Portfolio {
    public $id;
    public $user_id;
    public $asset_id;
    public $quantity;
   
    public static function getByUser($user_id) {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT a.name, p.quantity, a.current_price, 
                                     (p.quantity * a.current_price) AS total_value
                              FROM portfolio p 
                              JOIN assets a ON p.asset_id = a.id 
                              WHERE p.user_id = ? AND p.quantity > 0"); 
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Actualizar o insertar cantidad de un activo para un usuario
    public static function updateStock($user_id, $asset_id, $quantity) {
        $db = DB::getConnection();
        // Esta consulta es útil: si no existe el registro lo crea, si existe lo actualiza
        $stmt = $db->prepare("INSERT INTO portfolio (user_id, asset_id, quantity) 
                              VALUES (?, ?, ?) 
                              ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        return $stmt->execute([$user_id, $asset_id, $quantity, $quantity]);
    }

    // Obtener la cantidad de un activo específico que posee un usuario
    public static function getAssetQuantityForUser($user_id, $asset_id) {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT quantity FROM portfolio WHERE user_id = ? AND asset_id = ?");
        $stmt->execute([$user_id, $asset_id]);
        $quantity = $stmt->fetchColumn();
        return $quantity === false ? 0 : (float)$quantity; // Devuelve 0 si no lo tiene, o la cantidad si lo tiene
    }

    // Eliminar un activo del portfolio de un usuario (usado cuando la cantidad es 0)
    public static function deleteAssetForUser($user_id, $asset_id) {
        $db = DB::getConnection();
        $stmt = $db->prepare("DELETE FROM portfolio WHERE user_id = ? AND asset_id = ?");
        $stmt->execute([$user_id, $asset_id]);
        return $stmt->rowCount();
    }
}
