<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/AuditLog.php';

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
        try {
            $this->db->beginTransaction();

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

            $created = $stmt->execute([
                ':employee_id' => $employeeId,
                ':leave_type' => $leaveType,
                ':half_day_period' => $halfDayPeriod,
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':reason' => $reason
            ]);

            if (!$created) {
                $this->db->rollBack();
                return false;
            }

            // Retrieve employee details for the audit description.
            $employeeStmt = $this->db->prepare("
            SELECT employee_code
            FROM employees
            WHERE id = :employee_id
            LIMIT 1
        ");

            $employeeStmt->execute([
                ':employee_id' => $employeeId
            ]);

            $employeeCode = $employeeStmt->fetchColumn();

            if (!$employeeCode) {
                throw new RuntimeException(
                    'Unable to identify employee for leave audit.'
                );
            }

            // Record successful employee leave application.
            $auditLog = new AuditLog($this->db);

            $auditLog->create(
                'Employee ' . $employeeCode
                . ' submitted a ' . $leaveType
                . ' leave application for ' . $startDate,
                Auth::userId()
            );

            $this->db->commit();

            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
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
        try {
            $this->db->beginTransaction();

            // Retrieve the pending leave request and employee details.
            $stmt = $this->db->prepare("
            SELECT
                lr.employee_id,
                lr.leave_type,
                lr.start_date,
                e.employee_code
            FROM leave_requests lr
            INNER JOIN employees e
                ON e.id = lr.employee_id
            WHERE lr.id = :id
              AND lr.status = 'pending'
            LIMIT 1
        ");

            $stmt->execute([
                ':id' => $requestId
            ]);

            $leaveRequest = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$leaveRequest) {
                $this->db->commit();
                return false;
            }

            // Update the leave request status.
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

            $updated = $stmt->execute([
                ':status' => $status,
                ':reviewed_by' => $reviewedBy,
                ':id' => $requestId
            ]);

            if (!$updated || $stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }

            // Record successful administrator review.
            $auditLog = new AuditLog($this->db);

            $action = $status === 'approved'
                ? 'approved'
                : 'rejected';

            $auditLog->create(
                'Administrator ' . $action
                . ' leave application for employee '
                . $leaveRequest['employee_code']
                . ' (' . $leaveRequest['leave_type']
                . ', ' . $leaveRequest['start_date'] . ')',
                $reviewedBy
            );

            $this->db->commit();

            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
}
