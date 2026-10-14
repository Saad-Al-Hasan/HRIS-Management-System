<?php

require_once __DIR__ . '/../core/Model.php';

class Admin extends Model
{
    /**
     * Get all administrator accounts.
     */
    public function getAll(): array
    {
        $sql = "
            SELECT
                id,
                username,
                email,
                full_name,
                status,
                created_at,
                updated_at
            FROM users
            WHERE role_id = 1
            ORDER BY id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Find an administrator by ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                id,
                username,
                email,
                full_name,
                status,
                created_at,
                updated_at
            FROM users
            WHERE id = :id
              AND role_id = 1
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        $admin = $stmt->fetch();

        return $admin ?: null;
    }

    /**
     * Find an administrator by username.
     */
    public function findByUsername(string $username): ?array
    {
        $sql = "
            SELECT
                id,
                username,
                email,
                full_name,
                status
            FROM users
            WHERE username = :username
              AND role_id = 1
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':username' => $username
        ]);

        $admin = $stmt->fetch();

        return $admin ?: null;
    }

    /**
     * Find an administrator by email.
     */
    public function findByEmail(string $email): ?array
    {
        $sql = "
            SELECT
                id,
                username,
                email,
                full_name,
                status
            FROM users
            WHERE email = :email
              AND role_id = 1
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':email' => $email
        ]);

        $admin = $stmt->fetch();

        return $admin ?: null;
    }

    /**
     * Create a new administrator account.
     */
    public function create(
        string $username,
        string $email,
        string $fullName,
        string $password,
        string $status = 'active'
    ): bool {
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $sql = "
            INSERT INTO users (
                role_id,
                username,
                email,
                password,
                full_name,
                status
            )
            VALUES (
                1,
                :username,
                :email,
                :password,
                :full_name,
                :status
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $passwordHash,
            ':full_name' => $fullName,
            ':status' => $status
        ]);
    }

    /**
     * Update an administrator account.
     */
    public function update(
        int $id,
        string $username,
        string $email,
        string $fullName,
        string $status
    ): bool {
        $sql = "
            UPDATE users
            SET
                username = :username,
                email = :email,
                full_name = :full_name,
                status = :status
            WHERE id = :id
              AND role_id = 1
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':username' => $username,
            ':email' => $email,
            ':full_name' => $fullName,
            ':status' => $status
        ]);
    }

    /**
     * Update administrator password.
     */
    public function updatePassword(
        int $id,
        string $password
    ): bool {
        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $sql = "
            UPDATE users
            SET password = :password
            WHERE id = :id
              AND role_id = 1
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':id' => $id,
            ':password' => $passwordHash
        ]);
    }
}