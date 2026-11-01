<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Model.php';

class Attendance extends Model
{
    public function findByEmployeeAndDate(
        int $employeeId,
        string $attendanceDate
    ): ?array {
        $sql = '
            SELECT
                id,
                employee_id,
                attendance_date,
                entry_time,
                entry_status,
                exit_time,
                exit_status,
                status
            FROM attendance
            WHERE employee_id = :employee_id
              AND attendance_date = :attendance_date
            ORDER BY id DESC
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':attendance_date' => $attendanceDate,
        ]);

        $record = $stmt->fetch();

        return $record ?: null;
    }

    public function createEntry(
        int $employeeId,
        string $attendanceDate,
        string $entryTime,
        string $entryStatus,
        string $status = 'present'
    ): int {
        $sql = '
            INSERT INTO attendance (
                employee_id,
                attendance_date,
                entry_time,
                entry_status,
                status
            ) VALUES (
                :employee_id,
                :attendance_date,
                :entry_time,
                :entry_status,
                :status
            )
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':attendance_date' => $attendanceDate,
            ':entry_time' => $entryTime,
            ':entry_status' => $entryStatus,
            ':status' => $status,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function recordExit(
        int $attendanceId,
        string $exitTime,
        string $exitStatus
    ): bool {
        // Confirm the attendance record exists first.
        $existingRecord = $this->findById($attendanceId);

        if ($existingRecord === null) {
            return false;
        }

        $sql = '
        UPDATE attendance
        SET
            exit_time = :exit_time,
            exit_status = :exit_status
        WHERE id = :attendance_id
    ';

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':exit_time' => $exitTime,
            ':exit_status' => $exitStatus,
            ':attendance_id' => $attendanceId,
        ]);

        return true;
    }

    public function findLatestOpenRecord(int $employeeId): ?array
    {
        $sql = '
            SELECT
                id,
                employee_id,
                attendance_date,
                entry_time,
                entry_status,
                exit_time,
                exit_status,
                status
            FROM attendance
            WHERE employee_id = :employee_id
              AND exit_time IS NULL
            ORDER BY attendance_date DESC, entry_time DESC, id DESC
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
        ]);

        $record = $stmt->fetch();

        return $record ?: null;
    }

    public function findById(int $attendanceId): ?array
    {
        $sql = '
            SELECT
                id,
                employee_id,
                attendance_date,
                entry_time,
                entry_status,
                exit_time,
                exit_status,
                status
            FROM attendance
            WHERE id = :attendance_id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':attendance_id' => $attendanceId,
        ]);

        $record = $stmt->fetch();

        return $record ?: null;
    }
}
