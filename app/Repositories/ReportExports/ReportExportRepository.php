<?php

namespace App\Repositories\ReportExports;

use App\ReportExport;

class ReportExportRepository
{
    public function create(array $data): ReportExport
    {
        return ReportExport::create(array_merge(['created_at' => now()], $data));
    }

    public function findForOwner(int $exportId, int $userId): ?ReportExport
    {
        return ReportExport::where('id', $exportId)
            ->where('user_id', $userId)
            ->first();
    }

    public function existsInProgress(int $userId, int $groupId, string $reportType, string $format): bool
    {
        return ReportExport::where('user_id', $userId)
            ->where('family_group_id', $groupId)
            ->where('report_type', $reportType)
            ->where('format', $format)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();
    }

    public function update(ReportExport $export, array $data): void
    {
        foreach ($data as $key => $value) {
            $export->{$key} = $value;
        }
        $export->save();
    }
}
