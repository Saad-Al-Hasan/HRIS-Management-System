<?php

declare(strict_types=1);

interface RfidRegistrationServiceInterface
{
    public function register(
        int $employeeId,
        string $rfidIdentifier,
        ?int $userId = null
    ): array;
}
