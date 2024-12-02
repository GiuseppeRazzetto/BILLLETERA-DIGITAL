<?php
require_once '../../utils/cors.php';
require_once '../../config/database.prod.php';
require_once '../../utils/auth_utils.php';

header('Content-Type: application/json');

try {
    // Verificar el token de la sesión
    $user = validateSessionToken();
    
    if ($user) {
        // Obtener información de la billetera
        $wallet_query = "SELECT w.*, 
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
        
        $stmt = $conn->prepare($wallet_query);
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $wallet = $result->fetch_assoc();

        if ($wallet) {
            $transactions = json_decode($wallet['recent_transactions'] ?? '[]');
            echo json_encode([
                'success' => true,
                'data' => [
                    'balance' => $wallet['balance'],
                    'transactions' => $transactions
                ]
            ]);
        } else {
            throw new Exception('Billetera no encontrada');
        }
    } else {
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
