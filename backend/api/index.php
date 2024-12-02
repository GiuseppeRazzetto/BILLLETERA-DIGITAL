<?php
// Configuración de CORS
header('Access-Control-Allow-Origin: https://giusepperazzetto.github.io');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Obtener la ruta solicitada
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Si es setup_db.php, ejecutarlo directamente
if (strpos($path, 'setup_db.php') !== false) {
    require_once __DIR__ . '/setup_db.php';
    exit();
}

$endpoint = basename($path, '.php');

// Mapear los endpoints a los archivos correspondientes
$routes = [
    'login' => 'login.php',
    'register' => 'register.php',
    'wallet' => 'wallet.php',
    'transaction' => 'transaction.php',
    'check_session' => 'check_session.php',
    'logout' => 'logout.php',
    'change_token' => 'change_token.php',
    'login_attempts' => 'login_attempts.php'
];

if (isset($routes[$endpoint])) {
    require_once __DIR__ . '/' . $routes[$endpoint];
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}
