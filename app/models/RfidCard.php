<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/AuditLog.php';

class RfidCard extends Model
{
    /**
     * Find a card by its RFID identifier.
     */

    public function findByIdentifier(string $identifier): ?array
    {
        $sql = '
        SELECT
            rc.id,
            rc.employee_id,
            rc.rfid_identifier,
            rc.created_at,
            e.employee_code,
            e.user_id,
            u.full_name,
            u.username
        FROM rfid_cards rc
        INNER JOIN employees e
            ON e.id = rc.employee_id
        INNER JOIN users u
            ON u.id = e.user_id
        WHERE rc.rfid_identifier = :identifier
        LIMIT 1
    ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':identifier' => $identifier,
        ]);

        $card = $stmt->fetch();

        if (!$card) {
            return null;
        }

        // Provide the keys expected by the attendance result card.
        $card['identifier'] = $card['rfid_identifier'];

        return $card;
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
     * Store an RFID card assignment and record the activity.
     */
    public function create(int $employeeId, string $identifier): int
    {
        $this->db->beginTransaction();

        try {
            // Get the employee code for the audit record.
            $employeeStmt = $this->db->prepare(
                'SELECT employee_code FROM employees WHERE id = :employee_id'
            );

            $employeeStmt->execute([
                ':employee_id' => $employeeId
            ]);

            $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);

            if (!$employee) {
                throw new RuntimeException('Employee not found.');
            }

            // Register the RFID card.
            $sql = '
            INSERT INTO rfid_cards (employee_id, rfid_identifier)
            VALUES (:employee_id, :identifier)
        ';

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':employee_id' => $employeeId,
                ':identifier' => $identifier,
            ]);

            $cardId = (int) $this->db->lastInsertId();

            // Record the successful registration.
            $auditLog = new AuditLog($this->db);

            $auditLog->create(
                'RFID card assigned to employee ' . $employee['employee_code'],
                Auth::userId()
            );

            $this->db->commit();

            return $cardId;

        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
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
     * Remove an RFID assignment and record the activity.
     */
    public function delete(int $id): bool
    {
        $this->db->beginTransaction();

        try {
            // Retrieve the employee associated with this card.
            $sql = '
            SELECT
                rc.id,
                e.employee_code
            FROM rfid_cards rc
            LEFT JOIN employees e ON e.id = rc.employee_id
            WHERE rc.id = :id
            FOR UPDATE
        ';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);

            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$card) {
                $this->db->commit();
                return false;
            }

            // Remove the RFID assignment.
            $deleteStmt = $this->db->prepare(
                'DELETE FROM rfid_cards WHERE id = :id'
            );

            $deleteStmt->execute([':id' => $id]);

            if ($deleteStmt->rowCount() !== 1) {
                $this->db->commit();
                return false;
            }

            // Record the successful unassignment.
            $employeeCode = $card['employee_code'] ?? 'Unknown Employee';

            $auditLog = new AuditLog($this->db);

            $auditLog->create(
                'RFID card unassigned from employee ' . $employeeCode,
                Auth::userId()
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
     * Get employees who do not currently have an RFID card assigned.
     */
    public function getEmployeesWithoutAssignedCard(): array
    {
        $sql = '
            SELECT
                e.id AS employee_id,
                e.employee_code,
                u.username
            FROM employees e
            INNER JOIN users u
                ON u.id = e.user_id
            LEFT JOIN rfid_cards rc
                ON rc.employee_id = e.id
            WHERE rc.id IS NULL
              AND u.role_id = 2
            ORDER BY e.employee_code ASC
        ';

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

}