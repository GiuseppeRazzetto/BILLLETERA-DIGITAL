<?php
$host = 'sql10.freesqldatabase.com';
$port = 3306;
$dbname = 'sql10749054';
$username_db = 'sql10749054';
$password_db = '6SZwPqJXNB';

try {
    $conn = new mysqli($host, $username_db, $password_db, $dbname, $port);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Establecer charset a utf8
    $conn->set_charset("utf8");
    
} catch (Exception $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Error de conexión: ' . $e->getMessage(),
        'details' => [
            'host' => $host,
            'port' => $port,
            'dbname' => $dbname,
            'username' => $username_db
        ]
    ]));
}
