<?php

declare(strict_types=1);

require_once __DIR__ . '/RfidRegistrationServiceInterface.php';
require_once __DIR__ . '/../models/RfidCard.php';
require_once __DIR__ . '/../models/Employee.php';

class RfidRegistrationService implements RfidRegistrationServiceInterface
{
    private RfidCard $rfidCardModel;
    private Employee $employeeModel;

    public function __construct()
    {
        $this->rfidCardModel = new RfidCard();
        $this->employeeModel = new Employee();
    }

    public function register(
        int $employeeId,
        string $rfidIdentifier
    ): array {
        $rfidIdentifier = strtoupper(
            preg_replace('/\s+/', '', trim($rfidIdentifier))
        );

        if ($employeeId <= 0) {
            throw new InvalidArgumentException(
                'Please select a valid employee.'
            );
        }

        if ($rfidIdentifier === '') {
            throw new InvalidArgumentException(
                'Please scan a valid RFID card.'
            );
        }

        // Verify the selected employee exists.
        $employeeExists = false;

        foreach ($this->employeeModel->getAll() as $employee) {
            if ((int) $employee['employee_id'] === $employeeId) {
                $employeeExists = true;
                break;
            }
        }

        if (!$employeeExists) {
            throw new RuntimeException(
                'The selected employee was not found.'
            );
        }

        if ($this->rfidCardModel->findByIdentifier($rfidIdentifier)) {
            throw new RuntimeException(
                'This RFID card is already assigned to an employee.'
            );
        }

        if ($this->rfidCardModel->findByEmployeeId($employeeId)) {
            throw new RuntimeException(
                'This employee already has an RFID card assigned.'
            );
        }

        try {
            $this->rfidCardModel->create(
                $employeeId,
                $rfidIdentifier
            );
        } catch (PDOException $exception) {
            // Database UNIQUE constraints remain the final safeguard.
            if ($exception->getCode() === '23000') {
                throw new RuntimeException(
                    'This RFID card or employee already has an assignment.'
                );
            }

            throw $exception;
        }

        return [
            'success' => true,
            'message' => 'RFID card registered successfully.',
            'employee_id' => $employeeId,
            'rfid_identifier' => $rfidIdentifier,
        ];
    }
}