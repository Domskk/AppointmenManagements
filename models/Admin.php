<?php
class Admin {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getAppointments(): void {
        auth_admin();
        $appointments = execute($this->pdo, 'CALL getApptAdmin()');
        
        try {
            foreach ($appointments as &$appt) {
                if (!empty($appt['email'])) $appt['email'] = decrypt_data($appt['email']);
                if (!empty($appt['phone'])) $appt['phone'] = decrypt_data($appt['phone']);
            }
        } catch (RuntimeException $e) {
            respond_error('Failed to decrypt appointment data', 500);
        }
        
        respond_success($appointments);
    }

    public function getSlots(): void {
        auth_admin();
        respond_success(execute($this->pdo, 'CALL getAllSlotsWithService()'));
    }
}