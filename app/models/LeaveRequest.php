<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Model.php';

class LeaveRequest extends Model
{
    public const TYPE_FULL_DAY = 'full_day';
    public const TYPE_HALF_DAY = 'half_day';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Create a new leave request (status defaults to pending).
     * A single leave date is stored in both start_date and end_date.
     */
    public function create(
        int $employeeId,
        string $leaveDate,
        string $leaveType,
        string $reason
    ): bool {
        $sql = '
            INSERT INTO leave_requests
                (employee_id, leave_type, start_date, end_date, reason, status)
            VALUES
                (:employee_id, :leave_type, :start_date, :end_date, :reason, :status)
        ';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':employee_id' => $employeeId,
            ':leave_type' => $leaveType,
            ':start_date' => $leaveDate,
            ':end_date' => $leaveDate,
            ':reason' => $reason,
            ':status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Leave requests of one employee (newest first).
     */
    public function findByEmployeeId(int $employeeId): array
    {
        $sql = '
            SELECT
                id,
                leave_type,
                start_date AS leave_date,
                reason,
                status,
                created_at
            FROM leave_requests
            WHERE employee_id = :employee_id
            ORDER BY start_date DESC, id DESC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':employee_id' => $employeeId]);

        return $stmt->fetchAll();
    }

    /**
     * All leave requests for the administrator, optionally filtered by status.
     */
    public function getAll(?string $status = null): array
    {
        $sql = '
            SELECT
                lr.id,
                lr.employee_id,
                lr.leave_type,
                lr.start_date AS leave_date,
                lr.reason,
                lr.status,
                lr.reviewed_at,
                lr.created_at,
                e.employee_code,
                u.full_name AS employee_name,
                ru.full_name AS reviewer_name
            FROM leave_requests lr
            INNER JOIN employees e
                ON e.id = lr.employee_id
            INNER JOIN users u
                ON u.id = e.user_id
            LEFT JOIN users ru
                ON ru.id = lr.reviewed_by
        ';

        $params = [];

        if ($status !== null) {
            $sql .= ' WHERE lr.status = :status';
            $params[':status'] = $status;
        }

        $sql .= '
            ORDER BY
                (lr.status = \'pending\') DESC,
                lr.start_date DESC,
                lr.id DESC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * True when the employee already has a pending/approved request
     * covering the given date.
     */
    public function existsForDate(int $employeeId, string $date): bool
    {
        $sql = '
            SELECT COUNT(*)
            FROM leave_requests
            WHERE employee_id = :employee_id
              AND status IN (\'pending\', \'approved\')
              AND :leave_date BETWEEN start_date AND end_date
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':leave_date' => $date,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Approve or reject a request. Only pending requests can be changed.
     * Returns true when a row was actually updated.
     */
    public function updateStatus(
        int $leaveId,
        string $status,
        int $reviewerUserId
    ): bool {
        $sql = '
            UPDATE leave_requests
            SET
                status = :status,
                reviewed_by = :reviewed_by,
                reviewed_at = NOW()
            WHERE id = :id
              AND status = \'pending\'
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status' => $status,
            ':reviewed_by' => $reviewerUserId,
            ':id' => $leaveId,
        ]);

        return $stmt->rowCount() > 0;
    }
}
