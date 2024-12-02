<?php
require_once '../../config/database.prod.php';
require_once '../../utils/cors.php';
require_once '../../utils/auth_utils.php';

// Asegurarse de que no haya salida antes de los headers
ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://giusepperazzetto.github.io');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Verificar si hay una sesión activa
    $user = validateSessionToken();
    
    if (!$user) {
        throw new Exception('No hay sesión activa');
    }
    
    $user_id = $user['id'];
    
    // Obtener el wallet_id del usuario
    $stmt = $conn->prepare("SELECT w.id, w.balance, w.user_id FROM wallets w WHERE w.user_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        error_log("Error ejecutando consulta de wallet: " . $stmt->error);
        throw new Exception("Error al consultar el wallet");
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Si no existe wallet, crear uno
        $create_wallet = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
        $create_wallet->bind_param("i", $user_id);
        
        if (!$create_wallet->execute()) {
            error_log("Error creando wallet: " . $create_wallet->error);
            throw new Exception("Error al crear wallet");
        }
        
        $wallet_id = $create_wallet->insert_id;
        $balance = "0.00";
    } else {
        $wallet = $result->fetch_assoc();
        $wallet_id = $wallet['id'];
        $balance = $wallet['balance'];
    }
    
    // Obtener las últimas 5 transacciones
    $trans_stmt = $conn->prepare("
        SELECT id, type, amount, description, created_at 
        FROM transactions 
        WHERE wallet_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $trans_stmt->bind_param("i", $wallet_id);
    
    if (!$trans_stmt->execute()) {
        error_log("Error consultando transacciones: " . $trans_stmt->error);
        throw new Exception("Error al consultar transacciones");
    }
    
    $trans_result = $trans_stmt->get_result();
    $transactions = [];
    
    while ($row = $trans_result->fetch_assoc()) {
        $transactions[] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'amount' => $row['amount'],
            'description' => $row['description'],
            'created_at' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'wallet_id' => $wallet_id,
            'balance' => $balance,
            'transactions' => $transactions
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error en balance.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
