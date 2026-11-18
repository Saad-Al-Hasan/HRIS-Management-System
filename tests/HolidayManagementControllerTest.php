<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/controllers/HolidayController.php';

// Test helper: simulate redirects without terminating PHPUnit.
class TestRedirectException extends RuntimeException
{
}

class TestableHolidayController extends HolidayController
{
    public string $capturedView = '';
    public array $capturedData = [];
    public string $redirectUrl = '';

    protected function view(string $view, array $data = []): void
    {
        $this->capturedView = $view;
        $this->capturedData = $data;
    }

    protected function redirect(string $url): void
    {
        $this->redirectUrl = $url;

        // Simulate the real redirect terminating execution.
        throw new TestRedirectException($url);
    }
}


class HolidayManagementControllerTest extends TestCase
{
    private TestableHolidayController $controller;
    private PDO $pdo;

    // Temporary holiday IDs created during tests.
    private array $createdHolidayIds = [];

    private string $testCsrfToken = 'holiday-controller-test-token';


    protected function setUp(): void
    {
        // Connect to the separate test database.
        $database = new Database();
        $this->pdo = $database->connect();

        // Safety check: ensure the live database is never used.
        $databaseName = $this->pdo
            ->query('SELECT DATABASE()')
            ->fetchColumn();

        $this->assertSame(
            'hris_management_system_test',
            $databaseName,
            'Holiday controller tests must use the test database only.'
        );

        // Initialize session for administrator authentication.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Simulate an authenticated administrator.
        $_SESSION = [
            'user_id' => 999,
            'role_id' => 1,
            'csrf_token' => $this->testCsrfToken
        ];

        // Reset request data.
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        http_response_code(200);

        // Initialize the controller under test.
        $this->controller = new TestableHolidayController();
    }


    protected function tearDown(): void
    {
        // Delete only temporary holidays created by these tests.
        foreach ($this->createdHolidayIds as $holidayId) {

            $stmt = $this->pdo->prepare("
                DELETE FROM holidays
                WHERE id = ?
            ");

            $stmt->execute([$holidayId]);
        }

        $this->createdHolidayIds = [];

        // Reset request and session data.
        $_GET = [];
        $_POST = [];
        $_SESSION = [];

        http_response_code(200);

        // Explicitly release database-related objects.
        unset($this->controller);
        unset($this->pdo);
    }


    /**
     * Simulate a controller redirect without stopping PHPUnit.
     */
    private function invokeController(callable $callback): void
    {
        try {
            $callback();
        } catch (TestRedirectException $e) {
            // Simulate the real redirect terminating execution.
        }
    }


    /**
     * Generate an unused holiday date in the test database.
     */
    private function getAvailableTestDate(): string
    {
        $baseDate = new DateTimeImmutable('2091-01-01');

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
     * Create a temporary holiday directly in the test database.
     */
    private function createTestHoliday(
        string $name,
        string $date,
        string $type = 'Government',
        ?string $description = null
    ): int {

        $stmt = $this->pdo->prepare("
            INSERT INTO holidays
                (holiday_name, holiday_date, holiday_type, description)
            VALUES
                (?, ?, ?, ?)
        ");

        $stmt->execute([
            $name,
            $date,
            $type,
            $description
        ]);

        $holidayId = (int) $this->pdo->lastInsertId();

        $this->createdHolidayIds[] = $holidayId;

        return $holidayId;
    }


    /**
     * Prepare valid holiday submission data.
     */
    private function validHolidayPostData(array $overrides = []): array
    {
        return array_merge([
            'csrf_token' => $this->testCsrfToken,
            'holiday_name' => 'Controller Test Holiday',
            'holiday_date' => $this->getAvailableTestDate(),
            'holiday_type' => 'Government',
            'description' => 'Holiday controller unit test.'
        ], $overrides);
    }


    // =========================================================
    // HOL-C01: Index page loads successfully.
    // =========================================================

    public function testIndexLoadsHolidayManagementPage(): void
    {
        // Setup
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Call
        $this->controller->index();

        // Assertions
        $this->assertSame(
            'admin/holidays/index',
            $this->controller->capturedView
        );

        $this->assertSame(
            'Holiday Management',
            $this->controller->capturedData['pageTitle']
        );

        $this->assertIsArray(
            $this->controller->capturedData['holidays']
        );

        $this->assertArrayHasKey(
            'csrfToken',
            $this->controller->capturedData
        );
    }


    // =========================================================
    // HOL-C02: Filter holidays using an exact date.
    // =========================================================

    public function testIndexFiltersHolidaysBySelectedDate(): void
    {
        // Setup
        $selectedDate = $this->getAvailableTestDate();

        $selectedId = $this->createTestHoliday(
            'HOL_CTRL_FILTER_SELECTED',
            $selectedDate
        );

        $otherDate = $this->getAvailableTestDate();

        $this->createTestHoliday(
            'HOL_CTRL_FILTER_OTHER',
            $otherDate
        );

        $_GET = [
            'holiday_date' => $selectedDate
        ];

        // Call
        $this->controller->index();

        // Assertions
        $holidays = $this->controller->capturedData['holidays'];

        $this->assertSame(
            $selectedDate,
            $this->controller->capturedData['filterDate']
        );

        $this->assertCount(1, $holidays);

        $this->assertSame(
            $selectedId,
            (int) $holidays[0]['id']
        );
    }


    // =========================================================
    // HOL-C03: Reject invalid calendar date in index filter.
    // =========================================================

    public function testIndexRejectsInvalidCalendarDate(): void
    {
        // Setup
        $_GET = [
            'holiday_date' => '2091-02-30'
        ];

        // Call
        $this->controller->index();

        // Assertions
        $this->assertSame(
            '',
            $this->controller->capturedData['filterDate']
        );

        $this->assertSame(
            'Please select a valid holiday date.',
            $this->controller->capturedData['error']
        );

        $this->assertIsArray(
            $this->controller->capturedData['holidays']
        );
    }


    // =========================================================
    // HOL-C04: Reject non-POST holiday creation requests.
    // =========================================================

    public function testStoreRejectsNonPostRequest(): void
    {
        // Setup
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Call
        $this->controller->store();

        // Assertions
        $this->assertSame(405, http_response_code());

        $this->assertSame(
            '',
            $this->controller->redirectUrl
        );
    }


    // =========================================================
    // HOL-C05: Reject empty holiday name.
    // =========================================================

    public function testStoreRejectsEmptyHolidayName(): void
    {
        // Setup
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = $this->validHolidayPostData([
            'holiday_name' => ''
        ]);

        // Call
        $this->invokeController(
            fn() => $this->controller->store()
        );

        // Assertions
        $this->assertSame(
            'Holiday name is required and must not exceed 100 characters.',
            $_SESSION['error_message']
        );

        $this->assertSame(
            '/HRIS-Management-System/public/index.php?action=holidays',
            $this->controller->redirectUrl
        );
    }


    // =========================================================
    // HOL-C06: Reject holiday names exceeding 100 characters.
    // =========================================================

    public function testStoreRejectsHolidayNameExceeding100Characters(): void
    {
        // Setup
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = $this->validHolidayPostData([
            'holiday_name' => str_repeat('A', 101)
        ]);

        // Call
        $this->invokeController(
            fn() => $this->controller->store()
        );

        // Assertion
        $this->assertSame(
            'Holiday name is required and must not exceed 100 characters.',
            $_SESSION['error_message']
        );
    }


    // =========================================================
    // HOL-C07: Reject invalid calendar dates.
    // =========================================================

    public function testStoreRejectsInvalidCalendarDate(): void
    {
        // Setup
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = $this->validHolidayPostData([
            'holiday_date' => '2091-02-30'
        ]);

        // Call
        $this->invokeController(
            fn() => $this->controller->store()
        );

        // Assertion
        $this->assertSame(
            'Please select a valid holiday date.',
            $_SESSION['error_message']
        );
    }


    // =========================================================
    // HOL-C08: Reject unsupported holiday types.
    // =========================================================

    public function testStoreRejectsInvalidHolidayType(): void
    {
        // Setup
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = $this->validHolidayPostData([
            'holiday_type' => 'Personal'
        ]);

        // Call
        $this->invokeController(
            fn() => $this->controller->store()
        );

        // Assertion
        $this->assertSame(
            'Please select a valid holiday type.',
            $_SESSION['error_message']
        );
    }


    // =========================================================
    // HOL-C09: Reject descriptions exceeding 65535 characters.
    // =========================================================

    public function testStoreRejectsDescriptionExceeding65535Characters(): void
    {
        // Setup
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = $this->validHolidayPostData([
            'description' => str_repeat('D', 65536)
        ]);

        // Call
        $this->invokeController(
            fn() => $this->controller->store()
        );

        // Assertion
        $this->assertSame(
            'The description is too long.',
            $_SESSION['error_message']
        );
    }


    // =========================================================
    // HOL-C10: Reject duplicate holiday dates.
    // =========================================================

    public function testStoreRejectsDuplicateHolidayDate(): void
    {
        // Setup
        $date = $this->getAvailableTestDate();

        $this->createTestHoliday(
            'HOL_CTRL_DUPLICATE',
            $date
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = $this->validHolidayPostData([
            'holiday_date' => $date
        ]);

        // Call
        $this->invokeController(
            fn() => $this->controller->store()
        );

        // Assertion
        $this->assertSame(
            'A holiday already exists for the selected date.',
            $_SESSION['error_message']
        );
    }


    // =========================================================
    // HOL-C11: Create a valid holiday successfully.
    // =========================================================

    public function testStoreCreatesHolidaySuccessfully(): void
    {
        // Setup
        $date = $this->getAvailableTestDate();

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = $this->validHolidayPostData([
            'holiday_name' => 'HOL_CTRL_SUCCESS',
            'holiday_date' => $date,
            'holiday_type' => 'Government',
            'description' => 'Successful controller test.'
        ]);

        // Call
        $this->invokeController(
            fn() => $this->controller->store()
        );

        // Verify the holiday was inserted.
        $stmt = $this->pdo->prepare("
            SELECT id, holiday_name, holiday_type, description
            FROM holidays
            WHERE holiday_date = ?
        ");

        $stmt->execute([$date]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        // Assertions
        $this->assertIsArray($record);

        $this->createdHolidayIds[] = (int) $record['id'];

        $this->assertSame(
            'HOL_CTRL_SUCCESS',
            $record['holiday_name']
        );

        $this->assertSame(
            'Government',
            $record['holiday_type']
        );

        $this->assertSame(
            'Successful controller test.',
            $record['description']
        );

        $this->assertSame(
            'Holiday added successfully.',
            $_SESSION['success_message']
        );

        $this->assertSame(
            '/HRIS-Management-System/public/index.php?action=holidays',
            $this->controller->redirectUrl
        );
    }


    // =========================================================
    // HOL-C12: Convert empty optional description to NULL.
    // =========================================================

    public function testStoreConvertsEmptyDescriptionToNull(): void
    {
        // Setup
        $date = $this->getAvailableTestDate();

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = $this->validHolidayPostData([
            'holiday_name' => 'HOL_CTRL_NO_DESCRIPTION',
            'holiday_date' => $date,
            'description' => ''
        ]);

        // Call
        $this->invokeController(
            fn() => $this->controller->store()
        );

        // Verify the database record.
        $stmt = $this->pdo->prepare("
            SELECT id, description
            FROM holidays
            WHERE holiday_date = ?
        ");

        $stmt->execute([$date]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        // Assertions
        $this->assertIsArray($record);

        $this->createdHolidayIds[] = (int) $record['id'];

        $this->assertNull($record['description']);

        $this->assertSame(
            'Holiday added successfully.',
            $_SESSION['success_message']
        );
    }


    // =========================================================
    // HOL-C13: Reject non-POST holiday deletion requests.
    // =========================================================

    public function testDeleteRejectsNonPostRequest(): void
    {
        // Setup
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Call
        $this->controller->delete();

        // Assertions
        $this->assertSame(405, http_response_code());

        $this->assertSame(
            '',
            $this->controller->redirectUrl
        );
    }


    // =========================================================
    // HOL-C14: Reject invalid holiday IDs.
    // =========================================================

    public function testDeleteRejectsInvalidHolidayId(): void
    {
        // Setup
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = [
            'csrf_token' => $this->testCsrfToken,
            'id' => 'invalid'
        ];

        // Call
        $this->invokeController(
            fn() => $this->controller->delete()
        );

        // Assertions
        $this->assertSame(
            'Invalid holiday selected.',
            $_SESSION['error_message']
        );

        $this->assertSame(
            '/HRIS-Management-System/public/index.php?action=holidays',
            $this->controller->redirectUrl
        );
    }


    // =========================================================
    // HOL-C15: Delete a valid holiday successfully.
    // =========================================================

    public function testDeleteRemovesHolidaySuccessfully(): void
    {
        // Setup
        $date = $this->getAvailableTestDate();

        $holidayId = $this->createTestHoliday(
            'HOL_CTRL_DELETE',
            $date
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = [
            'csrf_token' => $this->testCsrfToken,
            'id' => (string) $holidayId
        ];

        // Call
        $this->invokeController(
            fn() => $this->controller->delete()
        );

        // Verify the holiday no longer exists.
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM holidays
            WHERE id = ?
        ");

        $stmt->execute([$holidayId]);

        // Assertions
        $this->assertSame(
            0,
            (int) $stmt->fetchColumn()
        );

        $this->assertSame(
            'Holiday deleted successfully.',
            $_SESSION['success_message']
        );

        $this->assertSame(
            '/HRIS-Management-System/public/index.php?action=holidays',
            $this->controller->redirectUrl
        );

        // The holiday has already been deleted.
        $this->createdHolidayIds = array_values(
            array_diff(
                $this->createdHolidayIds,
                [$holidayId]
            )
        );
    }
}
