<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/models/Holiday.php';

class HolidayManagementTest extends TestCase
{
    private PDO $pdo;

    // Model under test: Holiday
    private Holiday $holidayModel;

    // Store temporary holiday IDs for cleanup.
    private array $createdHolidayIds = [];

    protected function setUp(): void
    {
        // Setup: connect exclusively to the test database.
        $database = new Database();
        $this->pdo = $database->connect();

        // Safety check: prevent tests from running against the live database.
        $databaseName = $this->pdo
            ->query('SELECT DATABASE()')
            ->fetchColumn();

        $this->assertSame(
            'hris_management_system_test',
            $databaseName,
            'Holiday tests must never run against the live database.'
        );

        // Initialize the Holiday model.
        $this->holidayModel = new Holiday();
    }

    protected function tearDown(): void
    {
        // Cleanup: remove only temporary holidays created by this test.
        foreach ($this->createdHolidayIds as $holidayId) {
            $stmt = $this->pdo->prepare(
                "DELETE FROM holidays WHERE id = ?"
            );

            $stmt->execute([$holidayId]);
        }

        $this->createdHolidayIds = [];

        unset($this->holidayModel);
        unset($this->pdo);
    }

    /**
     * Generate an unused holiday date in the test database.
     */
    private function getAvailableTestDate(): string
    {
        $baseDate = new DateTimeImmutable('2090-01-01');

        for ($offset = 0; $offset < 3000; $offset++) {

            $date = $baseDate
                ->modify("+{$offset} days")
                ->format('Y-m-d');

            $stmt = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM holidays
                WHERE holiday_date = ?
            ");

            $stmt->execute([$date]);

            if ((int) $stmt->fetchColumn() === 0) {
                return $date;
            }
        }

        throw new RuntimeException(
            'Unable to generate an available test holiday date.'
        );
    }

    /**
     * Create a temporary holiday through the actual model.
     */
    private function createTestHoliday(
        string $name,
        string $date,
        string $type = 'Government',
        ?string $description = null
    ): int {

        $result = $this->holidayModel->create(
            $name,
            $date,
            $type,
            $description
        );

        $this->assertTrue($result);

        // Retrieve the inserted ID using the unique holiday date.
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM holidays
            WHERE holiday_date = ?
        ");

        $stmt->execute([$date]);

        $holidayId = (int) $stmt->fetchColumn();

        $this->assertGreaterThan(0, $holidayId);

        $this->createdHolidayIds[] = $holidayId;

        return $holidayId;
    }


    // Class under test: Holiday
    // Method under test: getAll()
    // Test ID: HOL-01
    // Purpose: Verify all holidays are retrieved in descending date order.

    public function testGetAllReturnsHolidaysInDescendingDateOrder(): void
    {
        // Setup
        $earlierDate = $this->getAvailableTestDate();

        $earlierId = $this->createTestHoliday(
            'HOL_TEST_EARLIER',
            $earlierDate
        );

        $laterDate = $this->getAvailableTestDate();

        $laterId = $this->createTestHoliday(
            'HOL_TEST_LATER',
            $laterDate
        );

        // Call
        $holidays = $this->holidayModel->getAll();

        $holidayIds = array_column($holidays, 'id');

        // Assertions
        $earlierPosition = array_search(
            $earlierId,
            $holidayIds
        );

        $laterPosition = array_search(
            $laterId,
            $holidayIds
        );

        $this->assertNotFalse($earlierPosition);
        $this->assertNotFalse($laterPosition);

        $this->assertGreaterThan(
            $laterPosition,
            $earlierPosition,
            'Later holiday dates should appear before earlier dates.'
        );
    }


    // Class under test: Holiday
    // Method under test: getAll()
    // Test ID: HOL-02
    // Purpose: Verify exact-date filtering returns only matching holidays.

    public function testGetAllFiltersHolidaysByExactDate(): void
    {
        // Setup
        $selectedDate = $this->getAvailableTestDate();

        $selectedId = $this->createTestHoliday(
            'HOL_TEST_SELECTED',
            $selectedDate
        );

        $otherDate = $this->getAvailableTestDate();

        $this->createTestHoliday(
            'HOL_TEST_OTHER',
            $otherDate
        );

        // Call
        $holidays = $this->holidayModel->getAll($selectedDate);

        // Assertions
        $this->assertCount(1, $holidays);

        $this->assertSame(
            $selectedId,
            (int) $holidays[0]['id']
        );

        $this->assertSame(
            $selectedDate,
            $holidays[0]['holiday_date']
        );
    }


    // Class under test: Holiday
    // Method under test: existsOnDate()
    // Test ID: HOL-03
    // Purpose: Verify an existing holiday date is detected.

    public function testExistsOnDateReturnsTrueForExistingHoliday(): void
    {
        // Setup
        $date = $this->getAvailableTestDate();

        $this->createTestHoliday(
            'HOL_TEST_EXISTS',
            $date
        );

        // Call
        $result = $this->holidayModel->existsOnDate($date);

        // Assertion
        $this->assertTrue($result);
    }


    // Class under test: Holiday
    // Method under test: existsOnDate()
    // Test ID: HOL-04
    // Purpose: Verify a non-existing holiday date returns false.

    public function testExistsOnDateReturnsFalseForNonexistentHoliday(): void
    {
        // Setup
        $date = $this->getAvailableTestDate();

        // Call
        $result = $this->holidayModel->existsOnDate($date);

        // Assertion
        $this->assertFalse($result);
    }


    // Class under test: Holiday
    // Method under test: create()
    // Test ID: HOL-05
    // Purpose: Verify a Government holiday can be created successfully.

    public function testCreateGovernmentHolidaySuccessfully(): void
    {
        // Setup
        $date = $this->getAvailableTestDate();

        // Call
        $holidayId = $this->createTestHoliday(
            'HOL_TEST_GOVERNMENT',
            $date,
            'Government',
            'Government holiday unit test.'
        );

        // Assertion
        $stmt = $this->pdo->prepare("
            SELECT holiday_name, holiday_type, description
            FROM holidays
            WHERE id = ?
        ");

        $stmt->execute([$holidayId]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($record);

        $this->assertSame(
            'HOL_TEST_GOVERNMENT',
            $record['holiday_name']
        );

        $this->assertSame(
            'Government',
            $record['holiday_type']
        );

        $this->assertSame(
            'Government holiday unit test.',
            $record['description']
        );
    }


    // Class under test: Holiday
    // Method under test: create()
    // Test ID: HOL-06
    // Purpose: Verify an Organizational holiday can be created without a description.

    public function testCreateOrganizationalHolidayWithoutDescription(): void
    {
        // Setup
        $date = $this->getAvailableTestDate();

        // Call
        $holidayId = $this->createTestHoliday(
            'HOL_TEST_ORGANIZATIONAL',
            $date,
            'Organizational',
            null
        );

        // Assertion
        $stmt = $this->pdo->prepare("
            SELECT holiday_type, description
            FROM holidays
            WHERE id = ?
        ");

        $stmt->execute([$holidayId]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($record);

        $this->assertSame(
            'Organizational',
            $record['holiday_type']
        );

        $this->assertNull($record['description']);
    }


    // Class under test: Holiday
    // Method under test: create()
    // Test ID: HOL-07
    // Purpose: Verify duplicate holiday dates are rejected by the database.

    public function testCreateRejectsDuplicateHolidayDate(): void
    {
        // Setup
        $date = $this->getAvailableTestDate();

        $this->createTestHoliday(
            'HOL_TEST_DUPLICATE_FIRST',
            $date
        );

        // Call and assertion
        try {

            $this->holidayModel->create(
                'HOL_TEST_DUPLICATE_SECOND',
                $date,
                'Government',
                null
            );

            $this->fail(
                'Expected duplicate holiday date to be rejected.'
            );

        } catch (PDOException $exception) {

            $this->assertSame(
                '23000',
                $exception->getCode()
            );
        }
    }


    // Class under test: Holiday
    // Method under test: delete()
    // Test ID: HOL-08
    // Purpose: Verify an existing holiday can be deleted successfully.

    public function testDeleteRemovesExistingHoliday(): void
    {
        // Setup
        $date = $this->getAvailableTestDate();

        $holidayId = $this->createTestHoliday(
            'HOL_TEST_DELETE',
            $date
        );

        // Call
        $result = $this->holidayModel->delete($holidayId);

        // Assertion
        $this->assertTrue($result);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM holidays
            WHERE id = ?
        ");

        $stmt->execute([$holidayId]);

        $this->assertSame(
            0,
            (int) $stmt->fetchColumn()
        );

        // Record is already deleted; remove it from cleanup list.
        $this->createdHolidayIds = array_values(
            array_diff(
                $this->createdHolidayIds,
                [$holidayId]
            )
        );
    }


    // Class under test: Holiday
    // Method under test: delete()
    // Test ID: HOL-09
    // Purpose: Verify deleting a nonexistent holiday does not delete other records.

    public function testDeleteNonexistentHolidayDoesNotAffectExistingRecords(): void
    {
        // Setup
        $date = $this->getAvailableTestDate();

        $existingId = $this->createTestHoliday(
            'HOL_TEST_RETAINED',
            $date
        );

        $missingId = 999999999;

        // Call
        $result = $this->holidayModel->delete($missingId);

        // Assertions
        $this->assertTrue($result);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM holidays
            WHERE id = ?
        ");

        $stmt->execute([$existingId]);

        $this->assertSame(
            1,
            (int) $stmt->fetchColumn()
        );
    }
}
