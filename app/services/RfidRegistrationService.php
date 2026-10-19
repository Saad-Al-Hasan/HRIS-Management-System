<?php

declare(strict_types=1);

require_once __DIR__ . '/RfidRegistrationServiceInterface.php';
require_once __DIR__ . '/../models/RfidCard.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/User.php';

class RfidRegistrationService implements RfidRegistrationServiceInterface
{
    private RfidCard $rfidCardModel;
    private Employee $employeeModel;
    private User $userModel;

    public function __construct()
    {
        $this->rfidCardModel = new RfidCard();
        $this->employeeModel = new Employee();
        $this->userModel = new User();
    }

    public function register(
        int $employeeId,
        string $rfidIdentifier,
        ?int $userId = null
    ): array {
        $rfidIdentifier = strtoupper(
            preg_replace('/\s+/', '', trim($rfidIdentifier))
        );

        if ($rfidIdentifier === '') {
            throw new InvalidArgumentException(
                'Please scan a valid RFID card.'
            );
        }

        if ($this->rfidCardModel->findByIdentifier($rfidIdentifier)) {
            throw new RuntimeException(
                'This RFID card is already assigned.'
            );
        }

        if ($userId !== null) {
            if ($userId <= 0) {
                throw new InvalidArgumentException(
                    'Please select a valid administrator.'
                );
            }

            $user = $this->userModel->findById($userId);

            if (
                !$user ||
                (int) $user['role_id'] !== 1
            ) {
                throw new RuntimeException(
                    'The selected administrator was not found.'
                );
            }

            if ($this->rfidCardModel->findByUserId($userId)) {
                throw new RuntimeException(
                    'This administrator already has an RFID card assigned.'
                );
            }

            $this->rfidCardModel->createForUser(
                $userId,
                $rfidIdentifier
            );

            return [
                'success' => true,
                'message' => 'RFID card assigned to administrator successfully.',
                'user_id' => $userId,
                'rfid_identifier' => $rfidIdentifier,
            ];
        }

        if ($employeeId <= 0) {
            throw new InvalidArgumentException(
                'Please select an employee or administrator.'
            );
        }

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

        if ($this->rfidCardModel->findByEmployeeId($employeeId)) {
            throw new RuntimeException(
                'This employee already has an RFID card assigned.'
            );
        }

        $this->rfidCardModel->createForEmployee(
            $employeeId,
            $rfidIdentifier
        );

        return [
            'success' => true,
            'message' => 'RFID card registered successfully.',
            'employee_id' => $employeeId,
            'rfid_identifier' => $rfidIdentifier,
        ];
    }
}
