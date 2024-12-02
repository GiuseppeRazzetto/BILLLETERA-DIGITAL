<?php
$host = 'sql10.freesqldatabase.com';
$port = 3306;
$dbname = 'sql10749054';
$username_db = 'sql10749054';
$password_db = '6SZwPqJXNB';

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
