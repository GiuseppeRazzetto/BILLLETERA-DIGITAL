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
    // Verificar el token de sesión
    $headers = getallheaders();
    $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($auth_header)) {
        throw new Exception('Token no proporcionado');
    }

    $token_parts = explode(' ', $auth_header);
    if (count($token_parts) !== 2 || $token_parts[0] !== 'Bearer') {
        throw new Exception('Formato de token inválido');
    }

    $session_token = $token_parts[1];

    // Obtener y validar el nuevo token
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['new_token']) || !isset($data['current_password'])) {
        throw new Exception('Datos incompletos');
    }

    $new_token = trim($data['new_token']);
    $current_password = trim($data['current_password']);

    if (!preg_match('/^\d{4}$/', $new_token)) {
        throw new Exception('El nuevo token debe ser de 4 dígitos');
    }

    // Conectar a la base de datos
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username_db,
        $password_db,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verificar la sesión y obtener datos del usuario
    $stmt = $conn->prepare("SELECT id, contrasena_hash FROM users WHERE session_token = ?");
    $stmt->execute([$session_token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Sesión inválida');
    }

    // Verificar la contraseña actual
    if (!password_verify($current_password, $user['contrasena_hash'])) {
        throw new Exception('Contraseña incorrecta');
    }

    // Actualizar el token personal
    $stmt = $conn->prepare("UPDATE users SET token_personal = ? WHERE id = ?");
    $stmt->execute([$new_token, $user['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Token personal actualizado correctamente'
    ]);

} catch (Exception $e) {
    http_response_code(400);
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
