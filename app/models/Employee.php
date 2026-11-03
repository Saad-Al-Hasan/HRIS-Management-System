<?php

require_once __DIR__ . '/../core/Model.php';

class Employee extends Model
{
    public function usernameExists(string $username): bool
    {
        $sql = "SELECT id FROM users WHERE username = :username LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':username' => $username]);

        return (bool) $stmt->fetch();
    }

    public function emailExists(string $email): bool
    {
        $sql = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);

        return (bool) $stmt->fetch();
    }

    public function create(array $userData, array $employeeData): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "
            INSERT INTO users (
                role_id,
                username,
                email,
                full_name,
                password,
                status
            ) VALUES (
                2,
                :username,
                :email,
                :full_name,
                :password,
                :status
            )
        ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':username' => $userData['username'],
                ':email' => $userData['email'],
                ':full_name' => $userData['full_name'],
                ':password' => password_hash(
                    $userData['password'],
                    PASSWORD_DEFAULT
                ),
                ':status' => $userData['status'] ?? 'active',
            ]);

            $userId = (int) $this->db->lastInsertId();

            $sql = "
            INSERT INTO employees (
                user_id,
                employee_code,
                phone,
                address,
                department,
                designation,
                joining_date
            ) VALUES (
                :user_id,
                :employee_code,
                :phone,
                :address,
                :department,
                :designation,
                :joining_date
            )
        ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':employee_code' => $employeeData['employee_code'],
                ':phone' => $employeeData['phone'] ?: null,
                ':address' => $employeeData['address'] ?: null,
                ':department' => $employeeData['department'],
                ':designation' => $employeeData['designation'],
                ':joining_date' => $employeeData['joining_date'],
            ]);
            $employeeId = (int) $this->db->lastInsertId();

            // Automatically assign the default 9 AM - 6 PM shift.
            $defaultScheduleSql = "
    SELECT id
    FROM work_schedules
    WHERE name = :default_name
    LIMIT 1
";

            $defaultScheduleStmt = $this->db->prepare($defaultScheduleSql);
            $defaultScheduleStmt->execute([
                ':default_name' => '9 AM - 6 PM',
            ]);

            $defaultScheduleId = $defaultScheduleStmt->fetchColumn();

            if ($defaultScheduleId === false) {
                throw new RuntimeException(
                    'The default 9 AM - 6 PM work schedule was not found.'
                );
            }

            $assignmentSql = "
    INSERT INTO employee_schedules (
        employee_id,
        schedule_id
    ) VALUES (
        :employee_id,
        :schedule_id
    )
";

            $assignmentStmt = $this->db->prepare($assignmentSql);
            $assignmentStmt->execute([
                ':employee_id' => $employeeId,
                ':schedule_id' => (int) $defaultScheduleId,
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

    public function employeeCodeExists(string $employeeCode): bool
    {
        $sql = "
        SELECT id
        FROM employees
        WHERE employee_code = :employee_code
        LIMIT 1
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_code' => $employeeCode
        ]);

        return (bool) $stmt->fetch();
    }

    public function findByEmployeeId(int $employeeId): ?array
    {
        $sql = "
        SELECT
            users.id AS user_id,
            users.username,
            users.email,
            users.full_name,
            users.status,
            employees.id AS employee_id,
            employees.employee_code,
            employees.phone,
            employees.address,
            employees.department,
            employees.designation,
            employees.joining_date,
            employees.created_at,
            employees.updated_at
        FROM users
        INNER JOIN employees
            ON employees.user_id = users.id
        WHERE employees.id = :employee_id
          AND users.role_id = 2
        LIMIT 1
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId
        ]);

        $employee = $stmt->fetch();

        return $employee ?: null;
    }

    public function update(int $userId, array $userData, array $employeeData): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "
            UPDATE users
            SET
                username = :username,
                email = :email,
                full_name = :full_name
            WHERE id = :user_id
              AND role_id = 2
        ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':username' => $userData['username'],
                ':email' => $userData['email'],
                ':full_name' => $userData['full_name'],
                ':user_id' => $userId,
            ]);

            $sql = "
            UPDATE employees
            SET
                employee_code = :employee_code,
                phone = :phone,
                address = :address,
                department = :department,
                designation = :designation,
                joining_date = :joining_date
            WHERE user_id = :user_id
        ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':employee_code' => $employeeData['employee_code'],
                ':phone' => $employeeData['phone'] ?: null,
                ':address' => $employeeData['address'] ?: null,
                ':department' => $employeeData['department'],
                ':designation' => $employeeData['designation'],
                ':joining_date' => $employeeData['joining_date'],
                ':user_id' => $userId,
            ]);

            if (!empty($userData['password'])) {
                $sql = "
        UPDATE users
        SET password = :password
        WHERE id = :user_id
          AND role_id = 2
    ";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':password' => password_hash(
                        $userData['password'],
                        PASSWORD_DEFAULT
                    ),
                    ':user_id' => $userId,
                ]);
            }

            $this->db->commit();

            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function updateStatus(int $employeeId, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return false;
        }

        $sql = "
        UPDATE users
        SET status = :status
        WHERE id = (
            SELECT user_id
            FROM employees
            WHERE id = :employee_id
        )
        AND role_id = 2
    ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':status' => $status,
            ':employee_id' => $employeeId,
        ]);
    }

    public function getNextEmployeeCode(): string
    {
        $sql = "
        SELECT MAX(
            CAST(SUBSTRING(employee_code, 5) AS UNSIGNED)
        ) AS last_number
        FROM employees
        WHERE employee_code REGEXP '^EMP-[0-9]+$'
    ";

        $stmt = $this->db->query($sql);
        $result = $stmt->fetch();

        $nextNumber = ((int) ($result['last_number'] ?? 0)) + 1;

        return 'EMP-' . str_pad(
            (string) $nextNumber,
            4,
            '0',
            STR_PAD_LEFT
        );
    }

    public function getAll(): array
    {
        $sql = "
        SELECT
            users.id AS user_id,
            users.username,
            users.email,
            users.full_name,
            users.status,
            employees.id AS employee_id,
            employees.employee_code,
            employees.phone,
            employees.address,
            employees.department,
            employees.designation,
            employees.joining_date
        FROM users
        INNER JOIN employees
            ON employees.user_id = users.id
        WHERE users.role_id = 2
        ORDER BY employees.employee_code ASC
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }


    public function getFiltered(array $filters = [], string $search = ''): array
    {
        $sql = "
        SELECT
            users.id AS user_id,
            users.username,
            users.email,
            users.full_name,
            users.status,
            employees.id AS employee_id,
            employees.employee_code,
            employees.phone,
            employees.address,
            employees.department,
            employees.designation,
            employees.joining_date
        FROM users
        INNER JOIN employees
            ON employees.user_id = users.id
        WHERE users.role_id = 2
    ";

        $params = [];

        $search = trim($search);

        if ($search !== '') {
            $sql .= " AND (
        employees.employee_code LIKE :search_code
        OR users.username LIKE :search_username
    )";

            $params[':search_code'] = '%' . $search . '%';
            $params[':search_username'] = '%' . $search . '%';
        }

        if (!empty($filters['department'])) {
            $sql .= " AND employees.department = :department";
            $params[':department'] = $filters['department'];
        }

        if (!empty($filters['designation'])) {
            $sql .= " AND employees.designation = :designation";
            $params[':designation'] = $filters['designation'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND users.status = :status";
            $params[':status'] = $filters['status'];
        }

        $sql .= " ORDER BY employees.employee_code ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }


    public function getDepartments(): array
    {
        $sql = "
        SELECT DISTINCT department
        FROM employees
        WHERE department IS NOT NULL
          AND department <> ''
        ORDER BY department ASC
    ";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
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

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }



    public function findByUserId(int $userId): ?array
    {
        $sql = "
            SELECT
                users.id AS user_id,
                users.username,
                users.email,
                users.full_name,
                users.status,
                employees.id AS employee_id,
                employees.employee_code,
                employees.phone,
                employees.address,
                employees.department,
                employees.designation,
                employees.joining_date,
                employees.created_at,
                employees.updated_at
            FROM users
            INNER JOIN employees
                ON employees.user_id = users.id
            WHERE users.id = :user_id
              AND users.role_id = 2
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':user_id' => $userId
        ]);

        $employee = $stmt->fetch();

        return $employee ?: null;
    }
}
