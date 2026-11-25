<?php

require_once __DIR__ . '/../core/Model.php';

class Report extends Model
{
    public function getDepartments(): array
    {
        $sql = "
            SELECT DISTINCT department
            FROM employees
            WHERE department IS NOT NULL
              AND department <> ''
            ORDER BY department ASC
        ";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getDesignations(): array
    {
        $sql = "
            SELECT DISTINCT designation
            FROM employees
            WHERE designation IS NOT NULL
              AND designation <> ''
            ORDER BY designation ASC
        ";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Return all employee options for the Attendance Report.
     * Department filtering is handled instantly in the browser.
     */
    public function getEmployeeOptions(): array
    {
        $sql = "
            SELECT
                e.id,
                e.employee_code,
                e.department,
                u.full_name
            FROM employees e
            INNER JOIN users u ON u.id = e.user_id
            WHERE u.role_id = 2
            ORDER BY u.full_name ASC
        ";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Attendance report.
     * Admin-edited attendance values take priority.
     */
    public function getAttendanceReport(array $filters = []): array
    {
        $sql = "
            SELECT
                e.employee_code AS employee_id,
                u.full_name,
                e.department,
                a.attendance_date,

                COALESCE(a.A_E_entry_time, a.entry_time)
                    AS entry_time,

                COALESCE(a.A_E_entry_status, a.entry_status)
                    AS entry_status,

                COALESCE(a.A_E_exit_time, a.exit_time)
                    AS exit_time,

                COALESCE(a.A_E_exit_status, a.exit_status)
                    AS exit_status,

                COALESCE(a.A_E_status, a.status)
                    AS attendance_status

            FROM attendance a

            INNER JOIN employees e
                ON e.id = a.employee_id

            INNER JOIN users u
                ON u.id = e.user_id

            WHERE u.role_id = 2
        ";

        $params = [];

        if (!empty($filters['department'])) {
            $sql .= " AND e.department = :department";
            $params[':department'] = $filters['department'];
        }

        if (!empty($filters['employee'])) {
            $sql .= " AND e.id = :employee";
            $params[':employee'] = (int) $filters['employee'];
        }

        if (!empty($filters['from_date'])) {
            $sql .= " AND a.attendance_date >= :from_date";
            $params[':from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $sql .= " AND a.attendance_date <= :to_date";
            $params[':to_date'] = $filters['to_date'];
        }

        if (!empty($filters['status'])) {
            $sql .= "
                AND COALESCE(a.A_E_status, a.status) = :status
            ";

            $params[':status'] = $filters['status'];
        }

        $sql .= "
            ORDER BY
                a.attendance_date DESC,
                u.full_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Employee report with separate Name and Employee Code filters.
     */
    public function getEmployeeReport(array $filters = []): array
    {
        $sql = "
            SELECT
                e.employee_code,
                u.full_name,
                u.username,
                e.department,
                e.designation,
                e.phone,
                u.email,
                e.joining_date,
                u.status

            FROM employees e

            INNER JOIN users u
                ON u.id = e.user_id

            WHERE u.role_id = 2
        ";

        $params = [];

        if (!empty($filters['department'])) {
            $sql .= " AND e.department = :department";
            $params[':department'] = $filters['department'];
        }

        if (!empty($filters['designation'])) {
            $sql .= " AND e.designation = :designation";
            $params[':designation'] = $filters['designation'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND u.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['name'])) {
            $sql .= " AND u.full_name LIKE :name";
            $params[':name'] = '%' . $filters['name'] . '%';
        }

        if (!empty($filters['employee_code'])) {
            $sql .= " AND e.employee_code LIKE :employee_code";
            $params[':employee_code'] = '%' . $filters['employee_code'] . '%';
        }

        $sql .= " ORDER BY u.full_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}