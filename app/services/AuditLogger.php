<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/AuditLog.php';

class AuditLogger
{
    /**
     * Record an activity performed by the currently authenticated user.
     *
     * An existing PDO connection may be supplied to keep the audit
     * insert inside the same database transaction as the main operation.
     */
    public static function log(
        string $activity,
        ?PDO $connection = null
    ): bool {
        $userId = Auth::userId();

        $auditLog = new AuditLog($connection);

        return $auditLog->create($activity, $userId);
    }
}
