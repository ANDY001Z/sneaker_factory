#Comprueba si el backend y la base de datos están funcionando correctamente y hay conexion entre ellos. Devuelve un JSON con el estado de los servicios.

<?php
header('Content-Type: application/json');

$response = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'services' => [
        'backend' => 'up',
        'database' => 'down'
    ]
];

$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '5432';
$db   = getenv('DB_NAME') ?: 'sneaker_db';
$user = getenv('DB_USER') ?: 'sneaker_user';
$pass = getenv('DB_PASSWORD') ?: 'sneaker_pass';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 2]);
    $response['services']['database'] = 'up';
    http_response_code(200);
} catch (PDOException $e) {
    $response['status'] = 'error';
    $response['message'] = 'Database connection failed';
    http_response_code(500);
}

echo json_encode($response, JSON_PRETTY_PRINT);