<?php

namespace App\Services\ProductReports;

use App\AuditLog;
use App\Exceptions\Ingredients\IngredientException;
use App\ProductReport;
use App\Repositories\ProductReports\ProductReportRepository;
use App\Repositories\Products\ProductRepository;
use Illuminate\Support\Facades\DB;

class ProductReportService
{
    private $reports;
    private $products;

    public function __construct(ProductReportRepository $reports, ProductRepository $products)
    {
        $this->reports = $reports;
        $this->products = $products;
    }

    public function list(array $filters)
    {
        return $this->reports->paginate($filters);
    }

    public function create(int $userId, int $productId, array $data, string $ip, string $userAgent)
    {
        $this->products->findPublicOrFail($productId);

        $type = $data['type'];

        return DB::transaction(function () use ($userId, $productId, $type, $data, $ip, $userAgent) {
            $report = $this->reports->create([
                'user_id'     => $userId,
                'product_id'  => $productId,
                'report_type' => $type,
                'description' => $data['description'] ?? null,
                'status'      => ProductReport::STATUS_OPEN,
            ]);

            AuditLog::create([
                'user_id'      => $userId,
                'action'       => 'product_report.created',
                'entity_name'  => 'product_reports',
                'entity_id'    => (string) $report->id,
                'old_values'   => null,
                'new_values'   => [
                    'product_id'  => $productId,
                    'report_type' => $type,
                    'status'      => ProductReport::STATUS_OPEN,
                ],
                'ip_address'   => $ip,
                'user_agent'   => $userAgent,
            ]);

            return $report;
        });
    }

    public function resolve(int $adminId, int $reportId, string $status, string $ip, string $userAgent)
    {
        $report = $this->reports->findOrFail($reportId);

        if ($report->status !== ProductReport::STATUS_OPEN) {
            throw new IngredientException('REPORT_ALREADY_RESOLVED', 'El reporte ya fue procesado.', 409);
        }

        return DB::transaction(function () use ($adminId, $report, $status, $ip, $userAgent) {
            $old = $this->auditPayload($report);
            $updated = $this->reports->resolve($report, $adminId, $status);
            $new = $this->auditPayload($updated);

            AuditLog::create([
                'user_id'     => $adminId,
                'action'      => 'product_report.resolved',
                'entity_name' => 'product_reports',
                'entity_id'   => (string) $report->id,
                'old_values'  => $old,
                'new_values'  => $new,
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
            ]);

            return $updated;
        });
    }

    private function auditPayload(ProductReport $report)
    {
        return [
            'report_type' => $report->report_type,
            'status'      => $report->status,
            'resolved_by' => $report->resolved_by,
            'resolved_at' => $report->resolved_at ? (string) $report->resolved_at : null,
        ];
    }
}
