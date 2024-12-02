<?php
// Deshabilitar la salida de errores HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../../config/database.prod.php';
require_once '../../utils/cors.php';
require_once '../../utils/auth_utils.php';

// Configurar headers CORS y JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://giusepperazzetto.github.io');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Función para enviar respuesta JSON
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Función para preparar consulta SQL de manera segura
function prepareStatement($conn, $query) {
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        error_log("Error en preparación de consulta SQL: " . $conn->error);
        throw new Exception("Error en la base de datos: " . $conn->error);
    }
    return $stmt;
}

// Manejar solicitud OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJsonResponse(true, 'OK');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Método no permitido', null, 405);
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
    if (!$conn->begin_transaction()) {
        throw new Exception('Error al iniciar la transacción: ' . $conn->error);
    }

    try {
        // Obtener usuario destino
        $stmt = prepareStatement($conn, '
            SELECT u.id, w.id as wallet_id, u.correo_electronico as email 
            FROM users u 
            JOIN wallets w ON u.id = w.user_id 
            WHERE u.correo_electronico = ?
        ');
        
        $stmt->bind_param('s', $data['email_destino']);
        if (!$stmt->execute()) {
            throw new Exception('Error al buscar usuario destino: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $destinatario = $result->fetch_assoc();

        if (!$destinatario) {
            throw new Exception('Usuario destino no encontrado');
        }

        error_log("Transfer.php - Usuario destino encontrado: " . json_encode($destinatario));

        if ($destinatario['id'] === $user['id']) {
            throw new Exception('No puedes transferir a tu propia billetera');
        }

        // Verificar fondos suficientes
        $stmt = prepareStatement($conn, 'SELECT balance FROM wallets WHERE id = ?');
        $stmt->bind_param('i', $user['wallet_id']);
        if (!$stmt->execute()) {
            throw new Exception('Error al verificar balance: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $wallet = $result->fetch_assoc();

        if ($wallet['balance'] < $data['monto']) {
            throw new Exception('Fondos insuficientes');
        }

        error_log("Transfer.php - Actualizando balances");

        // Actualizar balance del remitente
        $stmt = prepareStatement($conn, 'UPDATE wallets SET balance = balance - ? WHERE id = ?');
        $stmt->bind_param('di', $data['monto'], $user['wallet_id']);
        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar balance del remitente: ' . $stmt->error);
        }

        // Actualizar balance del destinatario
        $stmt = prepareStatement($conn, 'UPDATE wallets SET balance = balance + ? WHERE id = ?');
        $stmt->bind_param('di', $data['monto'], $destinatario['wallet_id']);
        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar balance del destinatario: ' . $stmt->error);
        }

        // Registrar transacción para el emisor (monto negativo)
        $stmt = prepareStatement($conn, 'INSERT INTO transactions (wallet_id, monto, tipo, descripcion, fecha, wallet_from_id, wallet_to_id) VALUES (?, ?, ?, ?, NOW(), ?, ?)');
        $tipo_transaccion = "transferencia";
        $monto_negativo = -$data['monto'];
        $stmt->bind_param('idsiii', $user['wallet_id'], $monto_negativo, $tipo_transaccion, $data['descripcion'], $user['wallet_id'], $destinatario['wallet_id']);
        if (!$stmt->execute()) {
            throw new Exception("Error al registrar la transacción del emisor: " . $stmt->error);
        }

        // Registrar transacción para el receptor (monto positivo)
        $stmt = prepareStatement($conn, 'INSERT INTO transactions (wallet_id, monto, tipo, descripcion, fecha, wallet_from_id, wallet_to_id) VALUES (?, ?, ?, ?, NOW(), ?, ?)');
        $tipo_transaccion = "transferencia";
        $monto_positivo = $data['monto'];
        $stmt->bind_param('idsiii', $destinatario['wallet_id'], $monto_positivo, $tipo_transaccion, $data['descripcion'], $user['wallet_id'], $destinatario['wallet_id']);
        if (!$stmt->execute()) {
            throw new Exception("Error al registrar la transacción del receptor: " . $stmt->error);
        }

        // Obtener el nuevo balance
        $stmt = prepareStatement($conn, 'SELECT balance FROM wallets WHERE id = ?');
        $stmt->bind_param('i', $user['wallet_id']);
        if (!$stmt->execute()) {
            throw new Exception('Error al obtener nuevo balance: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $nuevo_balance = $result->fetch_assoc();

        error_log("Transfer.php - Transacción completada con éxito");

        $responseData = [
            'monto' => floatval($data['monto']),
            'destinatario' => $destinatario['email'],
            'nuevo_balance' => floatval($nuevo_balance['balance'])
        ];

        if (!$conn->commit()) {
            throw new Exception('Error al confirmar la transacción: ' . $conn->error);
        }

        error_log("Transfer.php - Enviando respuesta: " . json_encode($responseData));
        sendJsonResponse(true, 'Transferencia realizada con éxito', $responseData);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Throwable $e) {
    error_log("Error en transfer.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    sendJsonResponse(false, $e->getMessage(), null, 400);
}
