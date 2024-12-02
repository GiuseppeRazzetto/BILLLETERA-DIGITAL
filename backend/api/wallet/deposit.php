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
    error_log("deposit.php - Iniciando depósito");
    
    // Verificar conexión a la base de datos
    if (!$conn) {
        error_log("deposit.php - Error: No hay conexión a la base de datos");
        throw new Exception("No hay conexión a la base de datos");
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    error_log("deposit.php - Datos recibidos: " . json_encode($data));
    
    if (!isset($data['monto']) || !isset($data['token_personal'])) {
        error_log("deposit.php - Error: Datos incompletos");
        throw new Exception('Datos incompletos');
    }

    if (!is_numeric($data['monto']) || $data['monto'] <= 0) {
        error_log("deposit.php - Error: Monto inválido");
        throw new Exception('Monto inválido');
    }

    $user = requireAuthentication($conn);
    error_log("deposit.php - Usuario autenticado: " . json_encode($user));
    
    verifyPersonalToken($conn, $user['id'], $data['token_personal']);
    error_log("deposit.php - Token personal verificado");

    // Iniciar transacción
    $conn->begin_transaction();
    error_log("deposit.php - Transacción iniciada");

    try {
        // Actualizar balance
        $stmt = $conn->prepare('
            UPDATE wallets 
            SET balance = balance + ? 
            WHERE user_id = ?
        ');
        $stmt->bind_param("di", $data['monto'], $user['id']);
        $stmt->execute();
        error_log("deposit.php - Balance actualizado");

        // Registrar transacción
        $stmt = $conn->prepare('
            INSERT INTO transactions (wallet_id, tipo, monto, descripcion) 
            VALUES (?, ?, ?, ?)
        ');
        $tipo = 'deposito';
        $descripcion = 'Depósito a la billetera';
        $stmt->bind_param("isds", $user['wallet_id'], $tipo, $data['monto'], $descripcion);
        $stmt->execute();
        error_log("deposit.php - Transacción registrada");

        // Obtener nuevo balance
        $stmt = $conn->prepare('SELECT balance FROM wallets WHERE user_id = ?');
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $nuevo_balance = $stmt->get_result()->fetch_assoc()['balance'];
        error_log("deposit.php - Nuevo balance obtenido");

        $conn->commit();
        error_log("deposit.php - Transacción completada exitosamente");

        echo json_encode([
            'success' => true,
            'message' => 'Depósito realizado con éxito',
            'data' => [
                'nuevo_balance' => $nuevo_balance,
                'monto_depositado' => $data['monto']
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("deposit.php - Error en la transacción: " . $e->getMessage());
        throw new Exception('Error al procesar el depósito');
    }

} catch (Exception $e) {
    error_log("Error en deposit.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
