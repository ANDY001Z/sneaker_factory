<?php

// Autoloader básico (más adelante podemos usar Composer)
spl_autoload_register(function ($class) {
    // Convierte el namespace (ej. Config\Database) en ruta de archivo (ej. ../config/Database.php)
    $path = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    
    if (file_exists($path)) {
        require_once $path;
    }
});

use Config\Database;

// 1. Configuración de Cabeceras Globales (API REST)
header("Access-Control-Allow-Origin: *"); // Permite peticiones desde el frontend
header("Content-Type: application/json; charset=UTF-8"); // Siempre devolveremos JSON
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Variables para centralizar la respuesta
$httpCode = 200;
$responseBody = null;

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // Si es una petición OPTIONS (Pre-flight de CORS), no procesamos rutas
    if ($method === 'OPTIONS') {
        $httpCode = 200;
    } else {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // --- ENRUTADOR BÁSICO ---
        // Endpoint de Healthcheck exigido
        if ($uri === '/health' && $method === 'GET') {
            $db = Database::getConnection(); // Probamos la conexión a PostgreSQL
            
            $httpCode = 200;
            $responseBody = [
                "status" => "success",
                "message" => "La API de Sneaker Factory está funcionando correctamente.",
                "database" => "Conectada"
            ];
        } 
        // Si la ruta no coincide con ninguna, asignamos 404
        else {
            $httpCode = 404;
            $responseBody = [
                "status" => "error", 
                "message" => "Ruta no encontrada (404)."
            ];
        }
    }
} catch (Exception $e) {
    // Captura global de errores
    $httpCode = 500;
    $responseBody = [
        "status" => "error",
        "message" => $e->getMessage()
    ];
} finally {
    // 2. Emisión de la respuesta (Punto de salida único)
    http_response_code($httpCode);
    
    if ($responseBody !== null) {
        echo json_encode($responseBody);
    }
}