<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Model.php';

class LeaveRequest extends Model
{
    /**
     * Retrieve leave requests belonging to one employee.
     */
    public function getByEmployeeId(int $employeeId): array
    {
        $sql = "
            SELECT
                id,
                employee_id,
                leave_type,
                half_day_period,
                start_date,
                end_date,
                reason,
                status,
                reviewed_at,
                created_at
            FROM leave_requests
            WHERE employee_id = :employee_id
            ORDER BY created_at DESC, id DESC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':employee_id' => $employeeId
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Create a new leave request.
     */
    public function create(
        int $employeeId,
        string $leaveType,
        ?string $halfDayPeriod,
        string $startDate,
        string $endDate,
        ?string $reason
    ): bool {
        $sql = "
            INSERT INTO leave_requests (
                employee_id,
                leave_type,
                half_day_period,
                start_date,
                end_date,
                reason,
                status
            )
            VALUES (
                :employee_id,
                :leave_type,
                :half_day_period,
                :start_date,
                :end_date,
                :reason,
                'pending'
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':employee_id' => $employeeId,
            ':leave_type' => $leaveType,
            ':half_day_period' => $halfDayPeriod,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':reason' => $reason
        ]);
    }


    /**
     * Retrieve all leave requests for administrator review.
     * Optionally filter by employee code.
     */
    public function getAllForAdmin(?string $employeeCode = null): array
    {
        $sql = "
        SELECT
            lr.id,
            lr.employee_id,
            lr.leave_type,
            lr.half_day_period,
            lr.start_date,
            lr.end_date,
            lr.reason,
            lr.status,
            lr.reviewed_at,
            lr.created_at,
            e.employee_code,
            u.full_name
        FROM leave_requests lr
        INNER JOIN employees e
            ON e.id = lr.employee_id
        INNER JOIN users u
            ON u.id = e.user_id
    ";

        if ($employeeCode !== null && $employeeCode !== '') {
            $sql .= " WHERE UPPER(e.employee_code) = :employee_code";
        }

        $sql .= "
        ORDER BY
            CASE
                WHEN lr.status = 'pending' THEN 0
                ELSE 1
            END,
            lr.created_at DESC,
            lr.id DESC
    ";

        $stmt = $this->db->prepare($sql);

        $params = [];

        if ($employeeCode !== null && $employeeCode !== '') {
            $params[':employee_code'] = strtoupper($employeeCode);
        }

        $stmt->execute($params);

        return $stmt->fetchAll();
    }


    /**
     * Update a leave request's review status.
     */
    public function updateStatus(
        int $requestId,
        string $status,
        int $reviewedBy
    ): bool {
        $sql = "
        UPDATE leave_requests
        SET
            status = :status,
            reviewed_by = :reviewed_by,
            reviewed_at = NOW()
        WHERE id = :id
          AND status = 'pending'
    ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':status' => $status,
            ':reviewed_by' => $reviewedBy,
            ':id' => $requestId
        ]);
    }
}
