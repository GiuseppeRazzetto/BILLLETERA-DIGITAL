<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'online',
    'message' => 'Digital Wallet 2 API is running'
]);
