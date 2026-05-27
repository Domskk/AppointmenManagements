<?php
class User {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getProfile(): void {
        $user = auth_user();             
        $data = execute($this->pdo, 'CALL getUserById(?)', [$user['id']], 'one');

        if (!$data) respond_error('User not found', 404);

        if (!empty($data['email'])) $data['email'] = decrypt_data($data['email']);
        if (!empty($data['phone'])) $data['phone'] = decrypt_data($data['phone']);

        unset($data['password'], $data['email_hash']);

        respond_success($data);
    }

    public function updateProfile(): void {
        $user = auth_user();
        $data = request_body();

        $name  = isset($data['name'])  ? trim($data['name'])  : null;
        $phone = isset($data['phone']) ? trim($data['phone']) : null;

        if ($name === null && $phone === null)
            respond_error('Provide at least name or phone to update', 400);

        if ($name !== null && $name === '')
            respond_error('Name cannot be empty', 400);

        $phoneEnc = $phone ? encrypt_data($phone) : null;

        execute($this->pdo, 'CALL updateUser(?, ?, ?)', [$name, $phoneEnc, $user['id']], 'none');

        $updated = execute($this->pdo, 'CALL getUserById(?)', [$user['id']], 'one');
        if ($updated) {
            if (!empty($updated['email'])) $updated['email'] = decrypt_data($updated['email']);
            if (!empty($updated['phone'])) $updated['phone'] = decrypt_data($updated['phone']);
            unset($updated['password'], $updated['email_hash']);
        }

        respond_success($updated ?? [], 'Profile updated successfully');
    }
}