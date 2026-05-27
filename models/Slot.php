<?php
class Slot {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getAll(): void {
        $data = execute($this->pdo, 'CALL getAllSlotsWithService()', [], 'all');
        respond_success($data);
    }
    public function create(): void {
        auth_admin();
        $data = request_body();
        $serviceId = $data['service_id'] ?? 0;
        $slotDate = $data['slot_date'] ?? '';
        $slotTime = $data['slot_time'] ?? '';
        $capacity = $data['capacity']  ?? 1;

        if (!$serviceId || !$slotDate || !$slotTime)
            respond_error('Service ID, date and time are required', 400);

        $result = execute($this->pdo, 'CALL insertSlot(?, ?, ?, ?)',
            [$serviceId, $slotDate, $slotTime, $capacity], 'one');

        respond_success(['slot_id' => $result['slot_id'] ?? null], 'Slot created', 201);
    }
}