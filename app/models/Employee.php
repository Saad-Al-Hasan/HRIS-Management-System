<?php

require_once __DIR__ . '/../core/Model.php';

class Employee extends Model
{
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
