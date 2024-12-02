<?php
require_once '../../utils/cors.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://giusepperazzetto.github.io');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.prod.php';

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
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    $stmt->execute([$data['email']]);
    $attempts = $stmt->fetchColumn();

    if ($attempts >= 5) {
        throw new Exception('Demasiados intentos fallidos. Por favor, espere 15 minutos.');
    }

    // Buscar usuario
    $stmt = $pdo->prepare('SELECT id, contrasena_hash, nombre, apellido FROM users WHERE correo_electronico = ?');
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($data['password'], $user['contrasena_hash'])) {
        // Registrar intento fallido
        $stmt = $pdo->prepare('INSERT INTO login_attempts (email, attempt_time) VALUES (?, NOW())');
        $stmt->execute([$data['email']]);
        
        throw new Exception('Credenciales inválidas');
    }

    // Generar token de sesión
    $session_token = bin2hex(random_bytes(32));
    $expiration = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Guardar sesión
    $stmt = $pdo->prepare('INSERT INTO sessions (user_id, token, expiration) VALUES (?, ?, ?)');
    $stmt->execute([$user['id'], $session_token, $expiration]);

    // Actualizar token en usuario
    $stmt = $pdo->prepare('UPDATE users SET session_token = ? WHERE id = ?');
    $stmt->execute([$session_token, $user['id']]);

    // Obtener wallet del usuario
    $stmt = $pdo->prepare('SELECT id, balance FROM wallets WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Login exitoso',
        'data' => [
            'user_id' => $user['id'],
            'nombre' => $user['nombre'],
            'apellido' => $user['apellido'],
            'session_token' => $session_token,
            'wallet_id' => $wallet['id'],
            'balance' => $wallet['balance']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
