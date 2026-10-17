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
}