<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Model.php';

class RfidCard extends Model
{
    /**
     * Find a card by its RFID identifier.
     */
    public function findByIdentifier(string $identifier): ?array
    {
        $sql = '
            SELECT id, employee_id, rfid_identifier, created_at
            FROM rfid_cards
            WHERE rfid_identifier = :identifier
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':identifier' => $identifier,
        ]);

        $card = $stmt->fetch();

        return $card ?: null;
    }

    /**
     * Find whether an employee already has an RFID card.
     */
    public function findByEmployeeId(int $employeeId): ?array
    {
        $sql = '
            SELECT id, employee_id, rfid_identifier, created_at
            FROM rfid_cards
            WHERE employee_id = :employee_id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
        ]);

        $card = $stmt->fetch();

        return $card ?: null;
    }

    /**
     * Store an RFID card assignment.
     */
    public function create(int $employeeId, string $identifier): int
    {
        $sql = '
            INSERT INTO rfid_cards (employee_id, rfid_identifier)
            VALUES (:employee_id, :identifier)
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':identifier' => $identifier,
        ]);

        return (int) $this->db->lastInsertId();
    }


    
    /**
     * Get all registered RFID cards with employee details.
     */
    public function getAllWithEmployeeDetails(): array
    {
        $sql = '
        SELECT
            rc.id,
            rc.employee_id,
            rc.rfid_identifier,
            rc.created_at,
            e.employee_code,
            u.username,
            u.full_name,
            u.email,
            u.status
        FROM rfid_cards rc
        INNER JOIN employees e ON e.id = rc.employee_id
        INNER JOIN users u ON u.id = e.user_id
        ORDER BY e.employee_code ASC
    ';

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * Find an RFID assignment by its database ID.
     */
    public function findById(int $id): ?array
    {
        $sql = '
        SELECT id, employee_id, rfid_identifier, created_at
        FROM rfid_cards
        WHERE id = :id
        LIMIT 1
    ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        $card = $stmt->fetch();

        return $card ?: null;
    }

    /**
     * Remove an RFID assignment.
     */
    public function delete(int $id): bool
    {
        $sql = 'DELETE FROM rfid_cards WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}