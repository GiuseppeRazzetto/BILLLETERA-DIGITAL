<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

require_once '../config/database.php';

function checkLoginAttempts($email, $conn) {
    // Primero, limpiar intentos antiguos
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE attempt_time <= DATE_SUB(NOW(), INTERVAL 30 SECOND)");
    $stmt->execute();

    // Luego verificar intentos actuales
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts, MIN(attempt_time) as first_attempt FROM login_attempts WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 SECOND)");
    $stmt->execute([$email]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['attempts'] >= 3) {
        $timeLeft = 30 - (time() - strtotime($result['first_attempt']));
        $timeLeft = max(0, $timeLeft);
        
        // Si el tiempo expiró, limpiar los intentos
        if ($timeLeft <= 0) {
            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
            $stmt->execute([$email]);
            return ['blocked' => false, 'attempts' => 0, 'timeLeft' => 0];
        }
        
        return ['blocked' => true, 'attempts' => $result['attempts'], 'timeLeft' => $timeLeft];
    }
    
    return ['blocked' => false, 'attempts' => $result['attempts'], 'timeLeft' => 0];
}

function addLoginAttempt($email, $conn) {
    // Agregar nuevo intento
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, attempt_time) VALUES (?, NOW())");
    $stmt->execute([$email]);
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username_db,
        $password_db,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Crear tabla de intentos si no existe
    $conn->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        attempt_time DATETIME NOT NULL,
        INDEX (email, attempt_time)
    )");

    // Paso 1: Validación de email y contraseña
    if (isset($data['email']) && isset($data['password']) && !isset($data['token_personal'])) {
        $email = trim($data['email']);
        $password = trim($data['password']);

        // Verificar si la cuenta está bloqueada
        $attemptStatus = checkLoginAttempts($email, $conn);
        if ($attemptStatus['blocked']) {
            throw new Exception(json_encode([
                'blocked' => true,
                'timeLeft' => $attemptStatus['timeLeft'],
                'message' => 'Demasiados intentos fallidos. Por favor espere ' . $attemptStatus['timeLeft'] . ' segundos.'
            ]));
        }

        $stmt = $conn->prepare("SELECT id, correo_electronico, contrasena_hash FROM users WHERE correo_electronico = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['contrasena_hash'])) {
            // Registrar intento fallido
            addLoginAttempt($email, $conn);
            $attemptStatus = checkLoginAttempts($email, $conn);
            $remainingAttempts = 3 - $attemptStatus['attempts'];
            
            if ($attemptStatus['blocked']) {
                throw new Exception(json_encode([
                    'blocked' => true,
                    'timeLeft' => $attemptStatus['timeLeft'],
                    'message' => 'Demasiados intentos fallidos. Por favor espere ' . $attemptStatus['timeLeft'] . ' segundos.'
                ]));
            } else {
                throw new Exception(json_encode([
                    'blocked' => false,
                    'remainingAttempts' => $remainingAttempts,
                    'message' => "Credenciales inválidas. Intentos restantes: $remainingAttempts"
                ]));
            }
        }

        // Si las credenciales son correctas, pedimos el token personal
        echo json_encode([
            'success' => true,
            'require_token' => true,
            'message' => 'Por favor ingrese su token personal de 4 dígitos',
            'user' => [
                'id' => $user['id'],
                'email' => $user['correo_electronico']
            ]
        ]);
        exit();
    }

    // Paso 2: Validación del token personal
    if (isset($data['email']) && isset($data['token_personal'])) {
        $email = trim($data['email']);
        $token = trim($data['token_personal']);

        // Verificar si la cuenta está bloqueada
        $attemptStatus = checkLoginAttempts($email, $conn);
        if ($attemptStatus['blocked']) {
            throw new Exception(json_encode([
                'blocked' => true,
                'timeLeft' => $attemptStatus['timeLeft'],
                'message' => 'Demasiados intentos fallidos. Por favor espere ' . $attemptStatus['timeLeft'] . ' segundos.'
            ]));
        }

        if (!preg_match('/^\d{4}$/', $token)) {
            throw new Exception('El token debe ser de 4 dígitos');
        }

        // Agregar log para depuración
        error_log("Verificando token para email: $email");
        error_log("Token ingresado (raw): " . $token);
        error_log("Token ingresado (tipo): " . gettype($token));
        
        $stmt = $conn->prepare("SELECT id, correo_electronico, token_personal FROM users WHERE correo_electronico = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            error_log("Token en DB (raw): " . $user['token_personal']);
            error_log("Token en DB (tipo): " . gettype($user['token_personal']));
            error_log("Comparación directa: " . ($user['token_personal'] === $token ? 'true' : 'false'));
            
            // Asegurarnos de que ambos son strings y tienen el mismo formato
            $dbToken = trim((string)$user['token_personal']);
            $inputToken = trim((string)$token);
            
            error_log("Token DB después de formateo: '$dbToken'");
            error_log("Token input después de formateo: '$inputToken'");
            error_log("Comparación después de formateo: " . ($dbToken === $inputToken ? 'true' : 'false'));

            if ($dbToken !== $inputToken) {
                // Registrar intento fallido
                addLoginAttempt($email, $conn);
                $attemptStatus = checkLoginAttempts($email, $conn);
                $remainingAttempts = 3 - $attemptStatus['attempts'];
                
                if ($attemptStatus['blocked']) {
                    throw new Exception(json_encode([
                        'blocked' => true,
                        'timeLeft' => $attemptStatus['timeLeft'],
                        'message' => 'Demasiados intentos fallidos. Por favor espere ' . $attemptStatus['timeLeft'] . ' segundos.'
                    ]));
                } else {
                    throw new Exception(json_encode([
                        'blocked' => false,
                        'remainingAttempts' => $remainingAttempts,
                        'message' => "Token incorrecto. Intentos restantes: $remainingAttempts"
                    ]));
                }
            }
        } else {
            throw new Exception(json_encode([
                'blocked' => false,
                'message' => "Usuario no encontrado"
            ]));
        }

        // Limpiar intentos fallidos al lograr iniciar sesión
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
        $stmt->execute([$email]);

        // Generar token de sesión
        $session_token = bin2hex(random_bytes(32));
        
        // Guardar token en la base de datos
        $stmt = $conn->prepare("UPDATE users SET session_token = ? WHERE id = ?");
        $stmt->execute([$session_token, $user['id']]);

        echo json_encode([
            'success' => true,
            'session_token' => $session_token,
            'user' => [
                'id' => $user['id'],
                'email' => $user['correo_electronico']
            ]
        ]);
        exit();
    }

    throw new Exception('Datos incompletos');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    error_log('Error de base de datos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor'
    ]);
}
