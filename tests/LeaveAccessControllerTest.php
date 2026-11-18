<?php

use PHPUnit\Framework\TestCase;

class LeaveAccessControllerTest extends TestCase
{
    private string $middlewarePath;
    private string $adminControllerPath;
    private string $employeeControllerPath;

    protected function setUp(): void
    {
        $this->middlewarePath = realpath(
            __DIR__ . '/../app/core/Middleware.php'
        );

        $this->adminControllerPath = realpath(
            __DIR__ . '/../app/controllers/AdminController.php'
        );

        $this->employeeControllerPath = realpath(
            __DIR__ . '/../app/controllers/EmployeeController.php'
        );

        $this->assertNotFalse($this->middlewarePath);
        $this->assertNotFalse($this->adminControllerPath);
        $this->assertNotFalse($this->employeeControllerPath);
    }

    /**
     * Run middleware in a separate PHP process.
     *
     * This is necessary because the middleware uses exit and die
     * when access is denied.
     */
    private function runIsolatedMiddleware(
        string $method,
        ?int $userId,
        ?int $roleId
    ): array {
        $authPath = realpath(__DIR__ . '/../app/core/Auth.php');

        $session = [];

        if ($userId !== null) {
            $session['user_id'] = $userId;
        }

        if ($roleId !== null) {
            $session['role_id'] = $roleId;
        }

        $code = '
    session_start();

    $_SESSION = ' . var_export($session, true) . ';

    require ' . var_export($authPath, true) . ';
    require ' . var_export($this->middlewarePath, true) . ';

    Middleware::' . $method . '();

    echo "ACCESS_GRANTED";
';

        $command = [
            PHP_BINARY,
            '-r',
            $code
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open(
            $command,
            $descriptors,
            $pipes
        );

        $this->assertIsResource(
            $process,
            'Unable to start isolated PHP process.'
        );

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'output' => $stdout,
            'error' => $stderr,
            'exitCode' => $exitCode
        ];
    }

    /**
     * Test ID: LEAVE-AC01
     * Purpose: Reject employees from admin-only operations.
     */
    public function testEmployeeCannotAccessAdminMiddleware(): void
    {
        $result = $this->runIsolatedMiddleware(
            'requireAdmin',
            900001,
            2
        );

        $this->assertStringContainsString(
            '403 - Access Denied',
            $result['output']
        );

        $this->assertStringNotContainsString(
            'ACCESS_GRANTED',
            $result['output']
        );
    }

    /**
     * Test ID: LEAVE-AC02
     * Purpose: Reject administrators from employee-only operations.
     */
    public function testAdminCannotAccessEmployeeMiddleware(): void
    {
        $result = $this->runIsolatedMiddleware(
            'requireEmployee',
            900002,
            1
        );

        $this->assertStringContainsString(
            '403 - Access Denied',
            $result['output']
        );

        $this->assertStringNotContainsString(
            'ACCESS_GRANTED',
            $result['output']
        );
    }

    /**
     * Test ID: LEAVE-AC03
     * Purpose: Reject unauthenticated access to admin middleware.
     */
    public function testUnauthenticatedUserCannotAccessAdminMiddleware(): void
    {
        $result = $this->runIsolatedMiddleware(
            'requireAdmin',
            null,
            null
        );

        $this->assertStringNotContainsString(
            'ACCESS_GRANTED',
            $result['output']
        );
    }

    /**
     * Test ID: LEAVE-AC04
     * Purpose: Reject unauthenticated access to employee middleware.
     */
    public function testUnauthenticatedUserCannotAccessEmployeeMiddleware(): void
    {
        $result = $this->runIsolatedMiddleware(
            'requireEmployee',
            null,
            null
        );

        $this->assertStringNotContainsString(
            'ACCESS_GRANTED',
            $result['output']
        );
    }

    /**
     * Test ID: LEAVE-AC05
     * Purpose: Allow authenticated administrators through admin middleware.
     */
    public function testAdminCanPassAdminMiddleware(): void
    {
        $result = $this->runIsolatedMiddleware(
            'requireAdmin',
            900003,
            1
        );

        $this->assertStringContainsString(
            'ACCESS_GRANTED',
            $result['output']
        );
    }

    /**
     * Test ID: LEAVE-AC06
     * Purpose: Allow authenticated employees through employee middleware.
     */
    public function testEmployeeCanPassEmployeeMiddleware(): void
    {
        $result = $this->runIsolatedMiddleware(
            'requireEmployee',
            900004,
            2
        );

        $this->assertStringContainsString(
            'ACCESS_GRANTED',
            $result['output']
        );
    }

    /**
     * Test ID: LEAVE-AC07
     * Purpose: Verify admin leave controller methods require admin access.
     */
    public function testAdminLeaveMethodsRequireAdminAccess(): void
    {
        $source = file_get_contents($this->adminControllerPath);

        $this->assertIsString($source);

        $methods = [
            'leaveManagement',
            'reviewLeaveRequest'
        ];

        foreach ($methods as $method) {
            $pattern = '/function\s+' . $method .
                '\s*\([^)]*\)\s*:\s*void\s*\{(.*?)\n\s*\}/s';

            $this->assertSame(
                1,
                preg_match($pattern, $source, $matches),
                "Could not locate AdminController::{$method}()."
            );

            $this->assertStringContainsString(
                'Middleware::requireAdmin();',
                $matches[1],
                "{$method}() must require administrator access."
            );
        }
    }

    /**
     * Test ID: LEAVE-AC08
     * Purpose: Verify employee leave controller methods require employee access.
     */
    public function testEmployeeLeaveMethodsRequireEmployeeAccess(): void
    {
        $source = file_get_contents($this->employeeControllerPath);

        $this->assertIsString($source);

        $methods = [
            'leaveApplication',
            'storeLeaveApplication'
        ];

        foreach ($methods as $method) {
            $pattern = '/function\s+' . $method .
                '\s*\([^)]*\)\s*:\s*void\s*\{(.*?)\n\s*\}/s';

            $this->assertSame(
                1,
                preg_match($pattern, $source, $matches),
                "Could not locate EmployeeController::{$method}()."
            );

            $this->assertStringContainsString(
                'Middleware::requireEmployee();',
                $matches[1],
                "{$method}() must require employee access."
            );
        }
    }
}
