<?php

require_once __DIR__ . '/../core/Model.php';

class User extends Model
{
    public function findByUsername(string $username): ?array
    {
        $sql = "
            SELECT
                users.*,
                roles.name AS role_name
            FROM users
            INNER JOIN roles
                ON users.role_id = roles.id
            WHERE users.username = :username
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':username' => $username
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                users.*,
                roles.name AS role_name
            FROM users
            INNER JOIN roles
                ON users.role_id = roles.id
            WHERE users.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }
}