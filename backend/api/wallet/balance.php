<?php
require_once '../../config/database.prod.php';
require_once '../../utils/cors.php';
require_once '../../utils/auth_utils.php';

// Deshabilitar la visualización de errores
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Asegurarse de que no haya salida antes de los headers
if (ob_get_level()) ob_end_clean();

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
    error_log("Balance.php - User ID: " . $user_id);
    
    // Obtener el wallet del usuario
    $stmt = $conn->prepare("SELECT id, balance FROM wallets WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception("Error preparando consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Error ejecutando consulta: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    // Si no existe wallet, crear uno
    if ($result->num_rows === 0) {
        $create_stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
        if (!$create_stmt) {
            throw new Exception("Error preparando inserción: " . $conn->error);
        }
        
        $create_stmt->bind_param("i", $user_id);
        if (!$create_stmt->execute()) {
            throw new Exception("Error creando wallet: " . $create_stmt->error);
        }
        
        $wallet_id = $create_stmt->insert_id;
        $balance = "0.00";
    } else {
        $wallet = $result->fetch_assoc();
        $wallet_id = $wallet['id'];
        $balance = $wallet['balance'];
    }
    
    // Obtener las últimas transacciones
    $trans_stmt = $conn->prepare("
        SELECT id, type, amount, description, created_at 
        FROM transactions 
        WHERE wallet_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    if (!$trans_stmt) {
        throw new Exception("Error preparando consulta de transacciones: " . $conn->error);
    }
    
    $trans_stmt->bind_param("i", $wallet_id);
    if (!$trans_stmt->execute()) {
        throw new Exception("Error consultando transacciones: " . $trans_stmt->error);
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
    
    $response = [
        'success' => true,
        'data' => [
            'wallet_id' => $wallet_id,
            'balance' => $balance,
            'transactions' => $transactions
        ]
    ];
    
    error_log("Balance.php - Respuesta: " . json_encode($response));
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error en balance.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
