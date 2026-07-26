<?php
class Appointment {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function create(): void {
        $user      = auth_user();
        $data      = request_body();
        $serviceId = $data['service_id'] ?? 0;
        $slotId    = $data['slot_id']    ?? 0;
        $notes     = $data['notes']      ?? '';

        if (!$serviceId || !$slotId)
            respond_error('Service ID and Slot ID are required', 400);

        $slot = execute($this->pdo, 'CALL getSlotById(?)', [$slotId], 'one');
        if (!$slot) respond_error('Slot not found', 404);
        if (($slot['available_capacity'] ?? 0) < 1)
            respond_error('Slot is full', 400);

        $service = execute($this->pdo, 'CALL getServiceById(?)', [$serviceId], 'one');
        if (!$service) respond_error('Service not found', 404);
        $duration = (int)($service['duration'] ?? 0);

        // ── Changed: check by date instead of slot_id ──
        $duplicate = execute(
            $this->pdo,
            'CALL checkDuplicateAppointment(?, ?, ?)',
            [$user['id'], $serviceId, $slot['slot_date']],  // ← was $slotId
            'one'
        );
        if (($duplicate['total'] ?? 0) > 0)
            respond_error(
                'You already have an active appointment for this service on this date.',  // ← clearer message
                409
            );

        // ── Calculate queue number and personal time ───────
        $count = execute(
            $this->pdo,
            'CALL getApptCountBySlot(?)',
            [$slotId],
            'one'
        );
        $queue = ($count['total'] ?? 0) + 1;

        $offsetMinutes = ($queue - 1) * $duration;
        $base          = strtotime($slot['slot_date'] . ' ' . $slot['slot_time']);
        $personalDate  = date('Y-m-d', $base + ($offsetMinutes * 60));
        $personalTime  = date('H:i:s', $base + ($offsetMinutes * 60));
        $estimatedEnd  = date('H:i:s', $base + ($offsetMinutes * 60) + ($duration * 60));

        $notesEnc = $notes ? encrypt_data($notes) : null;

        $result = execute(
            $this->pdo,
            'CALL insertAppointment(?, ?, ?, ?, ?, ?, ?)',
            [
                $user['id'],
                $serviceId,
                $slotId,
                $queue,
                $personalDate,
                $personalTime,
                $notesEnc
            ],
            'one'
        );

        execute($this->pdo, 'CALL updateSlotCapacity(?)', [$slotId], 'none');

        respond_success([
            'appointment_id'   => $result['appointment_id'] ?? null,
            'queue_number'     => $queue,
            'appointment_date' => $personalDate,
            'appointment_time' => $personalTime,
            'estimated_end'    => $estimatedEnd,
        ], 'Appointment created', 201);
    }

    public function getByUser(string $userId): void {
        auth_user();
        $appointments = execute($this->pdo, 'CALL getUserAppt(?)', [$userId]);
        
        try {
            foreach ($appointments as &$appt) {
                if (!empty($appt['notes'])) {
                    $appt['notes'] = decrypt_data($appt['notes']);
                }
            }
        } catch (RuntimeException $e) {
            respond_error('Failed to decrypt appointment data', 500);
        }
        
        respond_success($appointments);
    }

    public function updateStatus(): void {
        auth_admin();
        $data    = request_body();
        $id      = $data['id']     ?? 0;
        $status  = $data['status'] ?? '';
        $allowed = ['pending', 'confirmed', 'serving', 'completed'];

        if (!$id || !in_array(strtolower($status), $allowed))
            respond_error('Invalid appointment ID or status', 400);

        execute(
            $this->pdo,
            'CALL updateAppointmentStatus(?, ?)',
            [$id, strtolower($status)],
            'none'
        );
        respond_success([], 'Status updated');
    }

    public function delete(): void {
        auth_admin();
        $data = request_body();
        $id   = $data['id'] ?? 0;

        if (!$id) respond_error('Appointment ID is required', 400);

        execute($this->pdo, 'CALL deleteAppointment(?)', [$id], 'none');
        respond_success([], 'Appointment deleted');
    }

    public function getArchived(): void {
        auth_admin();
        $appointments = execute($this->pdo, 'CALL getArchivedAppointments()');
        
        try {
            foreach ($appointments as &$appt) {
                if (!empty($appt['notes'])) {
                    $appt['notes'] = decrypt_data($appt['notes']);
                }
            }
        } catch (RuntimeException $e) {
            respond_error('Failed to decrypt appointment data', 500);
        }
        
        respond_success($appointments);
    }
}