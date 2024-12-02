<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getUserFromToken($conn, $token) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE session_token = ?");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getWalletData($conn, $user_id) {
    // Obtener datos de la billetera
    $stmt = $conn->prepare("
        SELECT w.id, w.balance, w.ultima_actualizacion
        FROM wallets w
        WHERE w.user_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getTransactions($conn, $wallet_id) {
    // Obtener todas las transacciones relacionadas con esta billetera
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            CASE
                WHEN t.tipo = 'transferencia' AND t.wallet_from_id = ? THEN 'enviada'
                WHEN t.tipo = 'transferencia' AND t.wallet_to_id = ? THEN 'recibida'
                ELSE t.tipo
            END as transaction_direction,
            u_from.correo_electronico as from_email,
            u_to.correo_electronico as to_email
        FROM transactions t
        LEFT JOIN wallets w_from ON t.wallet_from_id = w_from.id
        LEFT JOIN wallets w_to ON t.wallet_to_id = w_to.id
        LEFT JOIN users u_from ON w_from.user_id = u_from.id
        LEFT JOIN users u_to ON w_to.user_id = u_to.id
        WHERE t.wallet_id = ? 
           OR t.wallet_from_id = ? 
           OR t.wallet_to_id = ?
        ORDER BY t.fecha DESC
    ");
    
    $stmt->execute([$wallet_id, $wallet_id, $wallet_id, $wallet_id, $wallet_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        error_log("Iniciando solicitud GET wallet.php");
        $headers = getallheaders();
        $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (empty($auth_header)) {
            throw new Exception('Token no proporcionado');
        }

        $token_parts = explode(' ', $auth_header);
        if (count($token_parts) !== 2 || $token_parts[0] !== 'Bearer') {
            throw new Exception('Formato de token inválido');
        }

        $session_token = $token_parts[1];
        error_log("Token recibido: " . $session_token);

        $conn = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username_db,
            $password_db,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $user = getUserFromToken($conn, $session_token);
        if (!$user) {
            error_log("Usuario no encontrado para el token: " . $session_token);
            throw new Exception('Sesión inválida');
        }
        error_log("Usuario encontrado ID: " . $user['id']);

        $wallet = getWalletData($conn, $user['id']);
        if (!$wallet) {
            error_log("Billetera no encontrada para usuario ID: " . $user['id']);
            throw new Exception('Billetera no encontrada');
        }
        error_log("Billetera encontrada ID: " . $wallet['id'] . " Balance: " . $wallet['balance']);

        $transactions = getTransactions($conn, $wallet['id']);
        error_log("Transacciones encontradas: " . count($transactions));
        error_log("Transacciones: " . json_encode($transactions));

        echo json_encode([
            'success' => true,
            'balance' => $wallet['balance'],
            'transactions' => $transactions
        ]);

    } catch (Exception $e) {
        error_log("Error en wallet.php: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
