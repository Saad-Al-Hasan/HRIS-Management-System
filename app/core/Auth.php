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

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role_id'] = (int) $user['role_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role_name'] = $user['role_name'];
    }

    public static function logout(): void
    {
        self::startSession();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

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

        return isset($_SESSION['user_id'])
            ? (int) $_SESSION['user_id']
            : null;
    }

    public static function roleId(): ?int
    {
        self::startSession();

        return isset($_SESSION['role_id'])
            ? (int) $_SESSION['role_id']
            : null;
    }

    public static function roleName(): ?string
    {
        self::startSession();

        return $_SESSION['role_name'] ?? null;
    }

    public static function username(): ?string
    {
        self::startSession();

        return $_SESSION['username'] ?? null;
    }

    public static function fullName(): ?string
    {
        self::startSession();

        return $_SESSION['full_name'] ?? null;
    }

    public static function csrfToken(): string
    {
        self::startSession();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrfToken(string $token): bool
    {
        self::startSession();

        return isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}