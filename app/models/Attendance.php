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
    A_E_entry_time,
    A_E_entry_status,
    A_E_exit_time,
A_E_exit_status,
A_E_status,
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

          -- Admin-edited entry time has priority.
          AND COALESCE(A_E_entry_time, entry_time) IS NOT NULL

          -- Admin-edited attendance status has priority.
          AND COALESCE(NULLIF(A_E_status, \'\'), status) = :status
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

          -- Use admin-edited entry time when available.
          AND COALESCE(A_E_entry_time, entry_time) IS NOT NULL
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
    h.source,
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
        scan_status,
        source
    ) VALUES (
        :employee_id,
        :attendance_date,
        :scan_time,
        :scan_type,
        :scan_status,
        :source
    )
';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':attendance_date' => $attendanceDate,
            ':scan_time' => $scanTime,
            ':scan_type' => $scanType,
            ':scan_status' => $scanStatus,
            ':source' => 'RFID',
        ]);
    }



    /**
     * Insert or update one administrator-edited entry/exit history row.
     *
     * RFID history rows are never modified by this method.
     */
    private function upsertAdminEditedScanHistory(
        int $employeeId,
        string $attendanceDate,
        string $scanTime,
        string $scanType,
        string $scanStatus
    ): void {
        if (!in_array($scanType, ['entry', 'exit'], true)) {
            throw new InvalidArgumentException('Invalid admin-edited scan type.');
        }

        // Find the existing admin-edited row for this employee, date, and scan type.
        $findSql = '
        SELECT id
        FROM attendance_scan_history
        WHERE employee_id = :employee_id
          AND attendance_date = :attendance_date
          AND scan_type = :scan_type
          AND source = :source
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ';

        $findStmt = $this->db->prepare($findSql);
        $findStmt->execute([
            ':employee_id' => $employeeId,
            ':attendance_date' => $attendanceDate,
            ':scan_type' => $scanType,
            ':source' => 'A_E_modify',
        ]);

        $existingScan = $findStmt->fetch(PDO::FETCH_ASSOC);

        if ($existingScan !== false) {
            // Update the existing A_E_modify row.
            $updateSql = '
            UPDATE attendance_scan_history
            SET
                scan_time = :scan_time,
                scan_status = :scan_status
            WHERE id = :scan_id
              AND source = :source
        ';

            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([
                ':scan_time' => $scanTime,
                ':scan_status' => $scanStatus,
                ':scan_id' => (int) $existingScan['id'],
                ':source' => 'A_E_modify',
            ]);

            return;
        }

        // No matching admin-edited row exists: create one.
        $insertSql = '
        INSERT INTO attendance_scan_history (
            employee_id,
            attendance_date,
            scan_time,
            scan_type,
            scan_status,
            source
        ) VALUES (
            :employee_id,
            :attendance_date,
            :scan_time,
            :scan_type,
            :scan_status,
            :source
        )
    ';

        $insertStmt = $this->db->prepare($insertSql);
        $insertStmt->execute([
            ':employee_id' => $employeeId,
            ':attendance_date' => $attendanceDate,
            ':scan_time' => $scanTime,
            ':scan_type' => $scanType,
            ':scan_status' => $scanStatus,
            ':source' => 'A_E_modify',
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

    /**
     * Retrieve attendance records for administrative attendance management.
     *
     * @param string|null $attendanceDate Optional date filter in YYYY-MM-DD format.
     * @return array
     */
    public function getAdministrativeAttendanceRecords(
        ?string $attendanceDate = null
    ): array {
        $sql = '
    SELECT
        a.id AS attendance_id,
        a.employee_id,
        e.employee_code,
        u.full_name,
        e.department,
        e.designation,
        a.attendance_date,

        -- Original RFID attendance values
        a.entry_time,
        a.entry_status,
        a.exit_time,
        a.exit_status,
        a.status AS attendance_status,

        -- Administrator-edited attendance values
        a.A_E_entry_time,
        a.A_E_entry_status,
        a.A_E_exit_time,
        a.A_E_exit_status,
        a.A_E_status

    FROM attendance a

    INNER JOIN employees e
        ON e.id = a.employee_id

    INNER JOIN users u
        ON u.id = e.user_id

    WHERE u.role_id = 2
';

        $params = [];

        if ($attendanceDate !== null && $attendanceDate !== '') {
            $sql .= ' AND a.attendance_date = :attendance_date';
            $params[':attendance_date'] = $attendanceDate;
        }

        $sql .= '
        ORDER BY
            a.attendance_date DESC,
            a.entry_time DESC,
            e.employee_code ASC
    ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Retrieve all distinct employee departments.
     *
     * @return array
     */
    public function getAllDepartments(): array
    {
        $sql = "
            SELECT DISTINCT department
            FROM employees
            WHERE department IS NOT NULL
              AND TRIM(department) <> ''
            ORDER BY department ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Retrieve active employee accounts belonging to a department.
     *
     * @param string $department
     * @return array
     */
    public function getEmployeesByDepartment(string $department): array
    {
        if (trim($department) === '') {
            return [];
        }

        $sql = "
            SELECT
                e.id,
                e.employee_code,
                e.department,
                u.full_name
            FROM employees e
            INNER JOIN users u
                ON u.id = e.user_id
            WHERE e.department = :department
              AND u.role_id = 2
              AND u.status = 'active'
            ORDER BY e.employee_code ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':department' => $department,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Retrieve attendance records for one employee within a date range.
     *
     * @param int $employeeId
     * @param string $fromDate
     * @param string $toDate
     * @return array
     */
    public function getEmployeeAttendanceByDateRange(
        int $employeeId,
        string $fromDate,
        string $toDate
    ): array {
        if (
            $employeeId <= 0 ||
            $fromDate === '' ||
            $toDate === '' ||
            $fromDate > $toDate
        ) {
            return [];
        }

        $sql = "
SELECT
    a.id AS attendance_id,
    a.employee_id,
    e.employee_code,
    u.id AS user_id,
    u.full_name,
    e.department,
    e.designation,
    a.attendance_date,

    -- Effective attendance values (Admin Edited takes priority)
    COALESCE(a.A_E_entry_time, a.entry_time) AS entry_time,
    COALESCE(a.A_E_entry_status, a.entry_status) AS entry_status,

    COALESCE(a.A_E_exit_time, a.exit_time) AS exit_time,
    COALESCE(a.A_E_exit_status, a.exit_status) AS exit_status,

    COALESCE(a.A_E_status, a.status) AS attendance_status,

    -- Original Admin Edited values (required for source identification)
    a.A_E_entry_time,
    a.A_E_entry_status,
    a.A_E_exit_time,
    a.A_E_exit_status,
    a.A_E_status

FROM attendance a

INNER JOIN employees e
    ON e.id = a.employee_id

INNER JOIN users u
    ON u.id = e.user_id

WHERE a.employee_id = :employee_id
  AND e.id = :employee_id_check
  AND u.role_id = 2
  AND a.attendance_date BETWEEN :from_date AND :to_date

ORDER BY
    a.attendance_date DESC,
    a.entry_time DESC,
    e.employee_code ASC
";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':employee_id' => $employeeId,
            ':employee_id_check' => $employeeId,
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    /**
     * Retrieve attendance records for all employees in a department
     * within a selected date range.
     *
     * @param string $department
     * @param string $fromDate
     * @param string $toDate
     * @return array
     */
    public function getDepartmentAttendanceByDateRange(
        string $department,
        string $fromDate,
        string $toDate
    ): array {
        if (
            trim($department) === '' ||
            $fromDate === '' ||
            $toDate === '' ||
            $fromDate > $toDate
        ) {
            return [];
        }

        $sql = "
        SELECT
            a.id AS attendance_id,
            a.employee_id,
            e.employee_code,
            u.id AS user_id,
            u.full_name,
            e.department,
            e.designation,
            a.attendance_date,

            -- Effective attendance values (Admin Edited takes priority)
            COALESCE(a.A_E_entry_time, a.entry_time) AS entry_time,
            COALESCE(a.A_E_entry_status, a.entry_status) AS entry_status,

            COALESCE(a.A_E_exit_time, a.exit_time) AS exit_time,
            COALESCE(a.A_E_exit_status, a.exit_status) AS exit_status,

            COALESCE(a.A_E_status, a.status) AS attendance_status,

            -- Original Admin Edited values (required for source identification)
            a.A_E_entry_time,
            a.A_E_entry_status,
            a.A_E_exit_time,
            a.A_E_exit_status,
            a.A_E_status

        FROM attendance a

        INNER JOIN employees e
            ON e.id = a.employee_id

        INNER JOIN users u
            ON u.id = e.user_id

        WHERE e.department = :department
          AND u.role_id = 2
          AND u.status = 'active'
          AND a.attendance_date BETWEEN :from_date AND :to_date

        ORDER BY
            a.attendance_date DESC,
            a.entry_time DESC,
            e.employee_code ASC
    ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':department' => $department,
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    /**
     * Retrieve all RFID scans for one employee on a specific date.
     */
    public function getEmployeeScanHistoryForDate(
        int $employeeId,
        string $attendanceDate
    ): array {
        if ($employeeId <= 0 || $attendanceDate === '') {
            return [];
        }

        $sql = "
SELECT
    id AS scan_id,
    employee_id,
    attendance_date,
    scan_time,
    scan_type,
    scan_status,
    source
FROM attendance_scan_history
WHERE employee_id = :employee_id
  AND attendance_date = :attendance_date
ORDER BY scan_time ASC, id ASC
    ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':employee_id' => $employeeId,
            ':attendance_date' => $attendanceDate,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function createManualAttendanceWithHistory(
        int $employeeId,
        string $attendanceDate,
        string $entryTime,
        string $entryStatus,
        string $legacyStatus,
        ?string $exitTime,
        ?string $exitStatus
    ): int {
        if ($employeeId <= 0) {
            throw new InvalidArgumentException('Invalid employee selected.');
        }

        if ($this->findByEmployeeAndDate($employeeId, $attendanceDate) !== null) {
            throw new InvalidArgumentException(
                'Attendance already exists for this employee on the selected date.'
            );
        }

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

            if ($exitTime !== null && $exitStatus !== null) {
                $this->recordExit(
                    $attendanceId,
                    $exitTime,
                    $exitStatus
                );

                $this->recordScanHistory(
                    $employeeId,
                    $attendanceDate,
                    $exitTime,
                    'exit',
                    $exitStatus
                );
            }

            $this->db->commit();

            return $attendanceId;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    /**
     * Save administrator-edited attendance times.
     *
     * Empty values preserve previously saved admin-edited values.
     * Original RFID attendance fields and scan history are never modified.
     */
    public function saveAdminEditedAttendance(
        int $employeeId,
        string $attendanceDate,
        ?string $entryTime,
        ?string $entryStatus,
        ?string $exitTime,
        ?string $exitStatus,
        ?string $overallStatus
    ): int {
        if ($employeeId <= 0) {
            throw new InvalidArgumentException('Invalid employee selected.');
        }

        $this->db->beginTransaction();

        try {
            $findSql = '
            SELECT id
            FROM attendance
            WHERE employee_id = :employee_id
              AND attendance_date = :attendance_date
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ';

            $findStmt = $this->db->prepare($findSql);
            $findStmt->execute([
                ':employee_id' => $employeeId,
                ':attendance_date' => $attendanceDate,
            ]);

            $existing = $findStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing !== false) {
                $attendanceId = (int) $existing['id'];

                $updateSql = '
    UPDATE attendance
    SET
        A_E_entry_time = COALESCE(
            :entry_time,
            A_E_entry_time
        ),
        A_E_entry_status = COALESCE(
            :entry_status,
            A_E_entry_status
        ),
        A_E_exit_time = COALESCE(
            :exit_time,
            A_E_exit_time
        ),
        A_E_exit_status = COALESCE(
            :exit_status,
            A_E_exit_status
        ),
        A_E_status = COALESCE(
            :overall_status,
            A_E_status
        )
    WHERE id = :attendance_id
';

                $updateStmt = $this->db->prepare($updateSql);

                $updateStmt->execute([
                    ':entry_time' => $entryTime,
                    ':entry_status' => $entryStatus,
                    ':exit_time' => $exitTime,
                    ':exit_status' => $exitStatus,
                    ':overall_status' => $overallStatus,
                    ':attendance_id' => $attendanceId,
                ]);

            } else {
                // Create an admin-edited-only record.
                // Original RFID fields remain NULL.
                $insertSql = '
    INSERT INTO attendance (
        employee_id,
        attendance_date,
        A_E_entry_time,
        A_E_entry_status,
        A_E_exit_time,
        A_E_exit_status,
        A_E_status
    ) VALUES (
        :employee_id,
        :attendance_date,
        :entry_time,
        :entry_status,
        :exit_time,
        :exit_status,
        :overall_status
    )
';

                $insertStmt = $this->db->prepare($insertSql);

                $insertStmt->execute([
                    ':employee_id' => $employeeId,
                    ':attendance_date' => $attendanceDate,
                    ':entry_time' => $entryTime,
                    ':entry_status' => $entryStatus,
                    ':exit_time' => $exitTime,
                    ':exit_status' => $exitStatus,
                    ':overall_status' => $overallStatus,
                ]);

                $attendanceId = (int) $this->db->lastInsertId();
            }


            // Synchronize admin-edited ENTRY history.
            // Empty entry input does not modify existing history.
            if ($entryTime !== null && $entryStatus !== null) {
                $this->upsertAdminEditedScanHistory(
                    $employeeId,
                    $attendanceDate,
                    $entryTime,
                    'entry',
                    $entryStatus
                );
            }

            // Synchronize admin-edited EXIT history.
            // Empty exit input does not modify existing history.
            if ($exitTime !== null && $exitStatus !== null) {
                $this->upsertAdminEditedScanHistory(
                    $employeeId,
                    $attendanceDate,
                    $exitTime,
                    'exit',
                    $exitStatus
                );
            }

            $this->db->commit();


            return $attendanceId;

        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


}
