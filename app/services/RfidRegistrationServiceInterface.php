<?php

declare(strict_types=1);

interface RfidRegistrationServiceInterface
{
    public function register(
        int $employeeId,
        string $rfidIdentifier
    ): array;
}