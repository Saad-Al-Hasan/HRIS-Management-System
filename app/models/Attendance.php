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


    public function countTodayByStatus(string $attendanceDate, string $status): int
    {
        if (!in_array($status, ['present', 'late'], true)) {
            return 0;
        }

        $sql = '
        SELECT COUNT(DISTINCT employee_id)
        FROM attendance
        WHERE attendance_date = :attendance_date
          AND entry_time IS NOT NULL
          AND status = :status
    ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':attendance_date' => $attendanceDate,
            ':status' => $status,
        ]);

        return (int) $stmt->fetchColumn();
    }


    public function countEmployeesWithEntryToday(string $attendanceDate): int
    {
        $sql = '
        SELECT COUNT(DISTINCT employee_id)
        FROM attendance
        WHERE attendance_date = :attendance_date
          AND entry_time IS NOT NULL
    ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':attendance_date' => $attendanceDate,
        ]);

        return (int) $stmt->fetchColumn();
    }



    public function getTodayActivity(string $attendanceDate, int $limit = 50): array
    {
        $limit = max(1, min($limit, 500));

        $sql = "
        SELECT
            h.id AS scan_id,
            h.employee_id,
            h.attendance_date,
            h.scan_time,
            h.scan_type,
            h.scan_status,
e.employee_code,
e.user_id,
u.full_name
        FROM attendance_scan_history h
        INNER JOIN employees e
            ON e.id = h.employee_id
        INNER JOIN users u
            ON u.id = e.user_id
        WHERE h.attendance_date = :attendance_date
        ORDER BY h.scan_time ASC, h.id ASC
        LIMIT {$limit}
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':attendance_date' => $attendanceDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    public function recordScanHistory(
        int $employeeId,
        string $attendanceDate,
        string $scanTime,
        string $scanType,
        ?string $scanStatus
    ): void {
        if (!in_array($scanType, ['entry', 'exit'], true)) {
            throw new InvalidArgumentException('Invalid scan type.');
        }

        $sql = '
        INSERT INTO attendance_scan_history (
            employee_id,
            attendance_date,
            scan_time,
            scan_type,
            scan_status
        ) VALUES (
            :employee_id,
            :attendance_date,
            :scan_time,
            :scan_type,
            :scan_status
        )
    ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':attendance_date' => $attendanceDate,
            ':scan_time' => $scanTime,
            ':scan_type' => $scanType,
            ':scan_status' => $scanStatus,
        ]);
    }



    public function createEntryWithHistory(
        int $employeeId,
        string $attendanceDate,
        string $entryTime,
        string $entryStatus,
        string $legacyStatus
    ): int {
        $this->db->beginTransaction();

        try {
            $attendanceId = $this->createEntry(
                $employeeId,
                $attendanceDate,
                $entryTime,
                $entryStatus,
                $legacyStatus
            );

            $this->recordScanHistory(
                $employeeId,
                $attendanceDate,
                $entryTime,
                'entry',
                $entryStatus
            );

            $this->db->commit();

            return $attendanceId;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function recordExitWithHistory(
        int $attendanceId,
        int $employeeId,
        string $attendanceDate,
        string $exitTime,
        string $exitStatus
    ): bool {
        $this->db->beginTransaction();

        try {
            $exitRecorded = $this->recordExit(
                $attendanceId,
                $exitTime,
                $exitStatus
            );

            if (!$exitRecorded) {
                $this->db->rollBack();
                return false;
            }

            $this->recordScanHistory(
                $employeeId,
                $attendanceDate,
                $exitTime,
                'exit',
                $exitStatus
            );

            $this->db->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


}
