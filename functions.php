<?php
// Execute stored procedure
function execute(PDO $pdo, string $sql, array $params = [], string $mode = 'all') {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return match($mode) {
        'one'  => $stmt->fetch(),
        'none' => true,
        default => $stmt->fetchAll(),
    };
}

// JSON request body 
function request_body(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

// Response helpers 
function respond_success($data = [], string $message = 'Success', int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
    exit;
}

function respond_error(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// JWT 
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function create_jwt(array $payload): string {
    $secret  = $_ENV['JWT_SECRET'] ?? 'secret';
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode(array_merge($payload, ['exp' => time() + 86400])));
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    return "$header.$payload.$sig";
}

function verify_jwt(string $token): ?array {
    $secret = $_ENV['JWT_SECRET'] ?? 'secret';
    $parts  = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    if (!hash_equals($expected, $sig)) return null;
    $data = json_decode(base64url_decode($payload), true);
    if (($data['exp'] ?? 0) < time()) return null;
    return $data;
}

function get_token(): ?string {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) return $m[1];
    return null;
}

// Auth guards
function auth_user(): array {
    $token = get_token();
    if (!$token) respond_error('No token provided', 401);
    $user = verify_jwt($token);
    if (!$user) respond_error('Invalid or expired token', 401);
    return $user;
}

function auth_admin(): array {
    $user = auth_user();
    if (($user['role'] ?? '') !== 'admin') respond_error('Admin access required', 403);
    return $user;
}

// Encryption (AES-256-GCM)
function encrypt_data(string $value): string {
    $key = ENCRYPTION_KEY; // 32 bytes, from config
    $iv  = openssl_random_pseudo_bytes(12); // 12 bytes recommended for GCM

    $tag = '';
    $cipher = openssl_encrypt(
        $value,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($cipher === false) {
        throw new RuntimeException('Encryption failed');
    }

    // Store iv + tag + ciphertext together, base64-encoded
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_data(string $value): string {
    $key = ENCRYPTION_KEY;
    $raw = base64_decode($value, true);
    
    if ($raw === false || strlen($raw) < 28) {
        throw new RuntimeException('Invalid encrypted data format');
    }
    
    $iv  = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    
    $plain = openssl_decrypt(
        $cipher,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
    
    if ($plain === false) {
        throw new RuntimeException('Decryption failed: authentication tag verification failed');
    }
    
    return $plain;
}