<?php
class Admin {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getAppointments(): void {
        auth_admin();
        respond_success(execute($this->pdo, 'CALL getApptAdmin()'));
    }

    public function getSlots(): void {
        auth_admin();
        respond_success(execute($this->pdo, 'CALL getAllSlotsWithService()'));
    }
}