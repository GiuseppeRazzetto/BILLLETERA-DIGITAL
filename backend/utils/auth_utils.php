<?php

function verifySessionToken($conn, $session_token) {
    error_log("verifySessionToken - Iniciando verificación con token: " . $session_token);
    
    if (!$session_token) {
        error_log("verifySessionToken - Token no proporcionado");
        throw new Exception('Token de sesión no proporcionado');
    }

    $query = "
        SELECT u.id, u.correo_electronico as email, u.nombre, u.apellido, u.token_personal, 
               COALESCE(w.id, 0) as wallet_id, COALESCE(w.balance, '0.00') as balance
        FROM users u 
        LEFT JOIN sessions s ON u.id = s.user_id 
        LEFT JOIN wallets w ON u.id = w.user_id 
        WHERE s.token = ? AND s.expiration > NOW()
    ";
    
    error_log("verifySessionToken - Ejecutando query");
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("verifySessionToken - Error preparando query: " . $conn->error);
        throw new Exception('Error preparando consulta');
    }
    
    $stmt->bind_param("s", $session_token);
    if (!$stmt->execute()) {
        error_log("verifySessionToken - Error ejecutando query: " . $stmt->error);
        throw new Exception('Error ejecutando consulta');
    }
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        error_log("verifySessionToken - No se encontró usuario con el token proporcionado");
        throw new Exception('Sesión inválida o expirada');
    }

    error_log("verifySessionToken - Usuario encontrado: " . json_encode($user));
    return $user;
}

function verifyPersonalToken($conn, $user_id, $token_personal) {
    error_log("verifyPersonalToken - Verificando token para usuario: " . $user_id);
    
    if (!$token_personal) {
        error_log("verifyPersonalToken - Token no proporcionado");
        throw new Exception('Token personal no proporcionado');
    }

    $query = "SELECT token_personal FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("verifyPersonalToken - Error preparando query: " . $conn->error);
        throw new Exception('Error preparando consulta');
    }
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log("verifyPersonalToken - Error ejecutando query: " . $stmt->error);
        throw new Exception('Error ejecutando consulta');
    }
    
    $result = $stmt->get_result();
    $stored_token = $result->fetch_assoc();

    if (!$stored_token || $stored_token['token_personal'] !== $token_personal) {
        error_log("verifyPersonalToken - Token inválido");
        throw new Exception('Token personal inválido');
    }

    error_log("verifyPersonalToken - Token verificado correctamente");
    return true;
}

function requireAuthentication($conn) {
    error_log("requireAuthentication - Iniciando autenticación");
    
    $headers = getallheaders();
    $session_token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    error_log("requireAuthentication - Token recibido: " . $session_token);
    return verifySessionToken($conn, $session_token);
}

?>
