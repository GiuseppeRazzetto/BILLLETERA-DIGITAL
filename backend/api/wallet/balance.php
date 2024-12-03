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
    error_log("Balance.php - Iniciando...");
    
    session_start();
    error_log("Balance.php - Sesión iniciada");
    
    // Verificar el token de la sesión
    $user = requireAuthentication($conn);
    error_log("Balance.php - Usuario autenticado: " . json_encode($user));
    
    if (!$user) {
        throw new Exception('No hay sesión activa');
    }
    
    $user_id = $user['id'];
    error_log("Balance.php - User ID: " . $user_id);
    
    // Verificar conexión a la base de datos
    if (!$conn) {
        error_log("Balance.php - Error: No hay conexión a la base de datos");
        throw new Exception("No hay conexión a la base de datos");
    }
    
    error_log("Balance.php - Conexión a base de datos establecida");
    
    // Obtener el wallet del usuario
    $stmt = $conn->prepare("SELECT id, balance FROM wallets WHERE user_id = ?");
    if (!$stmt) {
        error_log("Balance.php - Error preparando consulta: " . $conn->error);
        throw new Exception("Error preparando consulta: " . $conn->error);
    }
    
    error_log("Balance.php - Consulta preparada");
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log("Balance.php - Error ejecutando consulta: " . $stmt->error);
        throw new Exception("Error ejecutando consulta: " . $stmt->error);
    }
    
    error_log("Balance.php - Consulta ejecutada");
    
    $result = $stmt->get_result();
    error_log("Balance.php - Número de filas: " . $result->num_rows);
    
    // Si no existe wallet, crear uno
    if ($result->num_rows === 0) {
        error_log("Balance.php - Creando nuevo wallet para usuario " . $user_id);
        
        $create_stmt = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
        if (!$create_stmt) {
            error_log("Balance.php - Error preparando inserción: " . $conn->error);
            throw new Exception("Error preparando inserción: " . $conn->error);
        }
        
        $create_stmt->bind_param("i", $user_id);
        if (!$create_stmt->execute()) {
            error_log("Balance.php - Error creando wallet: " . $create_stmt->error);
            throw new Exception("Error creando wallet: " . $create_stmt->error);
        }
        
        $wallet_id = $create_stmt->insert_id;
        $balance = "0.00";
        error_log("Balance.php - Nuevo wallet creado con ID: " . $wallet_id);
    } else {
        $wallet = $result->fetch_assoc();
        $wallet_id = $wallet['id'];
        $balance = $wallet['balance'];
        error_log("Balance.php - Wallet existente encontrado. ID: " . $wallet_id . ", Balance: " . $balance);
    }
    
    // Obtener las últimas transacciones
    $trans_stmt = $conn->prepare("
        SELECT 
            t.id, 
            t.tipo, 
            t.monto, 
            t.descripcion,
            t.fecha as created_at, 
            t.wallet_from_id, 
            t.wallet_to_id,
            w.user_id as current_user_id,
            u_from.correo_electronico as from_email,
            u_to.correo_electronico as to_email
        FROM transactions t
        JOIN wallets w ON t.wallet_id = w.id
        LEFT JOIN wallets w_from ON t.wallet_from_id = w_from.id
        LEFT JOIN users u_from ON w_from.user_id = u_from.id
        LEFT JOIN wallets w_to ON t.wallet_to_id = w_to.id
        LEFT JOIN users u_to ON w_to.user_id = u_to.id
        WHERE t.wallet_id = ? 
        ORDER BY t.fecha DESC 
        LIMIT 5
    ");
    
    if (!$trans_stmt) {
        error_log("Balance.php - Error preparando consulta de transacciones: " . $conn->error);
        throw new Exception("Error preparando consulta de transacciones: " . $conn->error);
    }
    
    $trans_stmt->bind_param("i", $wallet_id);
    if (!$trans_stmt->execute()) {
        error_log("Balance.php - Error consultando transacciones: " . $trans_stmt->error);
        throw new Exception("Error consultando transacciones: " . $trans_stmt->error);
    }
    
    error_log("Balance.php - Consulta de transacciones ejecutada");
    
    $trans_result = $trans_stmt->get_result();
    $transactions = [];
    
    while ($row = $trans_result->fetch_assoc()) {
        error_log("Balance.php - Procesando transacción: " . json_encode($row));
        $transactions[] = [
            'id' => $row['id'],
            'tipo' => $row['tipo'],
            'monto' => $row['monto'],
            'descripcion' => $row['descripcion'],
            'created_at' => $row['created_at'],
            'wallet_from_id' => $row['wallet_from_id'],
            'wallet_to_id' => $row['wallet_to_id'],
            'from_email' => $row['from_email'],
            'to_email' => $row['to_email']
        ];
    }
    
    error_log("Balance.php - Transacciones recuperadas: " . count($transactions));
    
    $response = [
        'success' => true,
        'data' => [
            'wallet_id' => $wallet_id,
            'balance' => $balance,
            'transactions' => $transactions
        ]
    ];
    
    error_log("Balance.php - Respuesta final: " . json_encode($response));
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error en balance.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
