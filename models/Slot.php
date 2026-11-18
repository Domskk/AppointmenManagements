<?php
class Slot {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getAll(): void {
        $slots = execute($this->pdo, 'CALL getAllSlotsWithService()');
        respond_success(select_rows($slots, [
            'id', 'service_id', 'service_name', 'slot_date', 'slot_time',
            'capacity', 'available_capacity',
        ]));
    }

    public function create(): void {
        auth_admin();
        $data      = request_body();
        $serviceId = filter_var($data['service_id'] ?? null, FILTER_VALIDATE_INT);
        $slotDate  = $data['slot_date']  ?? '';
        $slotTime  = $data['slot_time']  ?? '';
        $capacity  = filter_var($data['capacity'] ?? 1, FILTER_VALIDATE_INT);

        if ($serviceId === false || $serviceId < 1)
            respond_error('A valid service ID is required', 400);
        if ($capacity === false || $capacity < 1)
            respond_error('Capacity must be a positive whole number', 400);
        if (!is_string($slotDate) || !is_string($slotTime))
            respond_error('A valid date and time are required', 400);

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $slotDate);
        if (!$date || $date->format('Y-m-d') !== $slotDate)
            respond_error('Slot date must use YYYY-MM-DD format', 400);

        if (!preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $slotTime))
            respond_error('Slot time must use HH:MM or HH:MM:SS format', 400);
        if (strlen($slotTime) === 5) $slotTime .= ':00';

        $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $slotDate . ' ' . $slotTime);
        if (!$start || $start->format('Y-m-d H:i:s') !== $slotDate . ' ' . $slotTime)
            respond_error('Slot time is invalid', 400);
        if ($start <= new DateTimeImmutable('now', $start->getTimezone()))
            respond_error('Slots must be scheduled in the future', 400);

        $service = execute($this->pdo, 'CALL getServiceById(?)', [$serviceId], 'one');
        if (!$service) respond_error('Service not found', 404);
        if (isset($service['is_active']) && !(bool)$service['is_active'])
            respond_error('Cannot schedule a slot for an inactive service', 400);

        $result = execute(
            $this->pdo,
            'CALL insertSlot(?, ?, ?, ?)',
            [$serviceId, $slotDate, $slotTime, $capacity],
            'one'
        );
        respond_success(['slot_id' => $result['slot_id'] ?? null], 'Slot created', 201);
    }

    // Soft delete
    public function delete(): void {
        auth_admin();
        $data = request_body();
        $id   = $data['id'] ?? 0;

        if (!$id) respond_error('Slot ID is required', 400);

        execute($this->pdo, 'CALL deleteSlot(?)', [$id], 'none');
        respond_success([], 'Slot deleted');
    }
}