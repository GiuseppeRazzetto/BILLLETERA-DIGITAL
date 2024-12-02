<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getWalletId($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    return $wallet ? $wallet['id'] : null;
}

function getUserFromToken($conn, $token) {
    $stmt = $conn->prepare("SELECT id, correo_electronico FROM users WHERE session_token = ?");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserByEmail($conn, $email) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE correo_electronico = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $headers = getallheaders();
        $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (empty($auth_header)) {
            throw new Exception('Token no proporcionado');
        }

        $token_parts = explode(' ', $auth_header);
        if (count($token_parts) !== 2 || $token_parts[0] !== 'Bearer') {
            throw new Exception('Formato de token inválido');
        }

        $session_token = $token_parts[1];
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['tipo']) || !isset($data['monto'])) {
            throw new Exception('Datos incompletos');
        }

        if ($data['monto'] <= 0) {
            throw new Exception('El monto debe ser mayor a 0');
        }

        $conn = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username_db,
            $password_db,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $user = getUserFromToken($conn, $session_token);
        if (!$user) {
            throw new Exception('Sesión inválida');
        }

        $wallet_id = getWalletId($conn, $user['id']);
        if (!$wallet_id) {
            throw new Exception('Billetera no encontrada');
        }

        $conn->beginTransaction();

        switch ($data['tipo']) {
            case 'deposito':
                // Actualizar balance
                $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$data['monto'], $wallet_id]);

                // Registrar transacción
                $stmt = $conn->prepare("INSERT INTO transactions (wallet_id, tipo, monto, descripcion) VALUES (?, 'deposito', ?, ?)");
                $stmt->execute([$wallet_id, $data['monto'], $data['descripcion'] ?? 'Depósito']);
                break;

            case 'retiro':
                // Verificar fondos suficientes
                $stmt = $conn->prepare("SELECT balance FROM wallets WHERE id = ? FOR UPDATE");
                $stmt->execute([$wallet_id]);
                $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($wallet['balance'] < $data['monto']) {
                    throw new Exception('Fondos insuficientes');
                }

                // Actualizar balance
                $stmt = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$data['monto'], $wallet_id]);

                // Registrar transacción
                $stmt = $conn->prepare("INSERT INTO transactions (wallet_id, tipo, monto, descripcion) VALUES (?, 'retiro', ?, ?)");
                $stmt->execute([$wallet_id, $data['monto'], $data['descripcion'] ?? 'Retiro']);
                break;

            case 'transferencia':
                if (!isset($data['email_destino'])) {
                    throw new Exception('Email de destino no proporcionado');
                }

                // Verificar que no sea el mismo usuario
                if ($data['email_destino'] === $user['correo_electronico']) {
                    throw new Exception('No puedes transferir a tu propia cuenta');
                }

                // Obtener usuario destino
                $destino = getUserByEmail($conn, $data['email_destino']);
                if (!$destino) {
                    throw new Exception('Usuario destino no encontrado');
                }

                // Obtener wallet destino
                $wallet_destino_id = getWalletId($conn, $destino['id']);
                if (!$wallet_destino_id) {
                    throw new Exception('Billetera destino no encontrada');
                }

                // Verificar fondos suficientes
                $stmt = $conn->prepare("SELECT balance FROM wallets WHERE id = ? FOR UPDATE");
                $stmt->execute([$wallet_id]);
                $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($wallet['balance'] < $data['monto']) {
                    throw new Exception('Fondos insuficientes');
                }

                // Actualizar balances
                $stmt = $conn->prepare("UPDATE wallets SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$data['monto'], $wallet_id]);

                $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$data['monto'], $wallet_destino_id]);

                // Registrar transacción
                $stmt = $conn->prepare("INSERT INTO transactions (wallet_id, tipo, monto, descripcion, wallet_from_id, wallet_to_id) VALUES (?, 'transferencia', ?, ?, ?, ?)");
                $stmt->execute([
                    $wallet_id,
                    $data['monto'],
                    $data['descripcion'] ?? 'Transferencia',
                    $wallet_id,
                    $wallet_destino_id
                ]);
                break;

            default:
                throw new Exception('Tipo de transacción inválido');
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Transacción realizada con éxito'
        ]);

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    } catch (PDOException $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error en la base de datos'
        ]);
    }
}
