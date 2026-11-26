<?php

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/AuditLog.php';

class Holiday extends Model
{
    /**
     * Retrieve all holidays or filter by an exact holiday date.
     */
    public function getAll(?string $holidayDate = null): array
    {
        $sql = "
        SELECT
            id,
            holiday_name,
            holiday_date,
            holiday_type,
            description,
            created_at
        FROM holidays
    ";

        if ($holidayDate !== null) {
            $sql .= " WHERE holiday_date = :holiday_date";
        }

        $sql .= " ORDER BY holiday_date DESC, id DESC";

        $stmt = $this->db->prepare($sql);

        if ($holidayDate !== null) {
            $stmt->execute([
                ':holiday_date' => $holidayDate
            ]);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll();
    }

    /**
     * Check whether a holiday already exists on a date.
     */
    public function existsOnDate(string $holidayDate): bool
    {
        $sql = "
            SELECT id
            FROM holidays
            WHERE holiday_date = :holiday_date
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':holiday_date' => $holidayDate
        ]);

        return (bool) $stmt->fetch();
    }

    /**
     * Create a new holiday.
     */
    public function create(
        string $holidayName,
        string $holidayDate,
        string $holidayType,
        ?string $description = null
    ): bool {
        try {
            $this->db->beginTransaction();

            $sql = "
            INSERT INTO holidays (
                holiday_name,
                holiday_date,
                holiday_type,
                description
            )
            VALUES (
                :holiday_name,
                :holiday_date,
                :holiday_type,
                :description
            )
        ";

            $stmt = $this->db->prepare($sql);

            $created = $stmt->execute([
                ':holiday_name' => $holidayName,
                ':holiday_date' => $holidayDate,
                ':holiday_type' => $holidayType,
                ':description' => $description
            ]);

            if (!$created) {
                $this->db->rollBack();
                return false;
            }

            // Record successful holiday creation.
            $auditLog = new AuditLog($this->db);

            $auditLog->create(
                'Holiday ' . $holidayName
                . ' (' . $holidayDate . ', ' . $holidayType . ') created',
                Auth::userId()
            );

            $this->db->commit();

            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Delete a holiday by ID.
     */
    public function delete(int $id): bool
    {
        try {
            $this->db->beginTransaction();

            // Retrieve holiday details before deletion.
            $stmt = $this->db->prepare("
            SELECT holiday_name, holiday_date, holiday_type
            FROM holidays
            WHERE id = :id
            LIMIT 1
        ");

            $stmt->execute([
                ':id' => $id
            ]);

            $holiday = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$holiday) {
                $this->db->commit();
                return false;
            }

            // Delete the selected holiday.
            $stmt = $this->db->prepare("
            DELETE FROM holidays
            WHERE id = :id
        ");

            $stmt->execute([
                ':id' => $id
            ]);

            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                return false;
            }

            // Record successful holiday deletion.
            $auditLog = new AuditLog($this->db);

            $auditLog->create(
                'Holiday ' . $holiday['holiday_name']
                . ' (' . $holiday['holiday_date']
                . ', ' . $holiday['holiday_type'] . ') deleted',
                Auth::userId()
            );

            $this->db->commit();

            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
}
