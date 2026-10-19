<?php

declare(strict_types=1);

require_once __DIR__ . '/RfidRegistrationServiceInterface.php';
require_once __DIR__ . '/../core/Auth.php';

/**
 * Design Pattern: Protection Proxy
 *
 * Checks administrator authorization before forwarding
 * an RFID registration request to the real service.
 */
class RfidRegistrationProxy implements RfidRegistrationServiceInterface
{
    private RfidRegistrationServiceInterface $realService;

    public function __construct(
        RfidRegistrationServiceInterface $realService
    ) {
        $this->realService = $realService;
    }

    public function register(
        int $employeeId,
        string $rfidIdentifier,
        ?int $userId = null
    ): array {
        Auth::startSession();

        if (!Auth::check() || Auth::roleId() !== 1) {
            throw new RuntimeException(
                'Access denied. Administrator permission is required.'
            );
        }

        if ($userId !== null && $employeeId > 0) {
            throw new InvalidArgumentException(
                'Select either an employee or an administrator, not both.'
            );
        }

        if ($userId === null && $employeeId <= 0) {
            throw new InvalidArgumentException(
                'Please select an employee or administrator.'
            );
        }

        if (trim($rfidIdentifier) === '') {
            throw new InvalidArgumentException(
                'Please scan a valid RFID card.'
            );
        }

        return $this->realService->register(
            $employeeId,
            $rfidIdentifier,
            $userId
        );
    }
}
