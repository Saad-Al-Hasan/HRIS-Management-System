<?php

require_once __DIR__ . '/Auth.php';

class Middleware
{
    public static function requireLogin(): void
    {
        if (!Auth::check()) {
            header(
                'Location: /HRIS-Management-System/public/index.php'
            );
            exit;
        }
    }

    public static function requireRole(int $roleId): void
    {
        self::requireLogin();

        if (Auth::roleId() !== $roleId) {
            http_response_code(403);

            die('403 - Access Denied');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireRole(1);
    }

    public static function requireEmployee(): void
    {
        self::requireRole(2);
    }
}