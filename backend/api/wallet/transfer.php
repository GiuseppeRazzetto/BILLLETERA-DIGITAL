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
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['monto']) || !isset($data['token_personal']) || !isset($data['email_destino'])) {
        throw new Exception('Datos incompletos');
    }

    if (!is_numeric($data['monto']) || $data['monto'] <= 0) {
        throw new Exception('Monto inválido');
    }

    $user = requireAuthentication($conn);
    verifyPersonalToken($conn, $user['id'], $data['token_personal']);

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Obtener usuario destino
        $stmt = $conn->prepare('
            SELECT u.id, w.id as wallet_id 
            FROM users u 
            JOIN wallets w ON u.id = w.user_id 
            WHERE u.correo_electronico = ?
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

        // Verificar balance suficiente
        $stmt = $conn->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_balance = $result->fetch_assoc();

        if ($current_balance['balance'] < $data['monto']) {
            throw new Exception('Saldo insuficiente');
        }

        // Restar de la billetera origen
        $stmt = $conn->prepare('
            UPDATE wallets 
            SET balance = balance - ? 
            WHERE user_id = ?
        ');
        $stmt->bind_param('di', $data['monto'], $user['id']);
        $stmt->execute();

        // Sumar a la billetera destino
        $stmt = $conn->prepare('
            UPDATE wallets 
            SET balance = balance + ? 
            WHERE user_id = ?
        ');
        $stmt->bind_param('di', $data['monto'], $destino['id']);
        $stmt->execute();

        // Registrar transacción
        $descripcion = $data['descripcion'] ?? 'Transferencia enviada a ' . $data['email_destino'];
        $stmt = $conn->prepare('
            INSERT INTO transactions (wallet_id, tipo, monto, descripcion, wallet_from_id, wallet_to_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->bind_param('idsiii', $user['wallet_id'], 'transferencia', $data['monto'], $descripcion, $user['wallet_id'], $destino['wallet_id']);
        $stmt->execute();

        // Obtener nuevo balance
        $stmt = $conn->prepare('SELECT balance FROM wallets WHERE user_id = ?');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $nuevo_balance = $result->fetch_assoc();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Transferencia realizada correctamente',
            'data' => [
                'nuevo_balance' => $nuevo_balance['balance'],
                'monto_transferido' => $data['monto'],
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
