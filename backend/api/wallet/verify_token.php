<?php
require_once '../../config/database.prod.php';
require_once '../../utils/auth_utils.php';
require_once '../../utils/cors.php';

// Deshabilitar la visualización de errores
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Asegurarse de que no haya salida antes de los headers
if (ob_get_level()) ob_end_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://giusepperazzetto.github.io');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 3600');

// Manejar solicitud OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST después de OPTIONS
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['token_personal'])) {
        throw new Exception('Token personal no proporcionado');
    }

    $user = requireAuthentication($pdo);
    verifyPersonalToken($pdo, $user['id'], $data['token_personal']);

    echo json_encode([
        'success' => true,
        'message' => 'Token personal verificado correctamente',
        'data' => [
            'user_id' => $user['id'],
            'wallet_id' => $user['wallet_id']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en verify_token.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
