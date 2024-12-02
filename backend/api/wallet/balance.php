<?php
require_once '../../utils/cors.php';
require_once '../../config/database.prod.php';
require_once '../../utils/auth_utils.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://giusepperazzetto.github.io');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    error_log("Balance.php: Iniciando...");
    
    // Verificar el token de la sesión
    $user = validateSessionToken();
    error_log("Balance.php: Usuario validado: " . json_encode($user));
    
    if ($user) {
        // Obtener información de la billetera
        $wallet_query = "SELECT w.id, w.balance, 
            (SELECT JSON_ARRAYAGG(
                JSON_OBJECT(
                    'id', t.id,
                    'type', t.type,
                    'amount', t.amount,
                    'description', t.description,
                    'created_at', t.created_at
                )
            )
            FROM transactions t 
            WHERE t.wallet_id = w.id 
            ORDER BY t.created_at DESC 
            LIMIT 10) as recent_transactions
        FROM wallets w 
        WHERE w.user_id = ?";
        
        error_log("Balance.php: Ejecutando query para user_id: " . $user['id']);
        
        $stmt = $conn->prepare($wallet_query);
        if (!$stmt) {
            error_log("Balance.php: Error en prepare: " . $conn->error);
            throw new Exception('Error al preparar la consulta');
        }
        
        $stmt->bind_param("i", $user['id']);
        if (!$stmt->execute()) {
            error_log("Balance.php: Error en execute: " . $stmt->error);
            throw new Exception('Error al ejecutar la consulta');
        }
        
        $result = $stmt->get_result();
        $wallet = $result->fetch_assoc();
        
        error_log("Balance.php: Resultado de wallet: " . json_encode($wallet));

        if ($wallet) {
            $transactions = json_decode($wallet['recent_transactions'] ?? '[]');
            $response = [
                'success' => true,
                'data' => [
                    'balance' => $wallet['balance'],
                    'transactions' => $transactions
                ]
            ];
            error_log("Balance.php: Respuesta exitosa: " . json_encode($response));
            echo json_encode($response);
        } else {
            error_log("Balance.php: No se encontró la billetera para el usuario: " . $user['id']);
            throw new Exception('Billetera no encontrada');
        }
    } else {
        error_log("Balance.php: Usuario no autorizado");
        throw new Exception('Usuario no autorizado');
    }
} catch (Exception $e) {
    error_log("Error en balance.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
