<?php

require_once __DIR__ . '/../core/Model.php';

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

        return $stmt->execute([
            ':holiday_name' => $holidayName,
            ':holiday_date' => $holidayDate,
            ':holiday_type' => $holidayType,
            ':description' => $description
        ]);
    }

    /**
     * Delete a holiday by ID.
     */
    public function delete(int $id): bool
    {
        $sql = "
            DELETE FROM holidays
            WHERE id = :id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $id
        ]);
    }
}
