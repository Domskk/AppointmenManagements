<?php
class Service {
    private PDO $pdo;
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getAll(): void {
        $services = execute($this->pdo, 'CALL getAllActiveService()');
        respond_success(select_rows($services, ['id', 'name', 'description', 'duration', 'is_active']));
    }

    public function create(): void {
        auth_admin();
        $data     = request_body();
        $name     = $data['name']        ?? '';
        $desc     = $data['description'] ?? '';
        $duration = filter_var($data['duration'] ?? null, FILTER_VALIDATE_INT);
        if (!is_string($name) || trim($name) === '')
            respond_error('Service name is required', 400);
        if ($duration === false || $duration < 1)
            respond_error('Service duration must be a positive whole number', 400);
        if (!is_string($desc))
            respond_error('Service description must be text', 400);

        $result = execute($this->pdo, 'CALL insertService(?, ?, ?)',
            [trim($name), trim($desc), $duration], 'one');
        respond_success(['service_id' => $result['service_id'] ?? null], 'Service created', 201);
    }

    public function update(): void {
        auth_admin();
        $data     = request_body();
        $id       = $data['id']          ?? 0;
        $name     = $data['name']        ?? null;
        $desc     = $data['description'] ?? null;
        $duration = array_key_exists('duration', $data)
            ? filter_var($data['duration'], FILTER_VALIDATE_INT)
            : null;
        $isActive = array_key_exists('is_active', $data)
            ? filter_var($data['is_active'], FILTER_VALIDATE_INT)
            : null;

        if (!$id) respond_error('Service ID is required', 400);
        if ($name !== null && (!is_string($name) || trim($name) === ''))
            respond_error('Service name cannot be empty', 400);
        if ($desc !== null && !is_string($desc))
            respond_error('Service description must be text', 400);
        if (array_key_exists('duration', $data) && ($duration === false || $duration < 1))
            respond_error('Service duration must be a positive whole number', 400);
        if (array_key_exists('is_active', $data) && !in_array($isActive, [0, 1], true))
            respond_error('Service status must be active or inactive', 400);

        execute($this->pdo, 'CALL updateService(?, ?, ?, ?, ?)',
            [$id, $name === null ? null : trim($name), $desc === null ? null : trim($desc), $duration, $isActive], 'none');

        respond_success([], 'Service updated');
    }
}