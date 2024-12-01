<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

require_once '../config/database.php';

try {
    // Obtener el token del header Authorization
    $headers = getallheaders();
    $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($auth_header)) {
        throw new Exception('Token no proporcionado');
    }

    // El formato debe ser "Bearer <token>"
    $token_parts = explode(' ', $auth_header);
    if (count($token_parts) !== 2 || $token_parts[0] !== 'Bearer') {
        throw new Exception('Formato de token inválido');
    }

    $session_token = $token_parts[1];

    // Eliminar el token de sesión de la base de datos
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username_db,
        $password_db,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $conn->prepare("UPDATE users SET session_token = NULL WHERE session_token = ?");
    $stmt->execute([$session_token]);

    echo json_encode([
        'success' => true,
        'message' => 'Sesión cerrada correctamente'
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log('Error de base de datos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor'
    ]);
}
