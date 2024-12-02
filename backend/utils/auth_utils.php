<?php

function verifySessionToken($conn, $session_token) {
    if (!$session_token) {
        throw new Exception('Token de sesión no proporcionado');
    }

    $query = "
        SELECT u.id, u.nombre, u.apellido, u.token_personal, w.id as wallet_id 
        FROM users u 
        JOIN sessions s ON u.id = s.user_id 
        JOIN wallets w ON u.id = w.user_id 
        WHERE s.token = ? AND s.expiration > NOW()
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $session_token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        throw new Exception('Sesión inválida o expirada');
    }

    return $user;
}

function verifyPersonalToken($conn, $user_id, $token_personal) {
    if (!$token_personal) {
        throw new Exception('Token personal no proporcionado');
    }

    $query = "SELECT token_personal FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stored_token = $result->fetch_assoc();

    if ($stored_token['token_personal'] !== $token_personal) {
        throw new Exception('Token personal inválido');
    }

    return true;
}

function requireAuthentication($conn) {
    $headers = getallheaders();
    $session_token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    return verifySessionToken($conn, $session_token);
}
