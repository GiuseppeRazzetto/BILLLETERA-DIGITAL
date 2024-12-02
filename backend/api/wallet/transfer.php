<?php
require_once '../../config/database.prod.php';
require_once '../../utils/cors.php';
require_once '../../utils/auth_utils.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://giusepperazzetto.github.io');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar solicitud OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    error_log("Transfer.php - Iniciando transferencia");
    
    $input = file_get_contents('php://input');
    error_log("Transfer.php - Datos recibidos: " . $input);
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
    }
    
    if (!isset($data['monto']) || !isset($data['token_personal']) || !isset($data['email_destino'])) {
        throw new Exception('Datos incompletos: ' . implode(', ', array_keys($data)));
    }

    if (!is_numeric($data['monto']) || $data['monto'] <= 0) {
        throw new Exception('Monto inválido: ' . $data['monto']);
    }

    error_log("Transfer.php - Autenticando usuario");
    $user = requireAuthentication($conn);
    verifyPersonalToken($conn, $user['id'], $data['token_personal']);

    error_log("Transfer.php - Usuario autenticado: " . json_encode($user));

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Obtener usuario destino
        $stmt = $conn->prepare('
            SELECT u.id, w.id as wallet_id 
            FROM users u 
            JOIN wallets w ON u.id = w.user_id 
            WHERE u.email = ?
        ');
        $stmt->bind_param('s', $data['email_destino']);
        $stmt->execute();
        $result = $stmt->get_result();
        $destino = $result->fetch_assoc();

        if (!$destino) {
            throw new Exception('Usuario destino no encontrado');
        }

        if ($destino['id'] === $user['id']) {
            throw new Exception('No puedes transferir a tu propia billetera');
        }

        // Verificar fondos suficientes
        $stmt = $conn->prepare('SELECT balance FROM wallets WHERE id = ?');
        $stmt->bind_param('i', $user['wallet_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $wallet = $result->fetch_assoc();

        if ($wallet['balance'] < $data['monto']) {
            throw new Exception('Fondos insuficientes');
        }

        error_log("Transfer.php - Actualizando balances");

        // Actualizar balance del remitente
        $stmt = $conn->prepare('UPDATE wallets SET balance = balance - ? WHERE id = ?');
        $stmt->bind_param('di', $data['monto'], $user['wallet_id']);
        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar balance del remitente: ' . $stmt->error);
        }

        // Actualizar balance del destinatario
        $stmt = $conn->prepare('UPDATE wallets SET balance = balance + ? WHERE id = ?');
        $stmt->bind_param('di', $data['monto'], $destino['wallet_id']);
        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar balance del destinatario: ' . $stmt->error);
        }

        // Registrar transacción
        $descripcion = isset($data['descripcion']) ? $data['descripcion'] : 'Transferencia';
        $stmt = $conn->prepare('
            INSERT INTO transactions (wallet_id, tipo, monto, descripcion, wallet_from_id, wallet_to_id) 
            VALUES (?, "transferencia", ?, ?, ?, ?)
        ');
        $stmt->bind_param('idsii', $user['wallet_id'], $data['monto'], $descripcion, $user['wallet_id'], $destino['wallet_id']);
        if (!$stmt->execute()) {
            throw new Exception('Error al registrar la transacción: ' . $stmt->error);
        }

        error_log("Transfer.php - Transacción completada con éxito");

        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Transferencia realizada con éxito',
            'data' => [
                'monto' => $data['monto'],
                'destinatario' => $data['email_destino']
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error en transfer.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
