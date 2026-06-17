<?php

namespace App\Services\UserSupplements;

use App\AuditLog;
use App\Exceptions\UserSupplements\UserSupplementException;
use App\Repositories\UserSupplements\SupplementLogRepository;
use App\Repositories\UserSupplements\SupplementScheduleRepository;
use App\Repositories\UserSupplements\UserSupplementRepository;
use Illuminate\Support\Facades\DB;

class SupplementScheduleService
{
    private $scheduleRepo;
    private $logRepo;
    private $supplementRepo;

    public function __construct(
        SupplementScheduleRepository $scheduleRepo,
        SupplementLogRepository $logRepo,
        UserSupplementRepository $supplementRepo
    ) {
        $this->scheduleRepo   = $scheduleRepo;
        $this->logRepo        = $logRepo;
        $this->supplementRepo = $supplementRepo;
    }

    public function listSchedules(int $userId, int $supplementId): array
    {
        $supplement = $this->supplementRepo->findForUser($userId, $supplementId);
        return $this->scheduleRepo->listForSupplement($supplement->id)->toArray();
    }

    public function createSchedule(int $userId, int $supplementId, array $data, string $ip, string $ua): array
    {
        $supplement = $this->supplementRepo->findForUser($userId, $supplementId);

        $timeOfDay  = $data['time_of_day'] ?? null;
        $daysOfWeek = $data['days_of_week'] ?? null;

        if ($this->scheduleRepo->existsDuplicate($supplement->id, $timeOfDay, $daysOfWeek)) {
            throw new UserSupplementException('SUPPLEMENT_SCHEDULE_DUPLICATE', 'Ya existe un horario con ese tiempo y dias.', 409);
        }

        return DB::transaction(function () use ($supplement, $data, $timeOfDay, $daysOfWeek, $userId, $ip, $ua) {
            $schedule = $this->scheduleRepo->create([
                'user_supplement_id' => $supplement->id,
                'time_of_day'        => $timeOfDay,
                'days_of_week'       => $daysOfWeek,
                'reminder_enabled'   => $data['reminder_enabled'] ?? false,
                'status'             => 'active',
            ]);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'supplement_schedule.create',
                'entity_name' => 'supplement_schedules',
                'entity_id'   => (string) $schedule->id,
                'old_values'  => null,
                'new_values'  => ['user_supplement_id' => $supplement->id, 'time_of_day' => $timeOfDay],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $schedule->toArray();
        });
    }

    public function updateSchedule(int $userId, int $supplementId, int $scheduleId, array $data, string $ip, string $ua): array
    {
        $supplement = $this->supplementRepo->findForUser($userId, $supplementId);
        $schedule   = $this->scheduleRepo->findForSupplement($supplement->id, $scheduleId);

        $timeOfDay  = array_key_exists('time_of_day', $data) ? $data['time_of_day'] : $schedule->time_of_day;
        $daysOfWeek = array_key_exists('days_of_week', $data) ? $data['days_of_week'] : $schedule->days_of_week;

        if ($this->scheduleRepo->existsDuplicate($supplement->id, $timeOfDay, $daysOfWeek, $schedule->id)) {
            throw new UserSupplementException('SUPPLEMENT_SCHEDULE_DUPLICATE', 'Ya existe un horario con ese tiempo y dias.', 409);
        }

        return DB::transaction(function () use ($schedule, $data, $userId, $ip, $ua) {
            $old     = ['time_of_day' => $schedule->time_of_day, 'days_of_week' => $schedule->days_of_week, 'reminder_enabled' => $schedule->reminder_enabled];
            $allowed = ['time_of_day', 'days_of_week', 'reminder_enabled'];
            $updates = array_intersect_key($data, array_flip($allowed));

            $updated = $this->scheduleRepo->update($schedule, $updates);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'supplement_schedule.update',
                'entity_name' => 'supplement_schedules',
                'entity_id'   => (string) $updated->id,
                'old_values'  => $old,
                'new_values'  => $updates,
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $updated->toArray();
        });
    }

    public function deleteSchedule(int $userId, int $supplementId, int $scheduleId, string $ip, string $ua): void
    {
        $supplement = $this->supplementRepo->findForUser($userId, $supplementId);
        $schedule   = $this->scheduleRepo->findForSupplement($supplement->id, $scheduleId);

        DB::transaction(function () use ($schedule, $userId, $ip, $ua) {
            $old = ['status' => $schedule->status, 'time_of_day' => $schedule->time_of_day];
            $this->scheduleRepo->deactivate($schedule);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'supplement_schedule.delete',
                'entity_name' => 'supplement_schedules',
                'entity_id'   => (string) $schedule->id,
                'old_values'  => $old,
                'new_values'  => ['status' => 'inactive'],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);
        });
    }

    public function createLog(int $userId, int $supplementId, array $data, string $ip, string $ua): array
    {
        $supplement = $this->supplementRepo->findForUser($userId, $supplementId);

        $takenAt = isset($data['taken_at']) ? $data['taken_at'] : now()->toDateTimeString();

        if ($this->logRepo->existsForTakenAt($supplement->id, $takenAt)) {
            throw new UserSupplementException('SUPPLEMENT_LOG_DUPLICATE', 'Ya existe un registro para ese momento.', 409);
        }

        return DB::transaction(function () use ($supplement, $data, $takenAt, $userId, $ip, $ua) {
            $log = $this->logRepo->create([
                'user_supplement_id' => $supplement->id,
                'user_id'            => $userId,
                'taken_at'           => $takenAt,
                'dose_quantity'      => $data['dose_quantity'] ?? $supplement->dose_quantity,
                'dose_unit_id'       => $data['dose_unit_id'] ?? $supplement->dose_unit_id,
                'notes'              => $data['notes'] ?? null,
                'created_at'         => now(),
            ]);

            AuditLog::create([
                'user_id'     => $userId,
                'action'      => 'supplement_log.create',
                'entity_name' => 'supplement_logs',
                'entity_id'   => (string) $log->id,
                'old_values'  => null,
                'new_values'  => ['user_supplement_id' => $supplement->id, 'taken_at' => $takenAt],
                'ip_address'  => $ip,
                'user_agent'  => $ua,
            ]);

            return $log->toArray();
        });
    }
}
