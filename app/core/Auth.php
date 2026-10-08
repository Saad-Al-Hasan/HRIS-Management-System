<?php

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(array $user): void
    {
        self::startSession();

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
    }

    public static function logout(): void
    {
        self::startSession();

        $_SESSION = [];

        session_destroy();
    }

    public static function check(): bool
    {
        self::startSession();

        return isset($_SESSION['user_id']);
    }

    public static function userId(): ?int
    {
        self::startSession();

        return $_SESSION['user_id'] ?? null;
    }

    public static function roleId(): ?int
    {
        self::startSession();

        return $_SESSION['role_id'] ?? null;
    }
}