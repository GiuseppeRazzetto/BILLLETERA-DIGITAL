<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'sql10.freesqldatabase.com';
$port = 3306;
$dbname = 'sql10749054';
$username_db = 'sql10749054';
$password_db = '6SZwPqJXNB';

try {
    echo "Intentando conectar a la base de datos...\n";
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $username_db, $password_db, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    echo "¡Conexión exitosa!\n";
    
    // Intentar una consulta simple
    $stmt = $pdo->query("SELECT NOW()");
    $result = $stmt->fetch();
    echo "Hora del servidor: " . print_r($result, true);
    
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage() . "\n";
    echo "DSN usado: $dsn\n";
}
