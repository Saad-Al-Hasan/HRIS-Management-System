<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/models/AuditLog.php';

/**
 * Sprint 7 Audit Logs unit tests.
 *
 * All database operations are restricted to hris_management_system_test.
 * Test fixtures are uniquely tagged and only fixture IDs created by this
 * test class are removed during tearDown().
 */
final class AuditLogUnitTest extends TestCase
{
    private PDO $pdo;
    private AuditLog $auditLog;
    private string $tag;
    private array $auditLogIds = [];
    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $this->pdo = $database->connect();

        $this->assertSame(
            'hris_management_system_test',
            $this->pdo->query('SELECT DATABASE()')->fetchColumn(),
            'Audit Log tests must never run against the live database.'
        );

        $this->auditLog = new AuditLog($this->pdo);
        $this->tag = 'AUDUNIT_' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        // Remove only audit rows explicitly created by this test class.
        foreach ($this->auditLogIds as $id) {
            $stmt = $this->pdo->prepare(
                'DELETE FROM audit_logs WHERE id = :id'
            );
            $stmt->execute([':id' => $id]);
        }

        // Remove only test users created by this test class.
        foreach ($this->userIds as $id) {
            $stmt = $this->pdo->prepare(
                'DELETE FROM users WHERE id = :id'
            );
            $stmt->execute([':id' => $id]);
        }

        parent::tearDown();
    }

    private function createTestUser(string $nameSuffix = 'Actor'): int
    {
        $roleId = $this->pdo
            ->query('SELECT id FROM roles ORDER BY id ASC LIMIT 1')
            ->fetchColumn();

        $this->assertNotFalse(
            $roleId,
            'A role must exist in the test database to create a test user.'
        );

        $unique = $this->tag . '_' . $nameSuffix . '_' . count($this->userIds);
        $username = strtolower($unique);
        $email = strtolower($unique) . '@test.local';

        $stmt = $this->pdo->prepare(
            'INSERT INTO users
                (role_id, username, email, password, full_name, status)
             VALUES
                (:role_id, :username, :email, :password, :full_name, :status)'
        );

        $stmt->execute([
            ':role_id' => (int) $roleId,
            ':username' => $username,
            ':email' => $email,
            ':password' => 'unit_test_password_hash',
            ':full_name' => $this->tag . ' ' . $nameSuffix,
            ':status' => 'active'
        ]);

        $userId = (int) $this->pdo->lastInsertId();
        $this->userIds[] = $userId;

        return $userId;
    }

    private function insertAuditRecord(
        string $activity,
        ?int $userId = null,
        string $timestamp = '2025-01-01 12:00:00'
    ): int {
        // Keep the original activity phrase intact for module classification,
        // while adding a unique suffix to isolate this test's fixtures.
        $uniqueActivity = $activity . ' [' . $this->tag . ']';

        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs
                (user_id, activity, activity_timestamp)
             VALUES
                (:user_id, :activity, :activity_timestamp)'
        );

        $stmt->bindValue(
            ':user_id',
            $userId,
            $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
        );
        $stmt->bindValue(':activity', $uniqueActivity, PDO::PARAM_STR);
        $stmt->bindValue(
            ':activity_timestamp',
            $timestamp,
            PDO::PARAM_STR
        );
        $stmt->execute();

        $id = (int) $this->pdo->lastInsertId();
        $this->auditLogIds[] = $id;

        return $id;
    }

    /**
     * Test ID: AUD-01
     * Test Case: Create Audit Record Successfully
     * Test Coverage: Verifies activity text is trimmed, user ID is saved,
     * and the database generates the activity timestamp automatically.
     */
    public function testCreateAuditRecordSuccessfully(): void
    {
        $userId = $this->createTestUser('Create');
        $activity = $this->tag . ' administrator logged in';

        $this->assertTrue(
            $this->auditLog->create('  ' . $activity . '  ', $userId)
        );

        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, activity, activity_timestamp
             FROM audit_logs
             WHERE activity = :activity'
        );
        $stmt->execute([':activity' => $activity]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($record);
        $this->auditLogIds[] = (int) $record['id'];
        $this->assertSame($userId, (int) $record['user_id']);
        $this->assertSame($activity, $record['activity']);
        $this->assertNotEmpty($record['activity_timestamp']);
    }

    /**
     * Test ID: AUD-02
     * Test Case: Reject Empty Audit Activity
     * Test Coverage: Verifies empty and whitespace-only activity text
     * is rejected with InvalidArgumentException.
     */
    public function testCreateRejectsEmptyActivity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Audit activity cannot be empty.');

        $this->auditLog->create('   ');
    }

    /**
     * Test ID: AUD-03
     * Test Case: Reject Activity Exceeding 255 Characters
     * Test Coverage: Verifies activity text longer than 255 characters
     * is rejected before attempting a database insert.
     */
    public function testCreateRejectsActivityLongerThan255Characters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Audit activity cannot exceed 255 characters.'
        );

        $this->auditLog->create(str_repeat('A', 256));
    }

    /**
     * Test ID: AUD-04
     * Test Case: Retrieve Audit Records with Actor and Pagination
     * Test Coverage: Verifies newest-first ordering, actor name retrieval,
     * unavailable-user fallback, and limit/offset pagination.
     */
    public function testGetAllReturnsNewestRecordsWithActorAndPagination(): void
    {
        $userId = $this->createTestUser('GetAll');

        $oldestId = $this->insertAuditRecord(
            'Employee profile updated',
            $userId,
            '2025-02-01 10:00:00'
        );
        $middleId = $this->insertAuditRecord(
            'Administrator logged in',
            $userId,
            '2025-02-01 11:00:00'
        );
        $newestId = $this->insertAuditRecord(
            'Administrator logged out',
            null,
            '2025-02-01 12:00:00'
        );

        $firstPage = $this->auditLog->getAll(2, 0);
        $secondPage = $this->auditLog->getAll(1, 2);

        $this->assertSame($newestId, (int) $firstPage[0]['id']);
        $this->assertSame(
            'Deleted / Unavailable User',
            $firstPage[0]['actor_name']
        );
        $this->assertSame($middleId, (int) $firstPage[1]['id']);
        $this->assertSame(
            $this->tag . ' GetAll',
            $firstPage[1]['actor_name']
        );
        $this->assertSame($oldestId, (int) $secondPage[0]['id']);
    }

    /**
     * Test ID: AUD-05
     * Test Case: Filter Audit Records by Date Range
     * Test Coverage: Verifies from_date and to_date are inclusive and
     * return only records whose activity date is within the selected range.
     */
    public function testGetFilteredAppliesInclusiveDateRange(): void
    {
        $userId = $this->createTestUser('Date');

        $beforeId = $this->insertAuditRecord(
            'Employee profile updated',
            $userId,
            '2025-03-09 23:59:59'
        );
        $insideId = $this->insertAuditRecord(
            'Employee profile updated',
            $userId,
            '2025-03-10 00:00:00'
        );
        $afterId = $this->insertAuditRecord(
            'Employee profile updated',
            $userId,
            '2025-03-11 00:00:00'
        );

        $rows = $this->auditLog->getFiltered([
            'from_date' => '2025-03-10',
            'to_date' => '2025-03-10',
            'user_id' => (string) $userId
        ]);

        $ids = array_map(
            static fn(array $row): int => (int) $row['id'],
            $rows
        );

        $this->assertContains($insideId, $ids);
        $this->assertNotContains($beforeId, $ids);
        $this->assertNotContains($afterId, $ids);
    }

    /**
     * Test ID: AUD-06
     * Test Case: Filter Audit Records by User
     * Test Coverage: Verifies user_id filtering returns records for the
     * selected user and excludes records belonging to another user.
     */
    public function testGetFilteredAppliesUserFilter(): void
    {
        $selectedUserId = $this->createTestUser('Selected');
        $otherUserId = $this->createTestUser('Other');

        $selectedOne = $this->insertAuditRecord(
            'Administrator logged in',
            $selectedUserId,
            '2025-04-01 10:00:00'
        );
        $selectedTwo = $this->insertAuditRecord(
            'Administrator logged out',
            $selectedUserId,
            '2025-04-01 11:00:00'
        );
        $otherRecord = $this->insertAuditRecord(
            'Administrator logged in',
            $otherUserId,
            '2025-04-01 12:00:00'
        );

        $rows = $this->auditLog->getFiltered([
            'user_id' => (string) $selectedUserId,
            'from_date' => '2025-04-01',
            'to_date' => '2025-04-01'
        ]);

        $ids = array_map(
            static fn(array $row): int => (int) $row['id'],
            $rows
        );

        $this->assertContains($selectedOne, $ids);
        $this->assertContains($selectedTwo, $ids);
        $this->assertNotContains($otherRecord, $ids);
        $this->assertCount(2, $rows);
    }

    /**
     * Test ID: AUD-07
     * Test Case: Filter Audit Records by Module
     * Test Coverage: Verifies authentication module filtering and confirms
     * unsupported module values are ignored by the model filter.
     */
    public function testGetFilteredAppliesModuleFilterAndIgnoresUnsupportedModule(): void
    {
        $userId = $this->createTestUser('Module');

        $authId = $this->insertAuditRecord(
            'Administrator logged in',
            $userId,
            '2025-05-01 10:00:00'
        );
        $employeeId = $this->insertAuditRecord(
            'Employee profile updated',
            $userId,
            '2025-05-01 11:00:00'
        );

        $baseFilters = [
            'from_date' => '2025-05-01',
            'to_date' => '2025-05-01',
            'user_id' => (string) $userId
        ];

        $authRows = $this->auditLog->getFiltered(
            $baseFilters + ['module' => 'authentication']
        );

        $this->assertCount(1, $authRows);
        $this->assertSame($authId, (int) $authRows[0]['id']);

        $unsupportedRows = $this->auditLog->getFiltered(
            $baseFilters + ['module' => 'not_a_supported_module']
        );

        $unsupportedIds = array_map(
            static fn(array $row): int => (int) $row['id'],
            $unsupportedRows
        );

        $this->assertCount(2, $unsupportedRows);
        $this->assertContains($authId, $unsupportedIds);
        $this->assertContains($employeeId, $unsupportedIds);
    }

    /**
     * Test ID: AUD-08
     * Test Case: Count Filtered Audit Records
     * Test Coverage: Verifies countFiltered() counts every matching record
     * using date, user, and module filters.
     */
    public function testCountFilteredReturnsMatchingRecordTotal(): void
    {
        $userId = $this->createTestUser('Count');

        $this->insertAuditRecord(
            'Salary processed',
            $userId,
            '2025-06-01 09:00:00'
        );
        $this->insertAuditRecord(
            'Payroll salary calculated',
            $userId,
            '2025-06-01 10:00:00'
        );
        $this->insertAuditRecord(
            'Administrator logged in',
            $userId,
            '2025-06-01 11:00:00'
        );

        $count = $this->auditLog->countFiltered([
            'from_date' => '2025-06-01',
            'to_date' => '2025-06-01',
            'user_id' => (string) $userId,
            'module' => 'salary_management'
        ]);

        $this->assertSame(2, $count);
    }

    /**
     * Test ID: AUD-09
     * Test Case: Retrieve Users for Audit Filter Dropdown
     * Test Coverage: Verifies users with audit activity are returned once,
     * actor_name is populated, and employee_code is available when present.
     */
    public function testGetUsersForFilterReturnsDistinctAuditActors(): void
    {
        $userId = $this->createTestUser('Dropdown');

        $this->insertAuditRecord(
            'Administrator logged in',
            $userId,
            '2025-07-01 09:00:00'
        );
        $this->insertAuditRecord(
            'Administrator logged out',
            $userId,
            '2025-07-01 10:00:00'
        );

        $users = $this->auditLog->getUsersForFilter();

        $matchingUsers = array_values(array_filter(
            $users,
            static fn(array $user): bool => (int) $user['id'] === $userId
        ));

        $this->assertCount(1, $matchingUsers);
        $this->assertSame(
            $this->tag . ' Dropdown',
            $matchingUsers[0]['actor_name']
        );
        $this->assertArrayHasKey('employee_code', $matchingUsers[0]);
        $this->assertNull($matchingUsers[0]['employee_code']);
    }

    /**
     * Test ID: AUD-10
     * Test Case: Calculate Module Activity Summary
     * Test Coverage: Verifies total and module-wise activity counts for
     * the eight supported modules, and summary totals after module filtering.
     */
    public function testGetCategorySummaryCountsModulesAndFilteredSummary(): void
    {
        $userId = $this->createTestUser('Summary');
        $timestamp = '2025-08-01 12:00:00';

        $activities = [
            'Administrator logged in',
            'Employee profile updated',
            'RFID card assigned',
            'Attendance record updated',
            'Work schedule created',
            'Salary processed',
            'Leave request approved',
            'Holiday created',
            'Unclassified system event'
        ];

        foreach ($activities as $activity) {
            $this->insertAuditRecord($activity, $userId, $timestamp);
        }

        $filters = [
            'from_date' => '2025-08-01',
            'to_date' => '2025-08-01',
            'user_id' => (string) $userId
        ];

        $summary = $this->auditLog->getCategorySummary($filters);

        $this->assertSame(9, $summary['total']);
        $this->assertSame(1, $summary['authentication']);
        $this->assertSame(1, $summary['employee_management']);
        $this->assertSame(1, $summary['rfid_management']);
        $this->assertSame(1, $summary['attendance_management']);
        $this->assertSame(1, $summary['work_schedules']);
        $this->assertSame(1, $summary['salary_management']);
        $this->assertSame(1, $summary['leave_management']);
        $this->assertSame(1, $summary['holiday_management']);

        $authenticationSummary = $this->auditLog->getCategorySummary(
            $filters + ['module' => 'authentication']
        );

        $this->assertSame(1, $authenticationSummary['total']);
        $this->assertSame(1, $authenticationSummary['authentication']);
        $this->assertSame(0, $authenticationSummary['employee_management']);
    }
}
