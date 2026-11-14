<?php

require_once __DIR__ . '/../core/Model.php';

class Holiday extends Model
{
    public function getAll(): array
    {
        $sql = "SELECT id, holiday_name, holiday_date, holiday_type, description, created_at
                FROM holidays
                ORDER BY holiday_date ASC, id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function create(
        string $name,
        string $date,
        string $type,
        ?string $description = null
    ): int {
        $sql = "INSERT INTO holidays
                (holiday_name, holiday_date, holiday_type, description)
                VALUES
                (:name, :date, :type, :description)";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':name' => $name,
            ':date' => $date,
            ':type' => $type,
            ':description' => $description
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM holidays WHERE id = :id";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        return $stmt->rowCount() > 0;
    }
}
