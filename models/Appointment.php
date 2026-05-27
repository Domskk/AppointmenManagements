<?php
class Appointment {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function create(): void {
        $user = auth_user();
        $data = request_body();
        $serviceId = $data['service_id'] ?? 0;
        $slotId = $data['slot_id']    ?? 0;
        $notes  = $data['notes']      ?? '';

        if (!$serviceId || !$slotId)
            respond_error('Service ID and Slot ID are required', 400);

        $slot = execute($this->pdo, 'CALL getSlotById(?)', [$slotId], 'one');
        if (!$slot) respond_error('Slot not found', 404);
        if (($slot['available_capacity'] ?? 0) < 1) respond_error('Slot is full', 400);

        $count = execute($this->pdo, 'CALL getApptCountBySlot(?)', [$slotId], 'one');
        $queue = ($count['total'] ?? 0) + 1;
        $notesEnc = $notes ? encrypt_data($notes) : null;

        $result = execute($this->pdo, 'CALL insertAppointment(?, ?, ?, ?, ?, ?, ?)', [
            $user['id'], $serviceId, $slotId, $queue,
            $slot['slot_date'], $slot['slot_time'], $notesEnc
        ], 'one');

        execute($this->pdo, 'CALL updateSlotCapacity(?)', [$slotId], 'none');

        respond_success([
            'appointment_id' => $result['appointment_id'] ?? null,
            'queue_number' => $queue
        ], 'Appointment created', 201);
    }

    public function getByUser(string $userId): void {
        auth_user();
        $appointments = execute($this->pdo, 'CALL getUserAppt(?)', [$userId]);

        foreach ($appointments as &$appt) {
            if (!empty($appt['notes']))
                $appt['notes'] = decrypt_data($appt['notes']);
        }

        respond_success($appointments);
    }
    public function updateStatus(): void {
    auth_admin();
    $data = request_body();
    $id = $data['id'] ?? 0;
    $status = $data['status'] ?? '';

    $allowed = ['pending', 'confirmed', 'serving', 'completed'];
    if (!$id || !in_array(strtolower($status), $allowed))
        respond_error('Invalid appointment ID or status', 400);

    execute($this->pdo, 'CALL updateAppointmentStatus(?, ?)', [$id, strtolower($status)], 'none');
    respond_success([], 'Status updated');
}
}