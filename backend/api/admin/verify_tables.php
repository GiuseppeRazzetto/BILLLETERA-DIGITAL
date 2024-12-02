<?php
require_once '../../config/database.prod.php';
require_once '../../utils/cors.php';

header('Content-Type: application/json');

try {
    // Leer el archivo SQL
    $sql = file_get_contents('../../database/verify_tables.sql');
    
    // Dividir en statements individuales
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    // Ejecutar cada statement
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            error_log("Ejecutando: " . $statement);
            if (!$conn->query($statement)) {
                throw new Exception("Error ejecutando query: " . $conn->error);
            }
        }
    }
    
    // Verificar si el usuario tiene una wallet
    $user_id = 3; // El ID del usuario de prueba
    $check_wallet = $conn->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $check_wallet->bind_param("i", $user_id);
    $check_wallet->execute();
    $result = $check_wallet->get_result();
    
    if ($result->num_rows === 0) {
        // Crear wallet para el usuario
        $create_wallet = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)");
        $create_wallet->bind_param("i", $user_id);
        $create_wallet->execute();
        error_log("Wallet creada para usuario " . $user_id);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Tablas verificadas y actualizadas correctamente'
    ]);

} catch (Exception $e) {
    error_log("Error en verify_tables.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
