<?php
class Auth {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function register(): void {
    $data = request_body();
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $phone = $data['phone'] ?? null;
    $role = $data['role'] ?? 'user';

    if (!$name || !$email || !$password)
        respond_error('Name, email and password are required', 400);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        respond_error('Invalid email format', 400);

    if (!preg_match("/^[A-Za-z\s.'-]+$/", $name))
        respond_error('Name must contain letters only (no numbers or special characters)', 400);

    if (strlen($password) < 6)
        respond_error('Password must be at least 6 characters long', 400);

    if (!in_array($role, ['user', 'admin'], true))
        respond_error('Invalid role', 400);

    $emailHash = hash_hmac('sha256', strtolower($email), $_ENV['JWT_SECRET'] ?? 'secret');
    $emailEnc = encrypt_data($email);
    $phoneEnc = $phone ? encrypt_data($phone) : null;
    $passHash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $result = execute(
            $this->pdo,
            'CALL registerUser(?, ?, ?, ?, ?, ?)',
            [$name, $emailEnc, $emailHash, $passHash, $phoneEnc, $role],
            'one'
        );
        respond_success(['user_id' => $result['user_id'] ?? null], 'Registration successful', 201);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Duplicate name'))
            respond_error('Name already taken', 409);
        if (str_contains($msg, 'Duplicate email'))
            respond_error('Email already exists', 409);
        respond_error('Registration failed', 500);
    }
}

    public function login(): void {
        $data = request_body();
        $email = trim($data['email']    ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password)
            respond_error('Email and password are required', 400);

        $emailHash = hash_hmac('sha256', strtolower($email), $_ENV['JWT_SECRET'] ?? 'secret');
        $found     = execute($this->pdo, 'CALL getUserByEmailHash(?)', [$emailHash], 'one');

        if (!$found || !password_verify($password, $found['password'] ?? ''))
            respond_error('Invalid email or password', 401);

        $token = create_jwt([
            'id' => $found['id'],
            'name' => $found['name'],
            'email' => $email,
            'role' => $found['role'] ?? 'user',
        ]);

        respond_success([
            'token' => $token,
            'user'  => [
                'id' => $found['id'],
                'name' => $found['name'],
                'email' => $email,
                'role' => $found['role'] ?? 'user',
            ],
        ], 'Login successful');
    }
}