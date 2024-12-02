<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://giusepperazzetto.github.io');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.prod.php';
require_once '../../utils/auth_utils.php';

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
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
