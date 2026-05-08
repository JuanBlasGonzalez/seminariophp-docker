<?php

namespace App\models;

use App\config\DB;
use PDO;

class Transaction {
    public $id;
    public $user_id;
    public $asset_id;
    public $transaction_type; // 'BUY' o 'SELL'
    public $quantity;
    public $price_per_unit;
    public $total_amount;
    public $transaction_date;

    // Para el endpoint GET /transactions 
    public static function getByUser($user_id, $filters = []) {
        $db = DB::getConnection();
        
        $query = "SELECT * FROM transactions WHERE user_id = ?";
        $params = [$user_id];

        if (!empty($filters['type'])) {
            $query .= " AND transaction_type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['asset_id'])) {
            $query .= " AND asset_id = ?";
            $params[] = $filters['asset_id'];
        }

        $query .= " ORDER BY transaction_date DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    // Registrar una compra o venta
    public static function create($user_id, $asset_id, $type, $quantity, $price) {
        $db = DB::getConnection();
        $total_amount = $quantity * $price;
        $stmt = $db->prepare("INSERT INTO transactions (user_id, asset_id, transaction_type, quantity, price_per_unit, total_amount) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        // 'type' sería 'BUY' o 'SELL' 
        return $stmt->execute([$user_id, $asset_id, $type, $quantity, $price, $total_amount]);
    }

}