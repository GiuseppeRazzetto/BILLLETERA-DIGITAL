<?php
require_once '../../config/database.prod.php';
require_once '../../utils/cors.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Obtener lista de tablas
    $tables = array();
    $result = $conn->query("SHOW TABLES");
    
    $database_data = array();
    
    while ($row = $result->fetch_array()) {
        $table_name = $row[0];
        
        // Obtener datos de cada tabla
        $table_data = array();
        $records = $conn->query("SELECT * FROM " . $table_name);
        
        while ($record = $records->fetch_assoc()) {
            $table_data[] = $record;
        }
        
        $database_data[$table_name] = $table_data;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $database_data
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
