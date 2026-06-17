<?php

namespace App\Repositories\UserSupplements;

use App\Exceptions\UserSupplements\UserSupplementException;
use App\SupplementSchedule;

class SupplementScheduleRepository
{
    public function listForSupplement(int $supplementId)
    {
        return SupplementSchedule::where('user_supplement_id', $supplementId)
            ->where('status', 'active')
            ->orderBy('time_of_day')
            ->get();
    }

    public function findForSupplement(int $supplementId, int $scheduleId): SupplementSchedule
    {
        $schedule = SupplementSchedule::where('user_supplement_id', $supplementId)
            ->where('id', $scheduleId)
            ->where('status', 'active')
            ->first();

        if (!$schedule) {
            throw new UserSupplementException('SUPPLEMENT_SCHEDULE_NOT_FOUND', 'El horario no existe.', 404);
        }

        return $schedule;
    }

    public function existsDuplicate(int $supplementId, ?string $timeOfDay, ?array $daysOfWeek, ?int $excludeId = null): bool
    {
        $query = SupplementSchedule::where('user_supplement_id', $supplementId)
            ->where('status', 'active')
            ->where('time_of_day', $timeOfDay);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($daysOfWeek !== null) {
            $query->where('days_of_week', json_encode($daysOfWeek));
        }

        return $query->exists();
    }

    public function create(array $data): SupplementSchedule
    {
        return SupplementSchedule::create($data);
    }

    public function update(SupplementSchedule $schedule, array $data): SupplementSchedule
    {
        $schedule->update($data);
        return $schedule->fresh();
    }

    public function deactivate(SupplementSchedule $schedule): void
    {
        $schedule->update(['status' => 'inactive']);
    }
}
