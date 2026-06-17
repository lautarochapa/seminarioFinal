<?php

namespace App\Repositories\UserSupplements;

use App\SupplementLog;

class SupplementLogRepository
{
    public function existsForTakenAt(int $supplementId, string $takenAt): bool
    {
        return SupplementLog::where('user_supplement_id', $supplementId)
            ->where('taken_at', $takenAt)
            ->exists();
    }

    public function create(array $data): SupplementLog
    {
        return SupplementLog::create($data);
    }
}
