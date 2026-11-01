<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/WorkSchedule.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/RfidCard.php';
require_once __DIR__ . '/../services/AttendanceService.php';

class AttendanceController extends Controller
{
    public function station(): void
    {
        Middleware::requireAdmin();

        $this->view('admin/attendance/station', [
            'error' => $_SESSION['attendance_error'] ?? null,
            'success' => $_SESSION['attendance_success'] ?? null,
            'attendanceEmployee' => $_SESSION['attendance_employee'] ?? null,
            'attendanceType' => $_SESSION['attendance_type'] ?? null,
        ]);

        unset(
            $_SESSION['attendance_error'],
            $_SESSION['attendance_success'],
            $_SESSION['attendance_employee'],
            $_SESSION['attendance_type']
        );
    }


    public function scan(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=attendance-station'
            );
        }

        $identifier = trim((string) ($_POST['rfid_identifier'] ?? ''));

        if (
            !isset($_SESSION['csrf_token']) ||
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])
        ) {
            $_SESSION['attendance_error'] = 'Invalid or expired form token. Refresh the page and try again.';
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=attendance-station'
            );
        }

        $service = new AttendanceService();
        $result = $service->processScan($identifier);

        if ($result['success']) {
            $_SESSION['attendance_success'] = $result['message'];
            $_SESSION['attendance_employee'] = $result['employee'] ?? null;
            $_SESSION['attendance_type'] = $result['type'] ?? null;

            unset($_SESSION['attendance_error']);
        } else {
            $_SESSION['attendance_error'] = $result['message'];

            unset(
                $_SESSION['attendance_success'],
                $_SESSION['attendance_employee'],
                $_SESSION['attendance_type']
            );
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=attendance-station'
        );
    }

}
