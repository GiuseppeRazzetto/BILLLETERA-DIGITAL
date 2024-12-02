<?php
$host = 'sql.freedb.tech'; // Quitamos el puerto de la URL
$port = 3306;  // Lo especificamos por separado
$dbname = 'freedb_digital_wallet2';
$username_db = 'freedb_giuseppe';
$password_db = 'Kh4M%*wJ2Nq4xE@';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $username_db, $password_db, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'details' => [
            'host' => $host,
            'port' => $port,
            'dbname' => $dbname,
            'username' => $username_db
        ]
    ]));
}
