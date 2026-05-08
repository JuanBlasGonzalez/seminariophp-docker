<?php

namespace App\models;

use App\config\DB;
use PDO;

class Asset {
    public $id;
    public $name;       
    public $current_price;
    public $last_update;

    public static function getAll() {
        $db = DB::getConnection();
        $stmt = $db->query("SELECT * FROM assets"); 
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener activos con filtros opcionales
    public static function getFiltered($filters) {
        $db = DB::getConnection();
        // 1. Empezamos con una consulta base que siempre es verdadera.
        $query = "SELECT * FROM assets WHERE 1=1";
        $params = [];

        // 2. Añadimos condiciones a la consulta dinámicamente si los filtros existen.
        if (!empty($filters['type'])) {
            $query .= " AND name = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['min_price'])) {
            $query .= " AND current_price >= ?";
            $params[] = $filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $query .= " AND current_price <= ?";
            $params[] = $filters['max_price'];
        }

        // 3. Preparamos la consulta que hemos construido.
        $stmt = $db->prepare($query);

        // 4. Ejecutamos la consulta con los parámetros correspondientes.
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function findById($id) {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Actualizar el precio en la DB tras calcular la variación
    public static function updatePrice($id, $newPrice) {
        $db = DB::getConnection();
        $stmt = $db->prepare("UPDATE assets SET current_price = ? WHERE id = ?");
        return $stmt->execute([$newPrice, $id]);
    }

    public static function variarPrecioPorTiempo($precioActual, $timestampUltimaVez, $volatilidadPorSegundo = 0.05) {
        // 1. Calcular cuántos segundos han pasado
        $tiempoPasado = time() - $timestampUltimaVez; 
        // Si no ha pasado tiempo, el precio no cambia
        if ($tiempoPasado <= 0) return $precioActual;
        // 2. Generar un cambio aleatorio (puede ser positivo o negativo)
        // mt_rand(-100, 100) / 100 nos da un número entre -1.0 y 1.0
        $direccion = mt_rand(-100, 100) / 100;
        // 3. El cambio total depende del tiempo que pasó
        $delta = $direccion * $volatilidadPorSegundo * $tiempoPasado;
        return abs($precioActual + $delta);
    }

        // Para el endpoint GET /assets/{asset_id}/history/{quantity}
    public static function getHistoryForAsset($asset_id, $limit) {
        $db = DB::getConnection();
        // Seleccionamos solo los datos no sensibles que pide el TP, ordenados por fecha más reciente.
        $stmt = $db->prepare("SELECT transaction_date, transaction_type, quantity, price_per_unit 
                              FROM transactions 
                              WHERE asset_id = ? 
                              ORDER BY transaction_date DESC 
                              LIMIT ?");
        // Es importante especificar el tipo de dato para el LIMIT en sentencias preparadas.
        $stmt->bindValue(1, $asset_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
