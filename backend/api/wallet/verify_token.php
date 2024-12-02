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
    error_log("verify_token.php - Iniciando verificación de token");
    
    // Verificar conexión a la base de datos
    if (!$conn) {
        error_log("verify_token.php - Error: No hay conexión a la base de datos");
        throw new Exception("No hay conexión a la base de datos");
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    error_log("verify_token.php - Datos recibidos: " . json_encode($data));
    
    if (!isset($data['token_personal'])) {
        error_log("verify_token.php - Error: Token personal no proporcionado");
        throw new Exception('Token personal no proporcionado');
    }

    $user = requireAuthentication($conn);
    error_log("verify_token.php - Usuario autenticado: " . json_encode($user));
    
    verifyPersonalToken($conn, $user['id'], $data['token_personal']);
    error_log("verify_token.php - Token personal verificado correctamente");

    echo json_encode([
        'success' => true,
        'message' => 'Token personal verificado correctamente',
        'data' => [
            'user_id' => $user['id'],
            'wallet_id' => $user['wallet_id']
        ]
    ]);

} catch (Exception $e) {
    error_log("Error en verify_token.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
