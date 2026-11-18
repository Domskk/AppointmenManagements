<?php
class Report {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function waitTime(): void {
        auth_admin();

        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ??null;

        if ($from && !$this->validDate($from))
            respond_error('Invalid "from" date format. Use YYYY-MM-DD', 400);
        if ($to && !$this->validDate($to))
            respond_error('Invalid "to" date format. Use YYYY-MM-DD', 400);
        if ($from && $to && $from > $to)
            respond_error('"from" date must be before "to" date', 400);

        $rows = execute($this->pdo, 'CALL getReport(?, ?)', [$from, $to]);
        respond_success(select_rows($rows, [
            'date', 'service_name', 'average_wait_time', 'avg_wait_time',
            'wait_time', 'total_appointments', 'appointment_count',
        ]));
    }

    public function serviceDemand(): void {
        auth_admin();

        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;

        if ($from && !$this->validDate($from))
            respond_error('Invalid "from" date format. Use YYYY-MM-DD', 400);
        if ($to && !$this->validDate($to))
            respond_error('Invalid "to" date format. Use YYYY-MM-DD', 400);
        if ($from && $to && $from > $to)
            respond_error('"from" date must be before "to" date', 400);

        $rows = execute($this->pdo, 'CALL getServiceReport(?, ?)', [$from, $to]);
        respond_success(select_rows($rows, [
            'service_id', 'service_name', 'demand', 'total_appointments',
            'appointment_count',
        ]));
    }

    private function validDate(?string $date): bool {
        if (!$date) return false;
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}