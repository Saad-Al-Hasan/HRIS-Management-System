<?php

require_once __DIR__ . '/../core/Model.php';

class AuditLog extends Model
{
    /**
     * Accept an existing PDO connection when needed.
     *
     * This allows audit records to use the same transaction
     * as the operation being audited.
     */
    public function __construct(?PDO $connection = null)
    {
        if ($connection !== null) {
            $this->db = $connection;
        } else {
            parent::__construct();
        }
    }

    /**
     * Create a new audit record.
     *
     * The timestamp is generated automatically by MySQL.
     */
    public function create(string $activity, ?int $userId = null): bool
    {
        $activity = trim($activity);

        if ($activity === '') {
            throw new InvalidArgumentException(
                'Audit activity cannot be empty.'
            );
        }

        if (strlen($activity) > 255) {
            throw new InvalidArgumentException(
                'Audit activity cannot exceed 255 characters.'
            );
        }

        $sql = "
            INSERT INTO audit_logs (user_id, activity)
            VALUES (:user_id, :activity)
        ";

        $stmt = $this->db->prepare($sql);

        if ($userId === null) {
            $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        }

        $stmt->bindValue(':activity', $activity, PDO::PARAM_STR);

        return $stmt->execute();
    }

    /**
     * Retrieve audit records with associated user information.
     */
    public function getAll(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $sql = "
            SELECT
                al.id,
                al.user_id,
                al.activity,
                al.activity_timestamp,
                COALESCE(
                    NULLIF(u.full_name, ''),
                    NULLIF(u.username, ''),
                    'Deleted / Unavailable User'
                ) AS actor_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.activity_timestamp DESC, al.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    /**
     * Apply a validated module filter to an audit query.
     *
     * The classification order matches the Audit Logs view.
     */
    private function applyModuleFilter(
        array &$conditions,
        array &$params,
        array $filters
    ): void {
        $module = trim((string) ($filters['module'] ?? ''));

        if ($module === '') {
            return;
        }

        $allowedModules = [
            'authentication',
            'employee_management',
            'rfid_management',
            'attendance_management',
            'work_schedules',
            'salary_management',
            'leave_management',
            'holiday_management'
        ];

        if (!in_array($module, $allowedModules, true)) {
            return;
        }

        $moduleCase = "
            CASE
                WHEN LOWER(al.activity) LIKE '%logged in%'
                  OR LOWER(al.activity) LIKE '%logged out%'
                THEN 'authentication'

                WHEN LOWER(al.activity) LIKE '%rfid%'
                 AND (
                    LOWER(al.activity) LIKE '%card%'
                    OR LOWER(al.activity) LIKE '%assigned%'
                    OR LOWER(al.activity) LIKE '%unassigned%'
                 )
                THEN 'rfid_management'

                WHEN LOWER(al.activity) LIKE '%attendance%'
                  OR LOWER(al.activity) LIKE '%rfid entry recorded%'
                  OR LOWER(al.activity) LIKE '%rfid exit recorded%'
                THEN 'attendance_management'

                WHEN LOWER(al.activity) LIKE '%work schedule%'
                THEN 'work_schedules'

                WHEN LOWER(al.activity) LIKE '%salary%'
                  OR LOWER(al.activity) LIKE '%payroll%'
                THEN 'salary_management'

                WHEN LOWER(al.activity) LIKE '%leave%'
                THEN 'leave_management'

                WHEN LOWER(al.activity) LIKE '%holiday%'
                THEN 'holiday_management'

                WHEN LOWER(al.activity) LIKE '%employee%'
                 AND (
                    LOWER(al.activity) LIKE '%created%'
                    OR LOWER(al.activity) LIKE '%profile updated%'
                    OR LOWER(al.activity) LIKE '%activated%'
                    OR LOWER(al.activity) LIKE '%deactivated%'
                 )
                THEN 'employee_management'

                ELSE 'other'
            END
        ";

        $conditions[] = "($moduleCase) = :module";
        $params[':module'] = $module;
    }




    /**
     * Retrieve audit records using optional filters.
     *
     * Supported filters:
     * - from_date
     * - to_date
     * - user_id
     * - search
     */
    public function getFiltered(
        array $filters = [],
        int $limit = 50,
        int $offset = 0
    ): array {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $conditions = [];
        $params = [];

        if (!empty($filters['from_date'])) {
            $conditions[] = 'DATE(al.activity_timestamp) >= :from_date';
            $params[':from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $conditions[] = 'DATE(al.activity_timestamp) <= :to_date';
            $params[':to_date'] = $filters['to_date'];
        }

        if (!empty($filters['user_id'])) {
            $conditions[] = 'al.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }

        $this->applyModuleFilter($conditions, $params, $filters);

        $whereClause = '';

        if (!empty($conditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "
            SELECT
                al.id,
                al.user_id,
                al.activity,
                al.activity_timestamp,
                COALESCE(
                    NULLIF(u.full_name, ''),
                    NULLIF(u.username, ''),
                    'Deleted / Unavailable User'
                ) AS actor_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            $whereClause
            ORDER BY al.activity_timestamp DESC, al.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $type = $key === ':user_id'
                ? PDO::PARAM_INT
                : PDO::PARAM_STR;

            $stmt->bindValue($key, $value, $type);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



    /**
     * Count audit records matching the selected filters.
     * Used to calculate pagination.
     */
    public function countFiltered(array $filters = []): int
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['from_date'])) {
            $conditions[] = 'DATE(al.activity_timestamp) >= :from_date';
            $params[':from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $conditions[] = 'DATE(al.activity_timestamp) <= :to_date';
            $params[':to_date'] = $filters['to_date'];
        }

        if (!empty($filters['user_id'])) {
            $conditions[] = 'al.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }

        $this->applyModuleFilter($conditions, $params, $filters);

        $whereClause = '';

        if (!empty($conditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "
            SELECT COUNT(*)
            FROM audit_logs al
            $whereClause
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $type = $key === ':user_id'
                ? PDO::PARAM_INT
                : PDO::PARAM_STR;

            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }


    /**
     * Retrieve users who have generated audit records.
     * Used to populate the actor filter dropdown.
     */

    public function getUsersForFilter(): array
    {
        $sql = "
        SELECT DISTINCT
            u.id,
            u.username,
            u.role_id,
            e.employee_code,
            COALESCE(
                NULLIF(u.full_name, ''),
                NULLIF(u.username, ''),
                CONCAT('User #', u.id)
            ) AS actor_name
        FROM audit_logs al
        INNER JOIN users u
            ON al.user_id = u.id
        LEFT JOIN employees e
            ON e.user_id = u.id
        WHERE al.user_id IS NOT NULL
        ORDER BY
            e.employee_code ASC,
            u.username ASC
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }




    /**
     * Get module-wise audit activity counts using the active filters.
     *
     * KPI totals are calculated across all matching records,
     * not just the current pagination page.
     */
    public function getCategorySummary(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['from_date'])) {
            $conditions[] = 'DATE(al.activity_timestamp) >= :from_date';
            $params[':from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $conditions[] = 'DATE(al.activity_timestamp) <= :to_date';
            $params[':to_date'] = $filters['to_date'];
        }

        if (!empty($filters['user_id'])) {
            $conditions[] = 'al.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }

        $this->applyModuleFilter($conditions, $params, $filters);

        $whereClause = $conditions
            ? 'WHERE ' . implode(' AND ', $conditions)
            : '';

        $sql = "
        SELECT
            COUNT(*) AS total,

            SUM(CASE
                WHEN LOWER(al.activity) LIKE '%logged in%'
                  OR LOWER(al.activity) LIKE '%logged out%'
                THEN 1 ELSE 0
            END) AS authentication,

            SUM(CASE
                WHEN LOWER(al.activity) LIKE '%employee%'
                 AND (
                    LOWER(al.activity) LIKE '%created%'
                    OR LOWER(al.activity) LIKE '%profile updated%'
                    OR LOWER(al.activity) LIKE '%activated%'
                    OR LOWER(al.activity) LIKE '%deactivated%'
                 )
                THEN 1 ELSE 0
            END) AS employee_management,

            SUM(CASE
                WHEN LOWER(al.activity) LIKE '%rfid%'
                 AND (
                    LOWER(al.activity) LIKE '%card%'
                    OR LOWER(al.activity) LIKE '%assigned%'
                    OR LOWER(al.activity) LIKE '%unassigned%'
                 )
                THEN 1 ELSE 0
            END) AS rfid_management,

            SUM(CASE
                WHEN LOWER(al.activity) LIKE '%attendance%'
                  OR LOWER(al.activity) LIKE '%rfid entry recorded%'
                  OR LOWER(al.activity) LIKE '%rfid exit recorded%'
                THEN 1 ELSE 0
            END) AS attendance_management,

            SUM(CASE
                WHEN LOWER(al.activity) LIKE '%work schedule%'
                THEN 1 ELSE 0
            END) AS work_schedules,

            SUM(CASE
                WHEN LOWER(al.activity) LIKE '%salary%'
                  OR LOWER(al.activity) LIKE '%payroll%'
                THEN 1 ELSE 0
            END) AS salary_management,

            SUM(CASE
                WHEN LOWER(al.activity) LIKE '%leave%'
                THEN 1 ELSE 0
            END) AS leave_management,

            SUM(CASE
                WHEN LOWER(al.activity) LIKE '%holiday%'
                THEN 1 ELSE 0
            END) AS holiday_management

        FROM audit_logs al
        $whereClause
    ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $type = $key === ':user_id'
                ? PDO::PARAM_INT
                : PDO::PARAM_STR;

            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($result['total'] ?? 0),
            'authentication' => (int) ($result['authentication'] ?? 0),
            'employee_management' => (int) ($result['employee_management'] ?? 0),
            'rfid_management' => (int) ($result['rfid_management'] ?? 0),
            'attendance_management' => (int) ($result['attendance_management'] ?? 0),
            'work_schedules' => (int) ($result['work_schedules'] ?? 0),
            'salary_management' => (int) ($result['salary_management'] ?? 0),
            'leave_management' => (int) ($result['leave_management'] ?? 0),
            'holiday_management' => (int) ($result['holiday_management'] ?? 0)
        ];
    }
}