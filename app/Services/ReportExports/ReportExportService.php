<?php

namespace App\Services\ReportExports;

use App\AuditLog;
use App\Exceptions\ReportExports\ReportExportException;
use App\Jobs\GenerateReportExportJob;
use App\ReportExport;
use App\Repositories\FamilyGroup\FamilyGroupRepository;
use App\Repositories\ReportExports\ReportExportRepository;

class ReportExportService
{
    const EXPIRY_HOURS = 24;

    private $repo;
    private $groupRepo;

    public function __construct(ReportExportRepository $repo, FamilyGroupRepository $groupRepo)
    {
        $this->repo      = $repo;
        $this->groupRepo = $groupRepo;
    }

    public function create(int $groupId, int $userId, array $data, string $ip, string $userAgent): ReportExport
    {
        $this->groupRepo->findOrFailForUser($groupId, $userId);

        $reportType = $data['report_type'];
        $format     = $data['format'];
        $filters    = array_filter([
            'date_from' => $data['date_from'] ?? null,
            'date_to'   => $data['date_to']   ?? null,
            'days'      => $data['days']       ?? null,
        ]);

        if ($this->repo->existsInProgress($userId, $groupId, $reportType, $format)) {
            throw new ReportExportException(
                'REPORT_EXPORT_DUPLICATE',
                'Ya existe una exportacion en progreso para este reporte y formato.',
                409
            );
        }

        $export = $this->repo->create([
            'user_id'         => $userId,
            'family_group_id' => $groupId,
            'report_type'     => $reportType,
            'format'          => $format,
            'status'          => 'pending',
        ]);

        AuditLog::create([
            'user_id'     => $userId,
            'action'      => 'report_export_requested',
            'entity_name' => 'report_exports',
            'entity_id'   => $export->id,
            'new_values'  => ['report_type' => $reportType, 'format' => $format, 'group_id' => $groupId],
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);

        GenerateReportExportJob::dispatch($export->id, $groupId, $userId, $filters);

        return $export;
    }

    public function find(int $exportId, int $userId): array
    {
        $export = $this->repo->findForOwner($exportId, $userId);

        if (!$export) {
            $export = $this->findForGroupMember($exportId, $userId);
        }

        if (!$export) {
            throw new ReportExportException(
                'REPORT_EXPORT_NOT_FOUND',
                'La exportacion no existe o no tiene acceso.',
                404
            );
        }

        if ($export->status === 'completed') {
            $export = $this->checkExpiry($export);
        }

        return $this->format($export);
    }

    private function findForGroupMember(int $exportId, int $userId): ?ReportExport
    {
        $export = ReportExport::find($exportId);
        if (!$export || !$export->family_group_id) {
            return null;
        }

        try {
            $this->groupRepo->findOrFailForUser($export->family_group_id, $userId);
            return $export;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function checkExpiry(ReportExport $export): ReportExport
    {
        if ($export->finished_at && $export->finished_at->diffInHours(now()) >= self::EXPIRY_HOURS) {
            $this->repo->update($export, ['status' => 'expired', 'file_url' => null]);
        }
        return $export;
    }

    private function format(ReportExport $export): array
    {
        $downloadUrl = null;
        if ($export->status === 'completed' && $export->file_url) {
            $downloadUrl = url('/api/v1/report-exports/' . $export->id . '/download');
        }

        return [
            'id'           => $export->id,
            'report_type'  => $export->report_type,
            'format'       => $export->format,
            'status'       => $export->status,
            'created_at'   => $export->created_at ? $export->created_at->toIso8601String() : null,
            'finished_at'  => $export->finished_at ? $export->finished_at->toIso8601String() : null,
            'download_url' => $downloadUrl,
        ];
    }
}
