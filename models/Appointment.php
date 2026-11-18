<?php
class Appointment {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function create(): void {
        $user      = auth_user();
        $data      = request_body();
        $serviceId = filter_var($data['service_id'] ?? null, FILTER_VALIDATE_INT);
        $slotId    = filter_var($data['slot_id'] ?? null, FILTER_VALIDATE_INT);
        $notes     = $data['notes']      ?? '';

        if ($serviceId === false || $serviceId < 1 || $slotId === false || $slotId < 1)
            respond_error('Service ID and Slot ID are required', 400);
        if (!is_string($notes))
            respond_error('Notes must be text', 400);

        $initialSlot = execute($this->pdo, 'CALL getSlotById(?)', [$slotId], 'one');
        if (!$initialSlot) respond_error('Slot not found', 404);

        $lockNames = [
            'appointment-slot-' . substr(hash('sha256', (string)$slotId), 0, 40),
            'appointment-user-' . substr(hash('sha256', implode(':', [
                $user['id'],
                $serviceId,
                $initialSlot['slot_date'],
            ])), 0, 40),
        ];
        sort($lockNames, SORT_STRING);

        $acquiredLocks = [];
        $failure = null;
        $resultData = null;
        $failureStatus = 500;

        try {
            $lockStatement = $this->pdo->prepare('SELECT GET_LOCK(?, 10)');
            foreach ($lockNames as $lockName) {
                $lockStatement->execute([$lockName]);
                $locked = (int)$lockStatement->fetchColumn();
                $lockStatement->closeCursor();
                if ($locked !== 1)
                    throw new DomainException('Bookings are busy. Please try again.', 503);
                $acquiredLocks[] = $lockName;
            }

            $this->pdo->beginTransaction();

            $slot = execute($this->pdo, 'CALL getSlotById(?)', [$slotId], 'one');
            if (!$slot) throw new DomainException('Slot not found', 404);
            if ((int)($slot['service_id'] ?? 0) !== $serviceId)
                throw new DomainException('The selected slot is not for this service', 400);
            if ((int)($slot['available_capacity'] ?? 0) < 1)
                throw new DomainException('Slot is full', 409);

            $service = execute($this->pdo, 'CALL getServiceById(?)', [$serviceId], 'one');
            if (!$service) throw new DomainException('Service not found', 404);
            if (isset($service['is_active']) && !(bool)$service['is_active'])
                throw new DomainException('This service is not available for booking', 400);

            $duration = filter_var($service['duration'] ?? null, FILTER_VALIDATE_INT);
            if ($duration === false || $duration < 1)
                throw new DomainException('Service duration is invalid', 500);

            $slotDate = $slot['slot_date'] ?? '';
            $slotTime = $slot['slot_time'] ?? '';
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $slotDate);
            if (!$date || $date->format('Y-m-d') !== $slotDate || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $slotTime))
                throw new DomainException('Slot date or time is invalid', 400);

            $slotStart = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $slotDate . ' ' . $slotTime);
            if (!$slotStart || $slotStart <= new DateTimeImmutable('now', $slotStart->getTimezone()))
                throw new DomainException('This slot is no longer available for booking', 409);

            $duplicate = execute(
                $this->pdo,
                'CALL checkDuplicateAppointment(?, ?, ?)',
                [$user['id'], $serviceId, $slotDate],
                'one'
            );
            if (($duplicate['total'] ?? 0) > 0)
                throw new DomainException(
                    'You already have an active appointment for this service on this date.',
                    409
                );

            $count = execute($this->pdo, 'CALL getApptCountBySlot(?)', [$slotId], 'one');
            $queue = (int)($count['total'] ?? 0) + 1;
            $offsetMinutes = ($queue - 1) * $duration;
            $personalStart = $slotStart->modify('+' . $offsetMinutes . ' minutes');
            $personalEnd = $personalStart->modify('+' . $duration . ' minutes');

            $result = execute(
                $this->pdo,
                'CALL insertAppointment(?, ?, ?, ?, ?, ?, ?)',
                [
                    $user['id'],
                    $serviceId,
                    $slotId,
                    $queue,
                    $personalStart->format('Y-m-d'),
                    $personalStart->format('H:i:s'),
                    $notes !== '' ? encrypt_data($notes) : null,
                ],
                'one'
            );

            execute($this->pdo, 'CALL updateSlotCapacity(?)', [$slotId], 'none');
            $this->pdo->commit();

            $resultData = [
                'appointment_id'   => $result['appointment_id'] ?? null,
                'queue_number'     => $queue,
                'appointment_date' => $personalStart->format('Y-m-d'),
                'appointment_time' => $personalStart->format('H:i:s'),
                'estimated_end'    => $personalEnd->format('H:i:s'),
            ];
        } catch (DomainException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $failure = $e->getMessage();
            $failureStatus = $e->getCode() ?: 400;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('Appointment booking failed: ' . $e->getMessage());
            $failure = 'Booking could not be completed. Please try again.';
        } finally {
            foreach (array_reverse($acquiredLocks) as $lockName) {
                try {
                    $releaseStatement = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $releaseStatement->execute([$lockName]);
                    $releaseStatement->closeCursor();
                } catch (PDOException $e) {
                    error_log('Failed to release appointment booking lock: ' . $e->getMessage());
                    if ($failure === null) {
                        $failure = 'Booking could not be completed. Please try again.';
                        $failureStatus = 500;
                    }
                }
            }
        }

        if ($failure !== null) respond_error($failure, $failureStatus);
        respond_success($resultData, 'Appointment created', 201);
    }

    public function getByUser(string $userId): void {
        $user = auth_user();
        if (($user['role'] ?? '') !== 'admin' && (string)($user['id'] ?? '') !== $userId)
            respond_error('You may only access your own appointments', 403);

        $appointments = execute($this->pdo, 'CALL getUserAppt(?)', [$userId]);
        respond_success(select_rows($appointments, [
            'id', 'queue_number', 'service_name', 'appointment_date',
            'appointment_time', 'estimated_end', 'status',
        ]));
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
        respond_success(select_rows($appointments, [
            'id', 'queue_number', 'user_name', 'service_name', 'appointment_date',
            'appointment_time', 'estimated_end', 'status',
        ]));
    }
}