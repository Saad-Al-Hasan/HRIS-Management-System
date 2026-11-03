<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Model.php';

class WorkSchedule extends Model
{

    public function getAll(): array
    {
        $sql = '
        SELECT
            ws.id,
            ws.name,
            ws.expected_entry_time,
            ws.expected_exit_time,
            ws.working_hours,
            COUNT(es.employee_id) AS total_employee_assigned
        FROM work_schedules ws
        LEFT JOIN employee_schedules es
            ON es.schedule_id = ws.id
        GROUP BY
            ws.id,
            ws.name,
            ws.expected_entry_time,
            ws.expected_exit_time,
            ws.working_hours
        ORDER BY ws.expected_entry_time ASC, ws.id ASC
    ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }


    public function findById(int $scheduleId): ?array
    {
        $sql = '
            SELECT
                id,
                name,
                expected_entry_time,
                expected_exit_time,
                working_hours
            FROM work_schedules
            WHERE id = :schedule_id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':schedule_id' => $scheduleId,
        ]);

        $schedule = $stmt->fetch();

        return $schedule ?: null;
    }

    public function findAssignedToEmployee(int $employeeId): ?array
    {
        $sql = '
            SELECT
                ws.id,
                ws.name,
                ws.expected_entry_time,
                ws.expected_exit_time,
                ws.working_hours
            FROM employee_schedules es
            INNER JOIN work_schedules ws
                ON ws.id = es.schedule_id
            WHERE es.employee_id = :employee_id
            ORDER BY es.id DESC
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
        ]);

        $schedule = $stmt->fetch();

        return $schedule ?: null;
    }


    public function findEffectiveScheduleForEmployee(int $employeeId): ?array
    {
        $assignedSchedule = $this->findAssignedToEmployee($employeeId);

        if ($assignedSchedule !== null) {
            return $assignedSchedule;
        }

        $sql = '
            SELECT
                id,
                name,
                expected_entry_time,
                expected_exit_time,
                working_hours
            FROM work_schedules
            WHERE name = :default_name
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':default_name' => '9 AM - 6 PM',
        ]);

        $schedule = $stmt->fetch();

        return $schedule ?: null;
    }


    public function create(
        string $name,
        string $entryTime,
        string $exitTime
    ): int {
        $this->validateScheduleTimes($entryTime, $exitTime);

        $sql = '
            INSERT INTO work_schedules (
                name,
                expected_entry_time,
                expected_exit_time,
                working_hours
            ) VALUES (
                :name,
                :entry_time,
                :exit_time,
                MOD(
                    TIME_TO_SEC(:exit_time_calc)
                    - TIME_TO_SEC(:entry_time_calc)
                    + 86400,
                    86400
                ) / 3600
            )
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':entry_time' => $entryTime,
            ':exit_time' => $exitTime,
            ':exit_time_calc' => $exitTime,
            ':entry_time_calc' => $entryTime,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(
        int $scheduleId,
        string $name,
        string $entryTime,
        string $exitTime
    ): bool {
        $this->validateScheduleTimes($entryTime, $exitTime);

        $sql = '
            UPDATE work_schedules
            SET
                name = :name,
                expected_entry_time = :entry_time,
                expected_exit_time = :exit_time,
                working_hours = MOD(
                    TIME_TO_SEC(:exit_time_calc)
                    - TIME_TO_SEC(:entry_time_calc)
                    + 86400,
                    86400
                ) / 3600
            WHERE id = :schedule_id
        ';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':name' => $name,
            ':entry_time' => $entryTime,
            ':exit_time' => $exitTime,
            ':exit_time_calc' => $exitTime,
            ':entry_time_calc' => $entryTime,
            ':schedule_id' => $scheduleId,
        ]);
    }

    public function getEmployeesWithSchedules(): array
    {
        $sql = '
            SELECT
                e.id AS employee_id,
                e.employee_code,
                e.department,
                e.designation,
                u.full_name,
                u.status AS employee_status,
                ws.id AS schedule_id,
                ws.name AS schedule_name,
                ws.expected_entry_time,
                ws.expected_exit_time
            FROM employees e
            INNER JOIN users u
                ON u.id = e.user_id
            LEFT JOIN employee_schedules es
                ON es.employee_id = e.id
            LEFT JOIN work_schedules ws
                ON ws.id = es.schedule_id
            WHERE u.role_id = 2
            ORDER BY e.employee_code ASC, e.id ASC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function assignToEmployee(
        int $employeeId,
        int $scheduleId
    ): bool {
        $this->db->beginTransaction();

        try {
            $employeeSql = '
                SELECT e.id
                FROM employees e
                INNER JOIN users u
                    ON u.id = e.user_id
                WHERE e.id = :employee_id
                  AND u.role_id = 2
                LIMIT 1
            ';

            $employeeStmt = $this->db->prepare($employeeSql);
            $employeeStmt->execute([
                ':employee_id' => $employeeId,
            ]);

            if (!$employeeStmt->fetch()) {
                $this->db->rollBack();
                return false;
            }

            if ($this->findById($scheduleId) === null) {
                $this->db->rollBack();
                return false;
            }

            $assignmentSql = '
                INSERT INTO employee_schedules (
                    employee_id,
                    schedule_id
                ) VALUES (
                    :employee_id,
                    :schedule_id
                )
                ON DUPLICATE KEY UPDATE
                    schedule_id = VALUES(schedule_id)
            ';

            $assignmentStmt = $this->db->prepare($assignmentSql);
            $assignmentStmt->execute([
                ':employee_id' => $employeeId,
                ':schedule_id' => $scheduleId,
            ]);

            $this->db->commit();

            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }



    public function assignAllEmployeesToSchedule(int $scheduleId): int
    {
        if ($scheduleId < 1 || $this->findById($scheduleId) === null) {
            throw new InvalidArgumentException('Please select a valid work schedule.');
        }

        $this->db->beginTransaction();

        try {
            // Get every employee account, including employees without an assignment.
            $employeeSql = '
            SELECT e.id
            FROM employees e
            INNER JOIN users u
                ON u.id = e.user_id
            WHERE u.role_id = 2
            ORDER BY e.id
            FOR UPDATE
        ';

            $employeeStmt = $this->db->prepare($employeeSql);
            $employeeStmt->execute();
            $employeeIds = $employeeStmt->fetchAll(PDO::FETCH_COLUMN);

            // Update existing assignments or insert missing ones.
            $assignmentSql = '
            INSERT INTO employee_schedules (employee_id, schedule_id)
            VALUES (:employee_id, :schedule_id)
            ON DUPLICATE KEY UPDATE schedule_id = VALUES(schedule_id)
        ';

            $assignmentStmt = $this->db->prepare($assignmentSql);

            foreach ($employeeIds as $employeeId) {
                $assignmentStmt->execute([
                    ':employee_id' => (int) $employeeId,
                    ':schedule_id' => $scheduleId,
                ]);
            }

            $this->db->commit();

            return count($employeeIds);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }




    public function delete(int $scheduleId): bool
    {
        // Protect the default 9 AM - 6 PM schedule.
        if ($scheduleId === 1) {
            throw new InvalidArgumentException(
                'The default 9 AM - 6 PM schedule cannot be deleted.'
            );
        }

        // Verify that the schedule exists.
        if ($this->findById($scheduleId) === null) {
            return false;
        }

        // Do not delete a schedule assigned to any employee.
        $assignmentSql = '
        SELECT COUNT(*)
        FROM employee_schedules
        WHERE schedule_id = :schedule_id
    ';

        $assignmentStmt = $this->db->prepare($assignmentSql);
        $assignmentStmt->execute([
            ':schedule_id' => $scheduleId,
        ]);

        if ((int) $assignmentStmt->fetchColumn() > 0) {
            throw new InvalidArgumentException(
                'This shift contains assigned employees. Change their shifts first.'
            );
        }

        // Delete only the selected schedule.
        $deleteSql = '
        DELETE FROM work_schedules
        WHERE id = :schedule_id
    ';

        $deleteStmt = $this->db->prepare($deleteSql);
        $deleteStmt->execute([
            ':schedule_id' => $scheduleId,
        ]);

        return $deleteStmt->rowCount() === 1;
    }



    private function validateScheduleTimes(
        string $entryTime,
        string $exitTime
    ): void {
        $validTimePattern = '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/';

        if (
            preg_match($validTimePattern, $entryTime) !== 1
            || preg_match($validTimePattern, $exitTime) !== 1
        ) {
            throw new InvalidArgumentException(
                'Enter valid entry and exit times.'
            );
        }

        $entryParts = array_map('intval', explode(':', $entryTime));
        $exitParts = array_map('intval', explode(':', $exitTime));

        $entrySeconds = ($entryParts[0] * 3600)
            + ($entryParts[1] * 60)
            + ($entryParts[2] ?? 0);

        $exitSeconds = ($exitParts[0] * 3600)
            + ($exitParts[1] * 60)
            + ($exitParts[2] ?? 0);

        if ($exitSeconds <= $entrySeconds) {
            throw new InvalidArgumentException(
                'Exit time must be later than entry time. Overnight schedules are not supported.'
            );
        }

        if (($exitSeconds - $entrySeconds) > (9 * 3600)) {
            throw new InvalidArgumentException(
                'A work schedule cannot exceed 9 working hours.'
            );
        }
    }


}
