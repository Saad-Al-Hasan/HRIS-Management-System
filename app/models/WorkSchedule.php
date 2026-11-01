<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Model.php';

class WorkSchedule extends Model
{
    public function getAll(): array
    {
        $sql = '
            SELECT
                id,
                name,
                expected_entry_time,
                expected_exit_time,
                working_hours
            FROM work_schedules
            ORDER BY expected_entry_time ASC, id ASC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findById(int $scheduleId): ?array
    {
        $sql = '
            SELECT
                id,
                name,
                expected_entry_time,
                expected_exit_time,
                working_hours
            FROM work_schedules
            WHERE id = :schedule_id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':schedule_id' => $scheduleId,
        ]);

        $schedule = $stmt->fetch();

        return $schedule ?: null;
    }

    public function findAssignedToEmployee(int $employeeId): ?array
    {
        $sql = '
            SELECT
                ws.id,
                ws.name,
                ws.expected_entry_time,
                ws.expected_exit_time,
                ws.working_hours
            FROM employee_schedules es
            INNER JOIN work_schedules ws
                ON ws.id = es.schedule_id
            WHERE es.employee_id = :employee_id
            ORDER BY es.id DESC
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId,
        ]);

        $schedule = $stmt->fetch();

        return $schedule ?: null;
    }

    
    public function findEffectiveScheduleForEmployee(int $employeeId): ?array
    {
        $assignedSchedule = $this->findAssignedToEmployee($employeeId);

        if ($assignedSchedule !== null) {
            return $assignedSchedule;
        }

        $sql = '
            SELECT
                id,
                name,
                expected_entry_time,
                expected_exit_time,
                working_hours
            FROM work_schedules
            WHERE name = :default_name
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':default_name' => '9 AM - 6 PM',
        ]);

        $schedule = $stmt->fetch();

        return $schedule ?: null;
    }

}
