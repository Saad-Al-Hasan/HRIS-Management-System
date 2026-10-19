<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Model.php';

class RfidCard extends Model
{
    public function findByIdentifier(string $identifier): ?array
    {
        $sql = '
            SELECT id, employee_id, user_id, rfid_identifier, created_at
            FROM rfid_cards
            WHERE rfid_identifier = :identifier
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':identifier' => $identifier]);

        $card = $stmt->fetch();

        return $card ?: null;
    }

    public function findByEmployeeId(int $employeeId): ?array
    {
        $sql = '
            SELECT id, employee_id, user_id, rfid_identifier, created_at
            FROM rfid_cards
            WHERE employee_id = :employee_id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':employee_id' => $employeeId]);

        $card = $stmt->fetch();

        return $card ?: null;
    }

    public function findByUserId(int $userId): ?array
    {
        $sql = '
            SELECT id, employee_id, user_id, rfid_identifier, created_at
            FROM rfid_cards
            WHERE user_id = :user_id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        $card = $stmt->fetch();

        return $card ?: null;
    }

    public function createForEmployee(int $employeeId, string $identifier): int
    {
        $sql = '
            INSERT INTO rfid_cards (employee_id, user_id, rfid_identifier)
            VALUES (:employee_id, NULL, :identifier)
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':identifier' => $identifier,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createForUser(int $userId, string $identifier): int
    {
        $sql = '
            INSERT INTO rfid_cards (employee_id, user_id, rfid_identifier)
            VALUES (NULL, :user_id, :identifier)
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':identifier' => $identifier,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getAllWithEmployeeDetails(): array
    {
        $sql = '
            SELECT
                rc.id,
                rc.employee_id,
                rc.user_id,
                rc.rfid_identifier,
                rc.created_at,
                e.employee_code,
                u.username,
                u.full_name,
                u.email,
                u.status,
                CASE
                    WHEN rc.user_id IS NOT NULL THEN "Administrator"
                    ELSE "Employee"
                END AS account_type
            FROM rfid_cards rc
            LEFT JOIN employees e ON e.id = rc.employee_id
            LEFT JOIN users u ON u.id = COALESCE(rc.user_id, e.user_id)
            ORDER BY account_type, u.full_name
        ';

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $sql = '
            SELECT id, employee_id, user_id, rfid_identifier, created_at
            FROM rfid_cards
            WHERE id = :id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        $card = $stmt->fetch();

        return $card ?: null;
    }

    public function delete(int $id): bool
    {
        $sql = 'DELETE FROM rfid_cards WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
