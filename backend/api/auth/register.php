<?php
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

    if (!$data || !isset($data['email']) || !isset($data['password']) || !isset($data['nombre']) || 
        !isset($data['apellido']) || !isset($data['token_personal'])) {
        throw new Exception('Datos incompletos');
    }

    // Validar email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }

    // Validar token personal (4 dígitos)
    if (!preg_match('/^[0-9]{4}$/', $data['token_personal'])) {
        throw new Exception('El token personal debe ser de 4 dígitos');
    }

    // Verificar si el email ya existe
    $stmt = $pdo->prepare('SELECT id FROM users WHERE correo_electronico = ?');
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        throw new Exception('El email ya está registrado');
    }

    // Generar hash de la contraseña
    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

    // Insertar nuevo usuario
    $stmt = $pdo->prepare('INSERT INTO users (correo_electronico, contrasena_hash, nombre, apellido, token_personal) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $data['email'],
        $password_hash,
        $data['nombre'],
        $data['apellido'],
        $data['token_personal']
    ]);

    $userId = $pdo->lastInsertId();

    // Crear wallet para el usuario
    $stmt = $pdo->prepare('INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)');
    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Usuario registrado correctamente'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
