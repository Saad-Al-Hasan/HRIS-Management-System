<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/WorkSchedule.php';
require_once __DIR__ . '/../models/RfidCard.php';

class AttendanceService
{
    private Attendance $attendance;
    private WorkSchedule $workSchedule;
    private RfidCard $rfidCard;

    public function __construct()
    {
        $this->attendance = new Attendance();
        $this->workSchedule = new WorkSchedule();
        $this->rfidCard = new RfidCard();
    }

    public function processScan(string $identifier): array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return $this->result(
                false,
                'Please scan a registered RFID card.'
            );
        }

        $card = $this->rfidCard->findByIdentifier($identifier);

        if ($card === null) {
            return $this->result(
                false,
                'Unregistered RFID card.'
            );
        }

        $employeeId = (int) $card['employee_id'];

        $schedule = $this->workSchedule
            ->findEffectiveScheduleForEmployee($employeeId);

        if ($schedule === null) {
            return $this->result(
                false,
                'No work schedule is available for this employee.',
                $card
            );
        }

        // Always use the real PHP server time.
        $now = new DateTimeImmutable('now');
        $nowSql = $now->format('Y-m-d H:i:s');
        $currentDate = $now->format('Y-m-d');

        /*
         * Check for an attendance record on today's calendar date.
         *
         * No PHP-session scan state is used.
         * Yesterday's record is preserved and is not updated
         * by a scan made on the new calendar date.
         */
        $todayRecord = $this->attendance->findByEmployeeAndDate(
            $employeeId,
            $currentDate
        );

        /*
         * FIRST SCAN OF TODAY: ENTRY
         */
        if ($todayRecord === null) {
            $shiftStart = new DateTimeImmutable(
                $currentDate . ' '
                . $schedule['expected_entry_time']
            );

            // Inclusive 30-minute grace period.
            $lateThreshold = $shiftStart->modify('+30 minutes');

            $entryStatus = ($now > $lateThreshold)
                ? 'Entry - Late'
                : 'Entry - In Time';

            // Preserve the existing legacy status column.
            $legacyStatus = ($entryStatus === 'Entry - Late')
                ? 'late'
                : 'present';

            try {
                $this->attendance->createEntry(
                    $employeeId,
                    $currentDate,
                    $nowSql,
                    $entryStatus,
                    $legacyStatus
                );
            } catch (PDOException $exception) {
                error_log(
                    '[HRIS ATTENDANCE ENTRY ERROR] '
                    . $exception->getMessage()
                );

                return $this->result(
                    false,
                    'The entry could not be recorded safely. Please try again or contact an administrator.',
                    $card
                );
            }

            return $this->result(
                true,
                'Entry recorded at '
                . $now->format('h:i:s A')
                . '. Status: ' . $entryStatus . '.',
                $card,
                'ENTRY'
            );
        }

        /*
         * EVERY SUBSEQUENT SCAN TODAY: EXIT
         *
         * Entry time and entry status remain unchanged.
         * Every accepted subsequent scan updates the same row's
         * exit time and exit status.
         */

        $scheduledExit = new DateTimeImmutable(
            $currentDate . ' '
            . $schedule['expected_exit_time']
        );

        // One-hour exit grace period.
        $exitGraceThreshold = $scheduledExit->modify('-1 hour');

        $exitStatus = ($now >= $exitGraceThreshold)
            ? 'Exit - In Time'
            : 'Exit - Before';

        try {
            $exitRecorded = $this->attendance->recordExit(
                (int) $todayRecord['id'],
                $nowSql,
                $exitStatus
            );

            if (!$exitRecorded) {
                return $this->result(
                    false,
                    'The attendance record could not be found. Please contact an administrator.',
                    $card
                );
            }
        } catch (PDOException $exception) {
            error_log(
                '[HRIS ATTENDANCE EXIT ERROR] '
                . $exception->getMessage()
            );

            return $this->result(
                false,
                'The exit could not be recorded safely. Please try again or contact an administrator.',
                $card
            );
        }

        return $this->result(
            true,
            'Exit recorded successfully at '
            . $now->format('h:i:s A')
            . '. Status: ' . $exitStatus . '.',
            $card,
            'EXIT'
        );
    }

    private function result(
        bool $success,
        string $message,
        ?array $employee = null,
        ?string $type = null
    ): array {
        return [
            'success' => $success,
            'message' => $message,
            'employee' => $employee,
            'type' => $type,
        ];
    }
}
