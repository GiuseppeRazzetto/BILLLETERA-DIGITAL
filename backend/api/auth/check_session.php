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
        $wallet_query = "SELECT * FROM wallets WHERE user_id = ?";
        $stmt = $conn->prepare($wallet_query);
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $wallet_result = $stmt->get_result();
        $wallet = $wallet_result->fetch_assoc();

        if (!$wallet) {
            throw new Exception('No se encontró la billetera del usuario');
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'email' => $user['email'],
                'nombre' => $user['nombre'],
                'apellido' => $user['apellido'],
                'wallet_id' => $wallet['id'],
                'balance' => $wallet['balance']
            ]
        ]);
    } else {
        throw new Exception('Sesión inválida');
    }
} catch (Exception $e) {
    error_log("Error en check_session.php: " . $e->getMessage());
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
