<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://giusepperazzetto.github.io');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.prod.php';
require_once '../../utils/auth_utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['monto']) || !isset($data['token_personal'])) {
        throw new Exception('Datos incompletos');
    }

    if (!is_numeric($data['monto']) || $data['monto'] <= 0) {
        throw new Exception('Monto inválido');
    }

    $user = requireAuthentication($pdo);
    verifyPersonalToken($pdo, $user['id'], $data['token_personal']);

    // Iniciar transacción
    $pdo->beginTransaction();

    try {
        // Actualizar balance
        $stmt = $pdo->prepare('
            UPDATE wallets 
            SET balance = balance + ? 
            WHERE user_id = ?
        ');
        $stmt->execute([$data['monto'], $user['id']]);

        // Registrar transacción
        $stmt = $pdo->prepare('
            INSERT INTO transactions (wallet_id, tipo, monto, descripcion) 
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([
            $user['wallet_id'],
            'deposito',
            $data['monto'],
            'Depósito a la billetera'
        ]);

        // Obtener nuevo balance
        $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $nuevo_balance = $stmt->fetchColumn();

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Depósito realizado correctamente',
            'data' => [
                'nuevo_balance' => $nuevo_balance,
                'monto_depositado' => $data['monto']
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
