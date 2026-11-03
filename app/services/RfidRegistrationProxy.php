<?php

declare(strict_types=1);

require_once __DIR__ . '/RfidRegistrationServiceInterface.php';
require_once __DIR__ . '/../core/Auth.php';

/**
 * Design Pattern: Protection Proxy
 *
 * Checks administrator authorization before allowing
 * an RFID registration request to reach the real service.
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
        string $rfidIdentifier
    ): array {
        Auth::startSession();

        if (!Auth::check() || Auth::roleId() !== 1) {
            throw new RuntimeException(
                'Access denied. Administrator permission is required.'
            );
        }

        if ($employeeId <= 0) {
            throw new InvalidArgumentException(
                'Please select a valid employee.'
            );
        }

        if (trim($rfidIdentifier) === '') {
            throw new InvalidArgumentException(
                'Please scan a valid RFID card.'
            );
        }

        // Forward the request only after the checks pass.
        return $this->realService->register(
            $employeeId,
            $rfidIdentifier
        );
    }
}