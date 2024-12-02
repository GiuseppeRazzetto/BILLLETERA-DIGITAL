<?php
require_once '../../utils/cors.php';
require_once '../../config/database.prod.php';
require_once '../../utils/auth_utils.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['email']) || !isset($data['password'])) {
        throw new Exception('Datos incompletos');
    }

    // Verificar intentos de login
    $query = "SELECT COUNT(*) as attempts FROM login_attempts WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $data['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $attempts = $result->fetch_assoc()['attempts'];

    if ($attempts >= 5) {
        throw new Exception('Demasiados intentos fallidos. Por favor, espere 15 minutos.');
    }

    // Buscar usuario
    $query = "SELECT id, contrasena_hash, nombre, apellido, token_personal FROM users WHERE correo_electronico = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $data['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || !password_verify($data['password'], $user['contrasena_hash'])) {
        // Registrar intento fallido
        $query = "INSERT INTO login_attempts (email, attempt_time) VALUES (?, NOW())";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $data['email']);
        $stmt->execute();
        
        throw new Exception('Credenciales inválidas');
    }

    // Generar token de sesión
    $session_token = bin2hex(random_bytes(32));
    $expiration = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Guardar sesión
    $query = "INSERT INTO sessions (user_id, token, expiration) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user['id'], $session_token, $expiration);
    $stmt->execute();

    // Devolver respuesta exitosa
    echo json_encode([
        'success' => true,
        'data' => [
            'session_token' => $session_token,
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'apellido' => $user['apellido'],
                'email' => $data['email']
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
