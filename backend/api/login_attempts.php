<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

function checkLoginAttempts($email, $conn) {
    // Limpiar intentos antiguos (más de 15 minutos)
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute();

    // Contar intentos recientes
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$email]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['attempts'];
}

function addLoginAttempt($email, $conn) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, attempt_time) VALUES (?, NOW())");
    $stmt->execute([$email]);
}

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username_db,
        $password_db,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Crear tabla si no existe
    $conn->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        attempt_time DATETIME NOT NULL,
        INDEX (email, attempt_time)
    )");

    // Obtener email del query string
    $email = isset($_GET['email']) ? trim($_GET['email']) : '';
    if (empty($email)) {
        throw new Exception('Email no proporcionado');
    }

    $attempts = checkLoginAttempts($email, $conn);
    $remainingAttempts = 5 - $attempts; // 5 intentos máximos

    echo json_encode([
        'success' => true,
        'attempts' => $attempts,
        'remaining_attempts' => max(0, $remainingAttempts),
        'is_locked' => $remainingAttempts <= 0,
        'unlock_time' => $remainingAttempts <= 0 ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null
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
