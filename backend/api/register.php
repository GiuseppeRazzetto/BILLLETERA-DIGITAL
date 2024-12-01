<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validar datos requeridos
    $required_fields = ['email', 'password', 'nombre', 'apellido', 'token_personal'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            throw new Exception("El campo $field es requerido");
        }
    }

    // Validar formato de email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Formato de email inválido');
    }

    // Validar longitud de contraseña
    if (strlen($data['password']) < 6) {
        throw new Exception('La contraseña debe tener al menos 6 caracteres');
    }

    // Asegurarnos de que el token sea un string de 4 dígitos
    $token = trim((string)$data['token_personal']);
    if (!preg_match('/^\d{4}$/', $token)) {
        throw new Exception('El token personal debe ser de 4 dígitos');
    }

    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username_db,
        $password_db,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verificar si el email ya existe
    $stmt = $conn->prepare("SELECT id FROM users WHERE correo_electronico = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        throw new Exception('El email ya está registrado');
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Hash de la contraseña
    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

    // Insertar usuario
    $stmt = $conn->prepare("INSERT INTO users (correo_electronico, contrasena_hash, token_personal, nombre, apellido, telefono) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['email'],
        $password_hash,
        $token,
        $data['nombre'],
        $data['apellido'],
        $data['telefono'] ?? null
    ]);

    $user_id = $conn->lastInsertId();

    // Crear billetera para el usuario
    $stmt = $conn->prepare("INSERT INTO wallets (user_id) VALUES (?)");
    $stmt->execute([$user_id]);

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Usuario registrado exitosamente',
        'user' => [
            'id' => $user_id,
            'email' => $data['email'],
            'nombre' => $data['nombre'],
            'apellido' => $data['apellido']
        ]
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
