<?php

use PHPUnit\Framework\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function testConnectionUsesTestDatabase(): void
    {
        // Setup
        $database = new Database();

        // Call
        $pdo = $database->connect();
        $result = $pdo->query('SELECT DATABASE()')->fetchColumn();

        // Assertion
        $this->assertSame(
            'hris_management_system_test',
            $result
        );
    }
}
