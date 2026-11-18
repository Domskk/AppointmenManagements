<?php
class Admin {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getAppointments(): void {
        auth_admin();
        $appointments = execute($this->pdo, 'CALL getApptAdmin()');
        respond_success(select_rows($appointments, [
            'id', 'queue_number', 'user_name', 'service_name', 'appointment_date',
            'appointment_time', 'estimated_end', 'status',
        ]));
    }

    public function getSlots(): void {
        auth_admin();
        $slots = execute($this->pdo, 'CALL getAllSlotsWithService()');
        respond_success(select_rows($slots, [
            'id', 'service_id', 'service_name', 'slot_date', 'slot_time',
            'capacity', 'available_capacity',
        ]));
    }
}