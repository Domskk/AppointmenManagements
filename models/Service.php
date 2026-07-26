<?php
class Service {
    private PDO $pdo;
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getAll(): void {
        respond_success(execute($this->pdo, 'CALL getAllActiveService()'));
    }

    public function create(): void {
        auth_admin();
        $data     = request_body();
        $name     = $data['name']        ?? '';
        $desc     = $data['description'] ?? '';
        $duration = $data['duration']    ?? 0;
        if (!$name || !$duration)
            respond_error('Service name and duration are required', 400);
        $result = execute($this->pdo, 'CALL insertService(?, ?, ?)',
            [$name, $desc, $duration], 'one');
        respond_success(['service_id' => $result['service_id'] ?? null], 'Service created', 201);
    }

    public function update(): void {
        auth_admin();
        $data     = request_body();
        $id       = $data['id']          ?? 0;
        $name     = $data['name']        ?? null;
        $desc     = $data['description'] ?? null;
        $duration = $data['duration']    ?? null;
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : null;

        if (!$id) respond_error('Service ID is required', 400);

        execute($this->pdo, 'CALL updateService(?, ?, ?, ?, ?)',
            [$id, $name, $desc, $duration, $isActive], 'none');

        respond_success([], 'Service updated');
    }
}