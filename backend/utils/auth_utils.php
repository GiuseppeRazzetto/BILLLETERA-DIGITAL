<?php

function verifySessionToken($pdo, $session_token) {
    if (!$session_token) {
        throw new Exception('Token de sesión no proporcionado');
    }

    $stmt = $pdo->prepare('
        SELECT u.id, u.nombre, u.apellido, u.token_personal, w.id as wallet_id 
        FROM users u 
        JOIN sessions s ON u.id = s.user_id 
        JOIN wallets w ON u.id = w.user_id 
        WHERE s.token = ? AND s.expiration > NOW()
    ');
    $stmt->execute([$session_token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Sesión inválida o expirada');
    }

    return $user;
}

function verifyPersonalToken($pdo, $user_id, $token_personal) {
    if (!$token_personal) {
        throw new Exception('Token personal no proporcionado');
    }

    $stmt = $pdo->prepare('SELECT token_personal FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $stored_token = $stmt->fetchColumn();

    if ($stored_token !== $token_personal) {
        throw new Exception('Token personal inválido');
    }

    return true;
}

function requireAuthentication($pdo) {
    $headers = getallheaders();
    $session_token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    return verifySessionToken($pdo, $session_token);
}
